<?php
namespace Simcify\Controllers;

$tcpdfPath = str_replace("Controllers", "TCPDF/", dirname(__FILE__));

require_once $tcpdfPath.'tcpdf.php';

use TCPDF;
use Simcify\Database;
use Simcify\Asilify;
use Simcify\Auth;
use Simcify\Mail;
use Simcify\File;

class Invoice {
    
    /**
     * Render invoice page
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        
        $title = 'Invoices';
        $user  = Auth::user();
        
        if ($user->role == "Staff" || $user->role == "Inventory Manager" || $user->role == "Booking Manager") {
            return view('errors/404');
        }
        
        $invoices = Database::table('invoices')->where('company', $user->company)->orderBy("id", false)->get();
        foreach ($invoices as $key => $invoice) {
            $invoice->items = Database::table('invoiceitems')->where('invoice', $invoice->id)->count("id", "total")[0]->total;

            $projectId = $invoice->project;
            $clientId = !empty($invoice->client) ? $invoice->client : null;
            $project = Database::table('projects')->where('company', $user->company)->where('id', $projectId)->first();
            if (!empty($project) && !empty($project->client)) {
                $clientId = $project->client;
            }
            if (empty($project)) {
                $project = $this->archivedProject($projectId, $clientId, $invoice->insurance);
            }

            $client = !empty($clientId)
                ? Database::table('clients')->where('company', $user->company)->where('id', $clientId)->first()
                : null;
            if (empty($client)) {
                $client = $this->archivedClient($clientId);
            }

            $invoice->project = $project;
            $invoice->client = $client;
            $invoice->balance = $invoice->total - $invoice->amount_paid;
        }


        $clients = Database::table('clients')->where('company', $user->company)->orderBy("id", false)->get();
        foreach ($clients as $key => $client) {
            $client->projects = Database::table('projects')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            if (empty($client->projects)) {
                unset($clients[$key]);
            }
        }
        
        return view("invoices", compact("user", "title", "invoices","clients"));
        
    }
    
    /**
     * Create an invoice
     * 
     * @return Json
     */
    public function create() {
        
        $user = Auth::user();
        $total = $tax = 0;

        if (empty($_POST["item"])) {
            return response()->json(responder("warning", "Hmmm!", "Add atleast one item."));
        }

        $project = Database::table('projects')->where('company', $user->company)->where('id', input('project'))->first();
        if (empty($project)) {
            return response()->json(responder("error", "Vehicle not found", "The selected vehicle/job is no longer available. Please choose an active vehicle."));
        }

        $data = array(
            "company" => $user->company,
            "project" => escape(input('project')),
            "client" => $project->client,
            "invoice_date" => escape(input('invoice_date')),
            "due_date" => escape(input('due_date')),
            "notes" => escape(input('notes')),
            "payment_details" => escape(input('payment_details'))
        );

        if (!empty($project->insurance)) {
            $data["insurance"] = $project->insurance;
            unset($data["client"]);
        }

        Database::table('invoices')->insert($data);
        $invoiceid = Database::table('invoices')->insertId();
        if (empty($invoiceid)) {
            return response()->json(responder("error", "Hmmm!", "Something went wrong, please try again."));
        }

        foreach ($_POST["item"] as $key => $item) {
            $line = array(
                "company" => $user->company,
                "project" => escape(input('project')),
                "invoice" => $invoiceid,
                "item" => $_POST["item"][$key],
                "quantity" => $_POST["quantity"][$key],
                "cost" => $_POST["cost"][$key],
                "tax" => Asilify::zero($_POST["tax"][$key])
            );

            $linetotal = $this->linetotal((object) $line);

            $line["total"] = $linetotal->total + $linetotal->tax;
            $linetax = $linetotal->tax;

            Database::table('invoiceitems')->insert($line);

            $total = $total + $linetotal->total;
            $tax = $tax + $linetax;

        }

        $update = array(
            "subtotal" => round($total, 2),
            "tax_amount" => round($tax, 2),
            "total" => round($total + $tax, 2)
        );

        if (empty($update["total"])) {
            $update["status"] = "Paid";
        }

        Database::table('invoices')->where('id', $invoiceid)->where('company', $user->company)->update($update);
        
        return response()->json(responder("success", "Alright!", "Invoice successfully created.", "redirect('" . url('Invoice@view', array(
            'invoiceid' => $invoiceid
        )) . "')"));
        
    }
    
    
    /**
     * Calculate line total
     * 
     * @param array
     * @return int
     */
    private function linetotal($line) {

        $total = $line->quantity * $line->cost;

        if (!empty($line->tax)) {
            $tax = ($line->tax / 100) * $total;
        }else{
            $tax = 0;
        }

        return ( object ) array(
            "total" => round($total, 2),
            "tax" => round($tax, 2),
        );

    }
    
    
    /**
     * Create invoice form view
     * 
     * @return \Pecee\Http\Response
     */
    public function createform() {
        
        $user   = Auth::user();
        if (!empty(input("clientid"))) {
            $client = Database::table('clients')->where('company', $user->company)->where('id', input("clientid"))->first();
            $projects = Database::table('projects')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
        }else{
            $insurance = Database::table('insurance')->where('company', $user->company)->where('id', input("insuranceid"))->first();
            $projects = Database::table('projects')->where('company', $user->company)->where('insurance', $insurance->id)->orderBy("id", false)->get();
        }
        
        return view('modals/create-invoice', compact("projects","user"));
        
    }
    
    
    /**
     * Invoice update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        
        $user   = Auth::user();
        $invoice = Database::table('invoices')->where('company', $user->company)->where('id', input("invoiceid"))->first();
        $invoiceitems = Database::table('invoiceitems')->where('company', $user->company)->where('invoice', $invoice->id)->get();
        
        return view('modals/update-invoice', compact("invoice","invoiceitems","user"));
        
    }
    
    /**
     * Update Invoice
     * 
     * @return Json
     */
    public function update() {

        $user = Auth::user();
        $total = $tax = 0;

        if (empty($_POST["item"])) {
            return response()->json(responder("warning", "Hmmm!", "Add atleast one item."));
        }

        $invoice = Database::table('invoices')->where('company', $user->company)->where('id', input("invoiceid"))->first();
        $invoiceitems = Database::table('invoiceitems')->where('company', $user->company)->where('invoice', $invoice->id)->get("id");

        foreach ($_POST["item"] as $key => $item) {
            $line = array(
                "item" => $_POST["item"][$key],
                "quantity" => $_POST["quantity"][$key],
                "cost" => $_POST["cost"][$key],
                "tax" => Asilify::zero($_POST["tax"][$key])
            );

            $linetotal = $this->linetotal((object) $line);

            $line["total"] = $linetotal->total + $linetotal->tax;

            if (empty($_POST["itemid"][$key])) {
                $line["invoice"] = $invoice->id;
                $line["project"] = $invoice->project;
                $line["company"] = $invoice->company;
                Database::table('invoiceitems')->insert($line);
            }else{
                Database::table('invoiceitems')->where('id', $_POST["itemid"][$key])->where('invoice', $invoice->id)->update($line);
            }

            $linetax = $linetotal->tax;

            $total = $total + $linetotal->total;
            $tax = $tax + $linetax;

        }

        $data = array(
            "invoice_date" => escape(input('invoice_date')),
            "due_date" => escape(input('due_date')),
            "notes" => escape(input('notes')),
            "payment_details" => escape(input('payment_details')),
            "subtotal" => round($total, 2),
            "tax_amount" => round($tax, 2),
            "total" => round($total + $tax, 2)
        );

        Database::table('invoices')->where('id', input("invoiceid"))->where('company', $user->company)->update($data);

        foreach ($invoiceitems as $key => $invoiceitem) {
            if (!in_array($invoiceitem->id, $_POST["itemid"])) {
                Database::table('invoiceitems')->where('id', $invoiceitem->id)->where('company', $user->company)->delete();
            }
        }

        $this->invoicestatus($user, $invoice->id);
        Asilify::unsign($invoice->id, "invoice");
        
        return response()->json(responder("success", "Alright!", "Invoice successfully updated.", "redirect('" . url('invoice@view', array(
            'invoiceid' => $invoice->id
        )) . "')"));
        
    }
    
    
    /**
     * Update invoice status
     * 
     * @return Json
     */
    private function invoicestatus($user, $invoiceid) {

        $invoice = Database::table('invoices')->where('company', $user->company)->where('id', $invoiceid)->first();

        if($invoice->amount_paid == 0.00 && $invoice->total > 0){
            $data["status"] = "Unpaid";
        }elseif($invoice->amount_paid == 0.00 && $invoice->total == 0.00){
            $data["status"] = "Paid";
        }elseif (abs(($invoice->total - $invoice->amount_paid)/$invoice->amount_paid) < 0.00001 || $invoice->amount_paid > $invoice->total) {
            $data["status"] = "Paid";
        }else{
            $data["status"] = "Partial";
        }

        Database::table('invoices')->where('id', $invoiceid)->where('company', $user->company)->update($data);

        return;

    }
    
    
    /**
     * Delete Invoice
     * 
     * @return Json
     */
    public function delete() {
        
        $user = Auth::user();

        $invoice = Database::table('invoices')->where('company', $user->company)->where('id', input('invoiceid'))->first();
        Database::table('invoices')->where('id', input('invoiceid'))->where('company', $user->company)->delete();

        return response()->json(responder("success", "Alright!", "Invoice successfully deleted.", "redirect('" . url('Projects@details', array(
            'projectid' => $invoice->project
        )) . "?view=invoices')"));
        
    }
    
    
    /**
     * Import invoice items from work requested
     * 
     * @return \Pecee\Http\Response
     */
    public function workrequested() {
        
        $user   = Auth::user();
        $project = Database::table('projects')->where('company', $user->company)->where('id', input("projectid"))->first();

        if (!empty($project->work_requested)) {
            $project->work_requested = json_decode($project->work_requested);
        }else{
            $project->work_requested = array();
        }
        
        return view('modals/import-invoice-workrequested', compact("project","user"));
        
    }
    
    
    /**
     * Import invoice items from jobcards
     * 
     * @return \Pecee\Http\Response
     */
    public function jobcards() {
        
        $items = array();
        $user   = Auth::user();
        $jobcard = Database::table('jobcards')->where('company', $user->company)->where('id', input("jobcardid"))->first();

        if(!empty($jobcard->body_report)){
            $items = array_merge($items, json_decode($jobcard->body_report));
        }

        if(!empty($jobcard->mechanical_report)){
            $items = array_merge($items, json_decode($jobcard->mechanical_report));
        }

        if(!empty($jobcard->electrical_report)){
            $items = array_merge($items, json_decode($jobcard->electrical_report));
        }
        
        return view('modals/import-invoice-jobcard', compact("items","user"));
        
    }
    
    
    /**
     * Import invoice items from expenses
     * 
     * @return \Pecee\Http\Response
     */
    public function expenses() {
        
        $user   = Auth::user();
        $expenses = Database::table('expenses')->where('company', $user->company)->where('project', input("projectid"))->orderBy("id", false)->get();
        $instance = uniqid("instance-");
        
        return view('modals/import-invoice-expenses', compact("expenses","user","instance"));
        
    }

    /**
     * View Invoice
     * 
     * @return \Pecee\Http\Response
     */
    public function view($invoiceid) {

        $user   = Auth::user();
        $invoice = Database::table('invoices')->where('company', $user->company)->where('id', $invoiceid)->first();
        if (empty($invoice)) {
            return view('errors/404');
        }

        $title = "Invoice #".$invoice->id;

        if (!empty($invoice->client)) {
            $owner = Database::table('clients')->where('company', $user->company)->where('id', $invoice->client)->first();
        }else{
            $owner = !empty($invoice->insurance)
                ? Database::table('insurance')->where('company', $user->company)->where('id', $invoice->insurance)->first()
                : null;
        }
        if (empty($owner)) {
            $owner = $this->archivedClient(!empty($invoice->client) ? $invoice->client : null);
        }

        return view('view-invoice', compact("title", "user","invoice","owner"));
        
    }
    
    /**
     * Generate PDF
     * 
     * @return \Pecee\Http\Response
     */
    public function render($invoiceid) {
        if (ob_get_level() === 0) { ob_start(); }
        @ini_set('display_errors', '0');

        try {
            $user = Auth::user();
            if (empty($user)) {
                throw new \RuntimeException('Authentication session is not available to the PDF request.');
            }

            $invoice = Database::table('invoices')->where('company', $user->company)->where('id', $invoiceid)->first();
            $invoiceitems = Database::table('invoiceitems')->where('company', $user->company)->where('invoice', $invoiceid)->get();

            if (empty($invoice)) {
                throw new \RuntimeException('Invoice #' . $invoiceid . ' was not found.');
            }
            if (empty($invoiceitems)) {
                throw new \RuntimeException('Invoice #' . $invoiceid . ' has no invoice item rows.');
            }

            list($project, $client) = $this->documentRelations($user, $invoice);
            $this->generate($user, $invoice, $invoiceitems, $project, $client);
        } catch (\Throwable $error) {
            pdf_failure_response('Invoice #' . $invoiceid, $error);
        }
    }
    
    /**
     * Sign invoice ( Acceptance )
     * 
     * @return \Pecee\Http\Response
     */
    public function sign() {
        
        $user   = Auth::user();
        $invoice = Database::table('invoices')->where('company', $user->company)->where('id', input("invoiceid"))->first();

        $upload = File::upload(input("signature"), "invoicesignatures", array(
            "source" => "base64",
            "extension" => "png"
        ));
        
        if ($upload['status'] == "success") {
            $signature = array(
                "signed" => "Yes",
                "signature" => $upload['info']['name'],
                "signed_by" => input("fullname")
            );
            Database::table('invoices')->where('id', $invoice->id)->update($signature);
        }else{
            return response()->json(responder("error", "Hmmm!", "Something went wrong, please try again."));
        }

        return response()->json(responder("success", "Signed!", "Invoice successfully signed.", "reload()"));
        
    }
    
    /**
     * Send via email
     * 
     * @return Json
     */
    public function send() {
        
        $user   = Auth::user();
        $invoice = Database::table('invoices')->where('company', $user->company)->where('id', input("itemid"))->first();
        $invoiceitems = Database::table('invoiceitems')->where('company', $user->company)->where('invoice', input("itemid"))->get();
        
        if (empty($invoice) || empty($invoiceitems)) {
            return response()->json(responder("error", "Hmmm!", "Something went wrong, please try again."));
        }
        
        list($project, $client) = $this->documentRelations($user, $invoice);

        $document = $this->generate($user, $invoice, $invoiceitems, $project, $client, true);

        $send = Mail::send(
            input("email"),
            input("subject"),
            array(
                "message" => nl2br(input("message"))
            ),
            "basic",
            null,
            array("Invoice #".$invoice->id.".pdf" => config("app.storage")."tmp/".$document)
        );

        File::delete($document, "tmp");

        if ($send) {
            return response()->json(responder("success", "Alright!", "Email successfully sent.", "reload()"));
        }else{
            return response()->json(responder("error", "Hmmm!", "Email could not be sent, please try again."));
        }

        die();
        
    }
    



    /**
     * Resolve document relations safely for current and historical records.
     */
    private function documentRelations($user, $document) {
        $projectId = !empty($document->project) ? $document->project : 0;
        $clientId = !empty($document->client) ? $document->client : null;
        $insuranceId = !empty($document->insurance) ? $document->insurance : null;

        $project = !empty($projectId)
            ? Database::table('projects')->where('company', $user->company)->where('id', $projectId)->first()
            : null;

        if (!empty($project)) {
            if (!empty($project->client)) {
                $clientId = $project->client;
            }
            if (!empty($project->insurance)) {
                $insuranceId = $project->insurance;
            }
        } else {
            $project = $this->archivedProject($projectId, $clientId, $insuranceId);
        }

        $client = !empty($clientId)
            ? Database::table('clients')->where('company', $user->company)->where('id', $clientId)->first()
            : null;
        if (empty($client)) {
            $client = $this->archivedClient($clientId);
        }

        if (!empty($insuranceId)) {
            $insurance = Database::table('insurance')->where('company', $user->company)->where('id', $insuranceId)->first();
            $project->insurance = !empty($insurance) ? $insurance : null;
        } else {
            $project->insurance = null;
        }

        return array($project, $client);
    }

    private function archivedProject($projectId, $clientId = null, $insuranceId = null) {
        return (object) array(
            'id' => $projectId,
            'client' => $clientId,
            'insurance' => $insuranceId,
            'make' => 'Archived',
            'model' => 'Vehicle',
            'registration_number' => 'Record unavailable',
            'vin' => '',
            'milleage' => '',
            'milleage_unit' => 'Kilometers'
        );
    }

    private function archivedClient($clientId = null) {
        return (object) array(
            'id' => !empty($clientId) ? $clientId : 0,
            'fullname' => 'Archived / Unknown Client',
            'phonenumber' => '',
            'email' => '',
            'address' => ''
        );
    }

/**
 * Generate PDF Statement for a Client
 * Layout: 100% same as Invoice generate() function
 */
public function renderstatement($clientid) {

        if (ob_get_level() === 0) { ob_start(); }
        @ini_set('display_errors', '0');

        $user = Auth::user();
        $client = Database::table('clients')
            ->where('company', $user->company)
            ->where('id', $clientid)
            ->first();

        if (empty($client)) {
            return view('errors/404');
        }

        $from = !empty($_GET['from']) ? escape($_GET['from']) : date('Y-m-d', strtotime('-6 months'));
        $to = !empty($_GET['to']) ? escape($_GET['to']) : date('Y-m-d');

        $invoices = Database::table('invoices')
            ->where('company', $user->company)
            ->where('client', $client->id)
            ->where('invoice_date', '>=', $from)
            ->where('invoice_date', '<=', $to)
            ->orderBy('invoice_date', true)
            ->get();

        $statementTotal = 0;
        $statementPaid = 0;
        foreach ($invoices as $invoice) {
            list($project) = $this->documentRelations($user, $invoice);
            $invoice->project_record = $project;
            $invoice->balance = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
            $statementTotal += (float) $invoice->total;
            $statementPaid += (float) $invoice->amount_paid;
        }
        $statementBalance = max(0, $statementTotal - $statementPaid);

        $pdf = new PDF('P', 'px', 'A4', true, 'UTF-8', false);
        $pdf->setCompression(false);
        $pdf->SetCreator('Union Star Auto Garage CRM');
        $pdf->SetAuthor($user->parent->name);
        $pdf->SetTitle('Client Statement - '.$client->fullname);
        $pdf->SetPrintHeader(false);
        $pdf->SetMargins(30, 24, 30);
        $pdf->SetAutoPageBreak(true, 58);
        $pdf->AddPage();
        $pdf->company = $user->parent;

        $navy = array(7, 22, 47);
        $orange = array(255, 107, 26);
        $text = array(31, 42, 61);
        $muted = array(112, 128, 151);
        $border = array(226, 232, 241);
        $soft = array(248, 250, 253);
        $green = array(16, 139, 91);
        $red = array(204, 58, 67);

        $safeVehicle = function($project) {
            if (empty($project)) { return 'Archived Vehicle'; }
            $make = !empty($project->make) ? trim((string) carmake($project->make)) : '';
            $model = !empty($project->model) ? trim((string) carmodel($project->model)) : '';
            $name = trim($make.' '.$model);
            if ($name === '' || strtolower($name) === 'archived vehicle') { $name = 'Archived Vehicle'; }
            $reg = !empty($project->registration_number) ? trim((string) $project->registration_number) : '';
            return $reg !== '' ? $name.' / '.$reg : $name;
        };

        $drawHeader = function($titleSuffix = '') use ($pdf, $user, $navy, $orange, $muted) {
            $logo = document_asset('assets/images/unionstar-pdf-logo.jpg');
            if (is_file($logo)) {
                $pdf->Image($logo, 30, 23, 62);
            }

            $pdf->SetXY(102, 24);
            $pdf->SetFont('', 'B', 14.5);
            $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
            $pdf->Cell(245, 20, strtoupper($user->parent->name), 0, 1, 'L');
            $pdf->SetX(102);
            $pdf->SetFont('', '', 8.5);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $bits = array();
            if (!empty($user->parent->phone)) { $bits[] = $user->parent->phone; }
            if (!empty($user->parent->email)) { $bits[] = $user->parent->email; }
            $pdf->Cell(280, 15, implode('  |  ', $bits), 0, 1, 'L');
            $pdf->SetX(102);
            $pdf->SetFont('', '', 8);
            $pdf->Cell(280, 14, (string) $user->parent->address, 0, 0, 'L');

            $pdf->SetXY(390, 20);
            $pdf->SetFont('', 'B', 17);
            $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
            $pdf->Cell(165, 25, 'CLIENT STATEMENT', 0, 1, 'R');
            if ($titleSuffix !== '') {
                $pdf->SetX(390);
                $pdf->SetFont('', 'B', 8.5);
                $pdf->SetTextColor($orange[0], $orange[1], $orange[2]);
                $pdf->Cell(165, 14, $titleSuffix, 0, 1, 'R');
            }

            $pdf->SetDrawColor($navy[0], $navy[1], $navy[2]);
            $pdf->SetLineWidth(1.3);
            $pdf->Line(30, 104, 555, 104);
            $pdf->SetDrawColor($orange[0], $orange[1], $orange[2]);
            $pdf->SetLineWidth(3);
            $pdf->Line(30, 104, 145, 104);
        };

        $statementRef = 'STM-'.str_pad($client->id, 5, '0', STR_PAD_LEFT).'-'.date('ymd', strtotime($to));
        $drawHeader($statementRef);

        // Customer and statement summary cards.
        $cardY = 122;
        $cardH = 120;
        $pdf->SetFillColor($soft[0], $soft[1], $soft[2]);
        $pdf->SetDrawColor($border[0], $border[1], $border[2]);
        $pdf->RoundedRect(30, $cardY, 252, $cardH, 7, '1111', 'DF');
        $pdf->RoundedRect(296, $cardY, 259, $cardH, 7, '1111', 'DF');

        $pdf->SetXY(44, $cardY + 12);
        $pdf->SetFont('', 'B', 8.5);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(220, 14, 'CUSTOMER INFORMATION', 0, 1, 'L');
        $pdf->SetX(44);
        $pdf->SetFont('', 'B', 11.5);
        $pdf->SetTextColor($text[0], $text[1], $text[2]);
        $pdf->Cell(220, 18, (string) $client->fullname, 0, 1, 'L');
        $pdf->SetX(44);
        $pdf->SetFont('', '', 8.5);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        if (!empty($client->phonenumber)) { $pdf->Cell(220, 14, (string) $client->phonenumber, 0, 1, 'L'); }
        if (!empty($client->email)) { $pdf->SetX(44); $pdf->Cell(220, 14, (string) $client->email, 0, 1, 'L'); }
        if (!empty($client->address)) {
            $pdf->SetXY(44, $pdf->GetY() + 2);
            $pdf->MultiCell(220, 26, (string) $client->address, 0, 'L', false, 1);
        }

        $pdf->SetXY(310, $cardY + 12);
        $pdf->SetFont('', 'B', 8.5);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(230, 14, 'STATEMENT SUMMARY', 0, 1, 'L');
        $pdf->SetFont('', '', 8.5);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);

        $summaryRows = array(
            array('Period', date('d M Y', strtotime($from)).'  -  '.date('d M Y', strtotime($to))),
            array('Generated', date('d M Y')), 
            array('Client ID', 'AC'.str_pad($client->id, 4, '0', STR_PAD_LEFT)),
            array('Invoices', count($invoices))
        );
        $sy = $cardY + 35;
        foreach ($summaryRows as $row) {
            $pdf->SetXY(310, $sy);
            $pdf->SetFont('', 'B', 8.5);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->Cell(70, 14, $row[0], 0, 0, 'L');
            $pdf->SetFont('', '', 8.5);
            $pdf->SetTextColor($text[0], $text[1], $text[2]);
            $pdf->Cell(155, 14, (string) $row[1], 0, 1, 'R');
            $sy += 18;
        }

        // Financial KPI cards.
        $kpiY = 258;
        $kpiW = 165;
        $kpiGap = 15;
        $kpis = array(
            array('TOTAL INVOICED', money($statementTotal, $user->parent->currency), $navy),
            array('TOTAL PAID', money($statementPaid, $user->parent->currency), $green),
            array('OUTSTANDING', money($statementBalance, $user->parent->currency), $statementBalance > 0 ? $red : $green)
        );
        foreach ($kpis as $i => $kpi) {
            $x = 30 + ($i * ($kpiW + $kpiGap));
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor($border[0], $border[1], $border[2]);
            $pdf->RoundedRect($x, $kpiY, $kpiW, 54, 6, '1111', 'DF');
            $pdf->SetXY($x + 12, $kpiY + 9);
            $pdf->SetFont('', 'B', 7.5);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->Cell($kpiW - 24, 12, $kpi[0], 0, 1, 'L');
            $pdf->SetXY($x + 12, $kpiY + 25);
            $pdf->SetFont('', 'B', 13);
            $pdf->SetTextColor($kpi[2][0], $kpi[2][1], $kpi[2][2]);
            $pdf->Cell($kpiW - 24, 18, $kpi[1], 0, 0, 'L');
        }

        $drawTableHeader = function($y) use ($pdf, $navy) {
            $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('', 'B', 7.5);
            $pdf->SetXY(30, $y);
            $pdf->Cell(24, 22, '#', 0, 0, 'C', true);
            $pdf->Cell(64, 22, 'INVOICE', 0, 0, 'L', true);
            $pdf->Cell(120, 22, 'VEHICLE', 0, 0, 'L', true);
            $pdf->Cell(66, 22, 'DATE', 0, 0, 'C', true);
            $pdf->Cell(68, 22, 'TOTAL', 0, 0, 'R', true);
            $pdf->Cell(68, 22, 'PAID', 0, 0, 'R', true);
            $pdf->Cell(68, 22, 'BALANCE', 0, 0, 'R', true);
            $pdf->Cell(47, 22, 'STATUS', 0, 1, 'C', true);
            return $y + 22;
        };

        $y = $drawTableHeader(330);
        $rowHeight = 24;
        if (empty($invoices)) {
            $pdf->SetFillColor($soft[0], $soft[1], $soft[2]);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->SetFont('', '', 9);
            $pdf->SetXY(30, $y);
            $pdf->Cell(525, 34, 'No invoices found in the selected date range.', 0, 1, 'C', true);
            $y += 34;
        } else {
            foreach ($invoices as $i => $invoice) {
                if ($y + $rowHeight > 760) {
                    $pdf->AddPage();
                    $drawHeader('CONTINUED');
                    $y = $drawTableHeader(126);
                }

                $fill = ($i % 2 === 0) ? $soft : array(255, 255, 255);
                $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
                $pdf->SetTextColor($text[0], $text[1], $text[2]);
                $pdf->SetFont('', '', 7.7);
                $pdf->SetXY(30, $y);
                $pdf->Cell(24, $rowHeight, $i + 1, 0, 0, 'C', true);
                $pdf->SetFont('', 'B', 7.7);
                $pdf->Cell(64, $rowHeight, 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT), 0, 0, 'L', true);
                $pdf->SetFont('', '', 7.5);
                $vehicle = $safeVehicle($invoice->project_record);
                if (strlen($vehicle) > 28) { $vehicle = substr($vehicle, 0, 27).'...'; }
                $pdf->Cell(120, $rowHeight, $vehicle, 0, 0, 'L', true);
                $pdf->Cell(66, $rowHeight, date('d M Y', strtotime($invoice->invoice_date)), 0, 0, 'C', true);
                $pdf->Cell(68, $rowHeight, money($invoice->total, $user->parent->currency), 0, 0, 'R', true);
                $pdf->SetTextColor($green[0], $green[1], $green[2]);
                $pdf->Cell(68, $rowHeight, money($invoice->amount_paid, $user->parent->currency), 0, 0, 'R', true);
                $balanceColor = $invoice->balance > 0 ? $red : $green;
                $pdf->SetTextColor($balanceColor[0], $balanceColor[1], $balanceColor[2]);
                $pdf->Cell(68, $rowHeight, money($invoice->balance, $user->parent->currency), 0, 0, 'R', true);

                $status = strtoupper((string) $invoice->status);
                if ($status === '') { $status = $invoice->balance <= 0 ? 'PAID' : 'UNPAID'; }
                $statusColor = $status === 'PAID' ? $green : ($status === 'PARTIAL' ? array(177,112,13) : $red);
                $pdf->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
                $pdf->SetFont('', 'B', 7.2);
                $pdf->Cell(47, $rowHeight, $status, 0, 1, 'C', true);
                $y += $rowHeight;
            }
        }

        if ($y > 680) {
            $pdf->AddPage();
            $drawHeader('SUMMARY');
            $y = 135;
        } else {
            $y += 18;
        }

        // Closing summary card - same visual language as all Union Star PDFs.
        $pdf->SetFillColor($soft[0], $soft[1], $soft[2]);
        $pdf->SetDrawColor($border[0], $border[1], $border[2]);
        $pdf->RoundedRect(330, $y, 225, 102, 7, '1111', 'DF');
        $pdf->SetXY(344, $y + 12);
        $pdf->SetFont('', 'B', 8.5);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(195, 14, 'STATEMENT TOTALS', 0, 1, 'L');

        $rows = array(
            array('Total Invoiced', money($statementTotal, $user->parent->currency), $text),
            array('Total Paid', money($statementPaid, $user->parent->currency), $green),
            array('Outstanding Balance', money($statementBalance, $user->parent->currency), $statementBalance > 0 ? $red : $green)
        );
        $ry = $y + 36;
        foreach ($rows as $idx => $row) {
            $pdf->SetXY(344, $ry);
            $pdf->SetFont('', $idx === 2 ? 'B' : '', 8.5);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->Cell(100, 16, $row[0], 0, 0, 'L');
            $pdf->SetFont('', 'B', $idx === 2 ? 10.5 : 8.5);
            $pdf->SetTextColor($row[2][0], $row[2][1], $row[2][2]);
            $pdf->Cell(95, 16, $row[1], 0, 1, 'R');
            $ry += 18;
        }

        $pdf->SetXY(30, $y + 12);
        $pdf->SetFont('', 'B', 8.5);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(250, 14, 'ACCOUNT STATEMENT', 0, 1, 'L');
        $pdf->SetX(30);
        $pdf->SetFont('', '', 8);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $pdf->MultiCell(270, 44, 'This statement summarizes invoices and payments recorded in Union Star Auto Garage CRM for the selected period. Please contact the garage if you need any clarification.', 0, 'L', false, 1);

        pdf_inline_response($pdf, 'Statement-'.$client->fullname.'-'.$from.'-to-'.$to.'.pdf');
    }

     
     

    /**
     * Generate premium Union Star invoice PDF.
     * Uses conservative TCPDF primitives and no stream compression for
     * maximum compatibility with XAMPP, Chrome and older PDF readers.
     */
    public function generate($user, $invoice, $invoiceitems, $project, $client, $save = false) {

        $outputName = uniqid("unionstar_invoice_").".pdf";
        $outputPath = config("app.storage")."/tmp/".$outputName;

        $pdf = new PDF('P', 'px', 'A4', true, 'UTF-8', false);
        $pdf->setCompression(false);
        $pdf->SetCreator('Union Star Auto Garage CRM');
        $pdf->SetAuthor($user->parent->name);
        $pdf->SetTitle('Invoice #'.$invoice->id);
        $pdf->SetPrintHeader(false);
        $pdf->SetMargins(30, 24, 30);
        $pdf->SetAutoPageBreak(true, 54);
        $pdf->AddPage();
        $pdf->company = $user->parent;

        $navy = array(7, 22, 47);
        $orange = array(255, 107, 26);
        $text = array(31, 42, 61);
        $muted = array(112, 128, 151);
        $border = array(226, 232, 241);
        $soft = array(248, 250, 253);

        $safe = function($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };

        $make = trim((string) carmake($project->make));
        $model = trim((string) carmodel($project->model));
        $vehicleName = trim($make.' '.$model);
        if ($vehicleName === '') { $vehicleName = 'Vehicle record'; }
        $registration = !empty($project->registration_number) ? $project->registration_number : '--';
        $vin = !empty($project->vin) ? $project->vin : '--';
        $mileage = !empty($project->milleage) ? $project->milleage.' '.(!empty($project->milleage_unit) ? $project->milleage_unit : '') : '--';

        // ===== Header =====
        $logo = document_asset('assets/images/unionstar-pdf-logo.jpg');
        if (is_file($logo)) {
            $pdf->Image($logo, 30, 23, 62);
        }

        $pdf->SetXY(102, 24);
        $pdf->SetFont('', 'B', 16);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(260, 20, strtoupper($user->parent->name), 0, 1, 'L');
        $pdf->SetX(102);
        $pdf->SetFont('', '', 8.5);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $contactBits = array();
        if (!empty($user->parent->phone)) { $contactBits[] = $user->parent->phone; }
        if (!empty($user->parent->email)) { $contactBits[] = $user->parent->email; }
        $pdf->Cell(280, 15, implode('  |  ', $contactBits), 0, 1, 'L');
        $pdf->SetX(102);
        $pdf->SetFont('', '', 8);
        $pdf->Cell(280, 14, (string) $user->parent->address, 0, 0, 'L');

        $pdf->SetXY(405, 20);
        $pdf->SetFont('', 'B', 22);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(150, 25, 'INVOICE', 0, 1, 'R');
        $pdf->SetX(405);
        $pdf->SetFont('', 'B', 10);
        $pdf->SetTextColor($orange[0], $orange[1], $orange[2]);
        $pdf->Cell(150, 15, 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT), 0, 1, 'R');
        $pdf->SetX(405);
        $pdf->SetFont('', '', 8.5);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $pdf->Cell(150, 14, 'Date: '.date('d M Y', strtotime($invoice->invoice_date)), 0, 1, 'R');
        $pdf->SetX(405);
        $pdf->Cell(150, 14, 'Due: '.date('d M Y', strtotime($invoice->due_date)), 0, 0, 'R');

        $status = strtoupper((string) $invoice->status);
        if ($status === 'PAID') { $statusColor = array(16, 139, 91); $statusBg = array(232, 248, 240); }
        elseif ($status === 'PARTIAL') { $statusColor = array(177, 112, 13); $statusBg = array(255, 246, 223); }
        else { $statusColor = array(204, 58, 67); $statusBg = array(255, 238, 240); }
        $pdf->SetFillColor($statusBg[0], $statusBg[1], $statusBg[2]);
        $pdf->RoundedRect(475, 77, 80, 19, 5, '1111', 'F');
        $pdf->SetXY(475, 81);
        $pdf->SetFont('', 'B', 8);
        $pdf->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
        $pdf->Cell(80, 10, $status, 0, 0, 'C');

        $pdf->SetDrawColor($navy[0], $navy[1], $navy[2]);
        $pdf->SetLineWidth(1.3);
        $pdf->Line(30, 104, 555, 104);
        $pdf->SetDrawColor($orange[0], $orange[1], $orange[2]);
        $pdf->SetLineWidth(3);
        $pdf->Line(30, 104, 145, 104);

        // ===== Customer + vehicle cards =====
        $cardY = 122;
        $cardH = 112;
        $pdf->SetFillColor($soft[0], $soft[1], $soft[2]);
        $pdf->SetDrawColor($border[0], $border[1], $border[2]);
        $pdf->RoundedRect(30, $cardY, 252, $cardH, 7, '1111', 'DF');
        $pdf->RoundedRect(296, $cardY, 259, $cardH, 7, '1111', 'DF');

        $pdf->SetXY(44, $cardY + 12);
        $pdf->SetFont('', 'B', 8.5);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(220, 14, 'BILL TO', 0, 1, 'L');
        $pdf->SetX(44);
        $pdf->SetFont('', 'B', 11);
        $pdf->SetTextColor($text[0], $text[1], $text[2]);
        $pdf->Cell(220, 17, (string) $client->fullname, 0, 1, 'L');
        $pdf->SetX(44);
        $pdf->SetFont('', '', 8.5);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        if (!empty($client->phonenumber)) { $pdf->Cell(220, 14, (string) $client->phonenumber, 0, 1, 'L'); $pdf->SetX(44); }
        if (!empty($client->email)) { $pdf->Cell(220, 14, (string) $client->email, 0, 1, 'L'); $pdf->SetX(44); }
        $pdf->MultiCell(220, 22, (string) $client->address, 0, 'L', false, 1);

        $pdf->SetXY(310, $cardY + 12);
        $pdf->SetFont('', 'B', 8.5);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(230, 14, 'VEHICLE INFORMATION', 0, 1, 'L');
        $vehicleRows = array(
            array('Vehicle', $vehicleName),
            array('Reg. No.', $registration),
            array('VIN', $vin),
            array('Mileage', $mileage)
        );
        foreach ($vehicleRows as $row) {
            $pdf->SetX(310);
            $pdf->SetFont('', 'B', 8);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->Cell(58, 16, $row[0], 0, 0, 'L');
            $pdf->SetFont('', '', 8.5);
            $pdf->SetTextColor($text[0], $text[1], $text[2]);
            $pdf->Cell(168, 16, (string) $row[1], 0, 1, 'L');
        }

        // ===== Items table =====
        $pdf->SetY(254);
        $rows = '';
        foreach ($invoiceitems as $key => $invoiceitem) {
            $bg = (($key % 2) === 0) ? '#FFFFFF' : '#F9FBFD';
            $rows .= '<tr bgcolor="'.$bg.'">'
                .'<td width="6%" align="center">'.($key + 1).'</td>'
                .'<td width="40%">'.$safe($invoiceitem->item).'</td>'
                .'<td width="10%" align="center">'.$safe($invoiceitem->quantity).'</td>'
                .'<td width="17%" align="right">'.$safe(money($invoiceitem->cost, $user->parent->currency)).'</td>'
                .'<td width="10%" align="center">'.$safe($invoiceitem->tax).'%</td>'
                .'<td width="17%" align="right"><b>'.$safe(money($invoiceitem->total, $user->parent->currency)).'</b></td>'
                .'</tr>';
        }
        $table = '<table cellpadding="7" cellspacing="0" border="0" width="100%">'
            .'<tr bgcolor="#07162F" color="#FFFFFF">'
            .'<td width="6%" align="center"><b>#</b></td>'
            .'<td width="40%"><b>DESCRIPTION</b></td>'
            .'<td width="10%" align="center"><b>QTY</b></td>'
            .'<td width="17%" align="right"><b>UNIT PRICE</b></td>'
            .'<td width="10%" align="center"><b>VAT</b></td>'
            .'<td width="17%" align="right"><b>TOTAL</b></td>'
            .'</tr>'.$rows.'</table>';
        $pdf->SetFont('', '', 8.5);
        $pdf->SetTextColor($text[0], $text[1], $text[2]);
        $pdf->writeHTML($table, true, false, true, false, '');

        $afterTable = $pdf->GetY();
        if ($afterTable > 675) {
            $pdf->AddPage();
            $afterTable = 42;
        }

        // ===== Notes / payment details + totals =====
        $leftY = $afterTable + 14;
        $rightY = $afterTable + 10;

        $leftEndY = $leftY;

        if (!empty($invoice->payment_details) || !empty($invoice->notes)) {
            $pdf->SetXY(30, $leftY);
            $pdf->SetFont('', 'B', 9);
            $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
            $pdf->Cell(255, 15, !empty($invoice->payment_details) ? 'PAYMENT INSTRUCTIONS' : 'NOTES', 0, 1, 'L');
            $pdf->SetX(30);
            $pdf->SetFont('', '', 8);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $copy = !empty($invoice->payment_details) ? $invoice->payment_details : $invoice->notes;
            $copy = str_replace(array('\\r\\n', '\\n', '\\r'), "\n", (string) $copy);
            $copyHeight = max(44, min(78, $pdf->getStringHeight(247, $copy) + 18));
            $pdf->SetDrawColor($border[0], $border[1], $border[2]);
            $pdf->SetFillColor(250, 252, 255);
            $pdf->MultiCell(255, $copyHeight, $copy, 1, 'L', true, 1, 30, '', true, 0, false, true, $copyHeight, 'M');
            if (!empty($invoice->payment_details) && !empty($invoice->notes)) {
                $pdf->SetX(30);
                $pdf->SetFont('', 'B', 8.5);
                $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
                $pdf->Cell(255, 14, 'NOTES', 0, 1, 'L');
                $pdf->SetX(30);
                $pdf->SetFont('', '', 8);
                $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
                $noteCopy = str_replace(array('\\r\\n', '\\n', '\\r'), "\n", (string) $invoice->notes);
                $noteHeight = max(36, min(64, $pdf->getStringHeight(247, $noteCopy) + 16));
                $pdf->SetFillColor(250, 252, 255);
                $pdf->MultiCell(255, $noteHeight, $noteCopy, 1, 'L', true, 1, 30, '', true, 0, false, true, $noteHeight, 'M');
            }
            $leftEndY = $pdf->GetY();
        }

        $x = 330;
        $pdf->SetXY($x, $rightY);
        $pdf->SetFont('', '', 9);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $pdf->Cell(85, 20, 'Subtotal', 0, 0, 'L');
        $pdf->SetTextColor($text[0], $text[1], $text[2]);
        $pdf->Cell(140, 20, money($invoice->subtotal, $user->parent->currency), 0, 1, 'R');
        $pdf->SetX($x);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $pdf->Cell(85, 20, 'VAT', 0, 0, 'L');
        $pdf->SetTextColor($text[0], $text[1], $text[2]);
        $pdf->Cell(140, 20, money($invoice->tax_amount, $user->parent->currency), 0, 1, 'R');
        if (!empty($invoice->amount_paid)) {
            $pdf->SetX($x);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->Cell(85, 20, 'Paid', 0, 0, 'L');
            $pdf->SetTextColor(16, 139, 91);
            $pdf->Cell(140, 20, money($invoice->amount_paid, $user->parent->currency), 0, 1, 'R');
        }

        // Keep the totals area clean: subtotal, VAT and paid amount are enough here.
        $rightEndY = $pdf->GetY() + 8;

        // Signature/status footer area.
        $bottomY = max($rightEndY, $leftEndY + 22);
        if ($bottomY < 730) {
            $pdf->SetXY(30, $bottomY);
            $pdf->SetDrawColor($border[0], $border[1], $border[2]);
            $pdf->Line(30, $bottomY, 205, $bottomY);
            $pdf->SetXY(30, $bottomY + 6);
            $pdf->SetFont('', '', 8);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->Cell(175, 14, 'Authorized Signature', 0, 0, 'L');

            if ($user->parent->invoice_signing == 'Enabled' && !empty($invoice->signed_by)) {
                $pdf->SetXY(390, $bottomY + 6);
                $pdf->SetFont('', '', 8);
                $pdf->Cell(165, 14, 'Client: '.(string) $invoice->signed_by, 0, 0, 'R');
            }
        }

        if (!empty($user->parent->invoice_disclaimer)) {
            $disclaimerY = max($bottomY + 34, $leftEndY + 26, $rightEndY + 26);
            if ($disclaimerY > 735) {
                $pdf->AddPage();
                $disclaimerY = 54;
            }
            $pdf->SetY($disclaimerY);
            $pdf->SetFont('', 'B', 8);
            $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
            $pdf->Cell(525, 14, 'TERMS & NOTES', 0, 1, 'L');
            $pdf->SetFont('', '', 7.5);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->MultiCell(525, 38, (string) $user->parent->invoice_disclaimer, 0, 'L');
        }

        if ($save) {
            $pdf->Output($outputPath, 'F');
            return $outputName;
        }

        pdf_inline_response($pdf, 'Invoice-'.$invoice->id.'.pdf');
    }

    
}



class PDF extends TCPDF {

    public $company;

    public function Footer() {
        $this->SetY(-34);
        $this->SetDrawColor(7, 22, 47);
        $this->SetLineWidth(1.2);
        $this->Line(30, $this->GetY(), 555, $this->GetY());
        $this->SetDrawColor(255, 107, 26);
        $this->SetLineWidth(3);
        $this->Line(30, $this->GetY(), 125, $this->GetY());
        $this->SetY(-25);
        $this->SetFont('', '', 7.5);
        $this->SetTextColor(112, 128, 151);
        $name = (!empty($this->company) && !empty($this->company->name)) ? $this->company->name : 'Union Star Auto Garage';
        $this->Cell(300, 13, $name . '  |  Drive with confidence', 0, 0, 'L');
        $this->Cell(225, 13, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}
