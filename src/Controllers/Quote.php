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

class Quote {
    
    /**
     * Render quotes page
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        
        $title = 'Quotes';
        $user  = Auth::user();
        
        if ($user->role == "Staff" || $user->role == "Inventory Manager" || $user->role == "Booking Manager") {
            return view('errors/404');
        }
        
        $quotes = Database::table('quotes')->where('company', $user->company)->orderBy("id", false)->get();
        foreach ($quotes as $key => $quote) {
            $quote->items = Database::table('quoteitems')->where('quote', $quote->id)->count("id", "total")[0]->total;

            // Historical quotes can remain after an old vehicle/project was removed.
            // Never pass a NULL id into the old query builder (it creates invalid SQL).
            $projectId = $quote->project;
            $clientId = !empty($quote->client) ? $quote->client : null;
            $project = Database::table('projects')->where('company', $user->company)->where('id', $projectId)->first();
            if (!empty($project) && !empty($project->client)) {
                $clientId = $project->client;
            }

            if (empty($project)) {
                $project = $this->archivedProject($projectId, $clientId, $quote->insurance);
            }

            $client = !empty($clientId)
                ? Database::table('clients')->where('company', $user->company)->where('id', $clientId)->first()
                : null;
            if (empty($client)) {
                $client = $this->archivedClient($clientId);
            }

            $quote->project = $project;
            $quote->client = $client;
        }


        $clients = Database::table('clients')->where('company', $user->company)->orderBy("id", false)->get();
        foreach ($clients as $key => $client) {
            $client->projects = Database::table('projects')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            if (empty($client->projects)) {
                unset($clients[$key]);
            }
        }
        
        return view("quotes", compact("user", "title", "quotes","clients"));
        
    }
    
    /**
     * Create a quote
     * 
     * @return Json
     */
    public function create() {
        
        $user = Auth::user();
        $total = $tax = 0;

        $project = Database::table('projects')->where('company', $user->company)->where('id', input('project'))->first();
        if (empty($project)) {
            return response()->json(responder("error", "Vehicle not found", "The selected vehicle/job is no longer available. Please choose an active vehicle."));
        }

        $data = array(
            "company" => $user->company,
            "project" => escape(input('project')),
            "client" => $project->client,
            "notes" => escape(input('notes'))
        );

        if (!empty($project->insurance)) {
            $data["insurance"] = $project->insurance;
            unset($data["client"]);
        }

        Database::table('quotes')->insert($data);
        $quoteid = Database::table('quotes')->insertId();
        if (empty($quoteid)) {
            return response()->json(responder("error", "Hmmm!", "Something went wrong, please try again."));
        }

        foreach ($_POST["item"] as $key => $item) {
            $line = array(
                "company" => $user->company,
                "project" => escape(input('project')),
                "quote" => $quoteid,
                "item" => $_POST["item"][$key],
                "quantity" => $_POST["quantity"][$key],
                "cost" => $_POST["cost"][$key],
                "tax" => Asilify::zero($_POST["tax"][$key])
            );

            $linetotal = $this->linetotal((object) $line);

            $line["total"] = $linetotal->total + $linetotal->tax;
            $linetax = $linetotal->tax;

            Database::table('quoteitems')->insert($line);

            $total = $total + $linetotal->total;
            $tax = $tax + $linetax;

        }

        $update = array(
            "subtotal" => round($total, 2),
            "tax_amount" => round($tax, 2),
            "total" => round($total + $tax, 2)
        );

        Database::table('quotes')->where('id', $quoteid)->where('company', $user->company)->update($update);
        
        return response()->json(responder("success", "Alright!", "Quote successfully created.", "redirect('" . url('Quote@view', array(
            'quoteid' => $quoteid
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
     * Create quote form view
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
        
        return view('modals/create-quote', compact("projects","user"));
        
    }
    
    
    /**
     * Quote update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        
        $user   = Auth::user();
        $quote = Database::table('quotes')->where('company', $user->company)->where('id', input("quoteid"))->first();
        $quoteitems = Database::table('quoteitems')->where('company', $user->company)->where('quote', $quote->id)->get();
        
        return view('modals/update-quote', compact("quote","quoteitems","user"));
        
    }
    
    /**
     * Update Quote
     * 
     * @return Json
     */
    public function update() {

        $user = Auth::user();
        $total = $tax = 0;

        $quote = Database::table('quotes')->where('company', $user->company)->where('id', input("quoteid"))->first();
        $quoteitems = Database::table('quoteitems')->where('company', $user->company)->where('quote', $quote->id)->get("id");

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
                $line["quote"] = $quote->id;
                $line["project"] = $quote->project;
                $line["company"] = $quote->company;
                Database::table('quoteitems')->insert($line);
            }else{
                Database::table('quoteitems')->where('id', $_POST["itemid"][$key])->where('quote', $quote->id)->update($line);
            }

            $linetax = $linetotal->tax;

            $total = $total + $linetotal->total;
            $tax = $tax + $linetax;

        }

        $data = array(
            "notes" => escape(input("notes")),
            "subtotal" => round($total, 2),
            "tax_amount" => round($tax, 2),
            "total" => round($total + $tax, 2)
        );

        Database::table('quotes')->where('id', input("quoteid"))->where('company', $user->company)->update($data);

        foreach ($quoteitems as $key => $quoteitem) {
            if (!in_array($quoteitem->id, $_POST["itemid"])) {
                Database::table('quoteitems')->where('id', $quoteitem->id)->where('company', $user->company)->delete();
            }
        }

        Asilify::unsign($quote->id, "quote");
        
        return response()->json(responder("success", "Alright!", "Quote successfully updated.", "redirect('" . url('Quote@view', array(
            'quoteid' => $quote->id
        )) . "')"));
        
    }
    
    /**
     * Convert Quote
     * 
     * @return Json
     */
    public function convert() {

        $user = Auth::user();
        $total = 0;

        $quote = Database::table('quotes')->where('company', $user->company)->where('id', input("quote"))->first();
        if (empty($quote)) {
            return response()->json(responder("error", "Hmmm!", "Quote could not be found."));
        }

        $data = array(
            "company" => $quote->company,
            "project" => $quote->project,
            "subtotal" => $quote->subtotal,
            "tax" => $quote->tax,
            "tax_amount" => $quote->tax_amount,
            "total" => $quote->total,
            "invoice_date" => escape(input('invoice_date')),
            "due_date" => escape(input('due_date')),
            "notes" => escape(input('notes')),
            "quote" => escape(input('quote')),
            "payment_details" => escape(input('payment_details'))
        );

        if (!empty($quote->client)) {
            $data["client"] = $quote->client;
        }

        if (!empty($quote->insurance)) {
            $data["insurance"] = $quote->insurance;
        }

        Database::table('invoices')->insert($data);
        $invoiceid = Database::table('invoices')->insertId();

        $quoteitems = Database::table('quoteitems')->where('company', $user->company)->where('quote', $quote->id)->get();
        foreach ($quoteitems as $key => $quoteitem) {

            $line = array(
                "company" => $quoteitem->company,
                "project" => $quoteitem->project,
                "invoice" => $invoiceid,
                "item" => $quoteitem->item,
                "quantity" => $quoteitem->quantity,
                "cost" => $quoteitem->cost,
                "total" => $quoteitem->total,
                "tax" => $quoteitem->tax
            );

            Database::table('invoiceitems')->insert($line);

        }
        
        return response()->json(responder("success", "Alright!", "Quote successfully converted to invoice.", "redirect('" . url('invoice@view', array(
            'invoiceid' => $invoiceid
        )) . "')"));
        
    }
    
    
    /**
     * Delete Quote
     * 
     * @return Json
     */
    public function delete() {
        
        $user = Auth::user();

        $quote = Database::table('quotes')->where('company', $user->company)->where('id', input('quoteid'))->first();
        Database::table('quotes')->where('id', input('quoteid'))->where('company', $user->company)->delete();

        return response()->json(responder("success", "Alright!", "Quote successfully deleted.", "redirect('" . url('Projects@details', array(
            'projectid' => $quote->project
        )) . "?view=quotes')"));
        
    }

    /**
     * View Quote
     * 
     * @return \Pecee\Http\Response
     */
    public function view($quoteid) {

        $user   = Auth::user();
        $quote = Database::table('quotes')->where('company', $user->company)->where('id', $quoteid)->first();
        if (empty($quote)) {
            return view('errors/404');
        }

        $title = "Quote #".$quote->id;

        if (!empty($quote->client)) {
            $owner = Database::table('clients')->where('company', $user->company)->where('id', $quote->client)->first();
        }else{
            $owner = !empty($quote->insurance)
                ? Database::table('insurance')->where('company', $user->company)->where('id', $quote->insurance)->first()
                : null;
        }
        if (empty($owner)) {
            $owner = $this->archivedClient(!empty($quote->client) ? $quote->client : null);
        }

        return view('view-quote', compact("title", "user","quote","owner"));
        
    }
    
    /**
     * Generate PDF
     * 
     * @return \Pecee\Http\Response
     */
    public function render($quoteid) {
        if (ob_get_level() === 0) { ob_start(); }
        @ini_set('display_errors', '0');
        try {
            $user = Auth::user();
            if (empty($user)) { throw new \RuntimeException('Authentication session is not available to the PDF request.'); }
            $quote = Database::table('quotes')->where('company', $user->company)->where('id', $quoteid)->first();
            $quoteitems = Database::table('quoteitems')->where('company', $user->company)->where('quote', $quoteid)->get();
            if (empty($quote)) { throw new \RuntimeException('Quotation #' . $quoteid . ' was not found.'); }
            if (empty($quoteitems)) { throw new \RuntimeException('Quotation #' . $quoteid . ' has no item rows.'); }
            list($project, $client) = $this->documentRelations($user, $quote);
            $this->generate($user, $quote, $quoteitems, $project, $client);
        } catch (\Throwable $error) {
            pdf_failure_response('Quotation #' . $quoteid, $error);
        }
    }
    
    /**
     * Sign quote ( Acceptance )
     * 
     * @return \Pecee\Http\Response
     */
    public function sign() {
        
        $user   = Auth::user();
        $quote = Database::table('quotes')->where('company', $user->company)->where('id', input("quoteid"))->first();

        $upload = File::upload(input("signature"), "quotesignatures", array(
            "source" => "base64",
            "extension" => "png"
        ));
        
        if ($upload['status'] == "success") {
            $signature = array(
                "signed" => "Yes",
                "signature" => $upload['info']['name'],
                "signed_by" => input("fullname")
            );
            Database::table('quotes')->where('id', $quote->id)->update($signature);
        }else{
            return response()->json(responder("error", "Hmmm!", "Something went wrong, please try again."));
        }

        return response()->json(responder("success", "Signed!", "Quote successfully signed.", "reload()"));
        
    }
    
    /**
     * Send via email
     * 
     * @return Json
     */
    public function send() {
        
        $user   = Auth::user();
        $quote = Database::table('quotes')->where('company', $user->company)->where('id', input("itemid"))->first();
        $quoteitems = Database::table('quoteitems')->where('company', $user->company)->where('quote', input("itemid"))->get();
        
        if (empty($quote) || empty($quoteitems)) {
            return response()->json(responder("error", "Hmmm!", "Something went wrong, please try again."));
        }
        
        list($project, $client) = $this->documentRelations($user, $quote);

        $document = $this->generate($user, $quote, $quoteitems, $project, $client, true);

        $send = Mail::send(
            input("email"),
            input("subject"),
            array(
                "message" => nl2br(input("message"))
            ),
            "basic",
            null,
            array("Quote #".$quote->id.".pdf" => config("app.storage")."tmp/".$document)
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
     * Generate premium Union Star quotation PDF.
     */
    public function generate($user, $quote, $quoteitems, $project, $client, $save = false) {

        $outputName = uniqid("unionstar_quote_").".pdf";
        $outputPath = config("app.storage")."/tmp/".$outputName;

        $pdf = new PDF('P', 'px', 'A4', true, 'UTF-8', false);
        $pdf->setCompression(false);
        $pdf->SetCreator('Union Star Auto Garage CRM');
        $pdf->SetAuthor($user->parent->name);
        $pdf->SetTitle('Quotation #'.$quote->id);
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
        $safe = function($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };

        $make = trim((string) carmake($project->make));
        $model = trim((string) carmodel($project->model));
        $vehicleName = trim($make.' '.$model);
        if ($vehicleName === '') { $vehicleName = 'Vehicle record'; }
        $registration = !empty($project->registration_number) ? $project->registration_number : '--';
        $vin = !empty($project->vin) ? $project->vin : '--';
        $mileage = !empty($project->milleage) ? $project->milleage.' '.(!empty($project->milleage_unit) ? $project->milleage_unit : '') : '--';

        $logo = document_asset('assets/images/unionstar-pdf-logo.jpg');
        if (is_file($logo)) { $pdf->Image($logo, 30, 23, 62); }
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

        $pdf->SetXY(390, 20);
        $pdf->SetFont('', 'B', 20);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(165, 25, 'QUOTATION', 0, 1, 'R');
        $pdf->SetX(390);
        $pdf->SetFont('', 'B', 10);
        $pdf->SetTextColor($orange[0], $orange[1], $orange[2]);
        $pdf->Cell(165, 15, 'QTN-'.str_pad($quote->id, 6, '0', STR_PAD_LEFT), 0, 1, 'R');
        $pdf->SetX(390);
        $pdf->SetFont('', '', 8.5);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $pdf->Cell(165, 14, 'Date: '.date('d M Y', strtotime($quote->created_at)), 0, 0, 'R');

        $pdf->SetFillColor(236, 245, 255);
        $pdf->RoundedRect(475, 77, 80, 19, 5, '1111', 'F');
        $pdf->SetXY(475, 81);
        $pdf->SetFont('', 'B', 8);
        $pdf->SetTextColor(37, 99, 235);
        $pdf->Cell(80, 10, 'ESTIMATE', 0, 0, 'C');

        $pdf->SetDrawColor($navy[0], $navy[1], $navy[2]);
        $pdf->SetLineWidth(1.3);
        $pdf->Line(30, 104, 555, 104);
        $pdf->SetDrawColor($orange[0], $orange[1], $orange[2]);
        $pdf->SetLineWidth(3);
        $pdf->Line(30, 104, 145, 104);

        $cardY = 122; $cardH = 112;
        $pdf->SetFillColor($soft[0], $soft[1], $soft[2]);
        $pdf->SetDrawColor($border[0], $border[1], $border[2]);
        $pdf->RoundedRect(30, $cardY, 252, $cardH, 7, '1111', 'DF');
        $pdf->RoundedRect(296, $cardY, 259, $cardH, 7, '1111', 'DF');

        $pdf->SetXY(44, $cardY + 12);
        $pdf->SetFont('', 'B', 8.5);
        $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
        $pdf->Cell(220, 14, 'QUOTATION FOR', 0, 1, 'L');
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
        $vehicleRows = array(array('Vehicle',$vehicleName),array('Reg. No.',$registration),array('VIN',$vin),array('Mileage',$mileage));
        foreach ($vehicleRows as $row) {
            $pdf->SetX(310);
            $pdf->SetFont('', 'B', 8);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->Cell(58, 16, $row[0], 0, 0, 'L');
            $pdf->SetFont('', '', 8.5);
            $pdf->SetTextColor($text[0], $text[1], $text[2]);
            $pdf->Cell(168, 16, (string) $row[1], 0, 1, 'L');
        }

        $pdf->SetY(254);
        $rows = '';
        foreach ($quoteitems as $key => $quoteitem) {
            $bg = (($key % 2) === 0) ? '#FFFFFF' : '#F9FBFD';
            $rows .= '<tr bgcolor="'.$bg.'">'
                .'<td width="6%" align="center">'.($key + 1).'</td>'
                .'<td width="40%">'.$safe($quoteitem->item).'</td>'
                .'<td width="10%" align="center">'.$safe($quoteitem->quantity).'</td>'
                .'<td width="17%" align="right">'.$safe(money($quoteitem->cost, $user->parent->currency)).'</td>'
                .'<td width="10%" align="center">'.$safe($quoteitem->tax).'%</td>'
                .'<td width="17%" align="right"><b>'.$safe(money($quoteitem->total, $user->parent->currency)).'</b></td>'
                .'</tr>';
        }
        $table = '<table cellpadding="7" cellspacing="0" border="0" width="100%">'
            .'<tr bgcolor="#07162F" color="#FFFFFF">'
            .'<td width="6%" align="center"><b>#</b></td><td width="40%"><b>DESCRIPTION</b></td>'
            .'<td width="10%" align="center"><b>QTY</b></td><td width="17%" align="right"><b>UNIT PRICE</b></td>'
            .'<td width="10%" align="center"><b>VAT</b></td><td width="17%" align="right"><b>TOTAL</b></td></tr>'
            .$rows.'</table>';
        $pdf->SetFont('', '', 8.5);
        $pdf->SetTextColor($text[0], $text[1], $text[2]);
        $pdf->writeHTML($table, true, false, true, false, '');

        $afterTable = $pdf->GetY();
        if ($afterTable > 675) { $pdf->AddPage(); $afterTable = 42; }
        $leftY = $afterTable + 14;
        $leftEndY = $leftY;
        if (!empty($quote->notes)) {
            $pdf->SetXY(30, $leftY);
            $pdf->SetFont('', 'B', 9);
            $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
            $pdf->Cell(255, 15, 'NOTES', 0, 1, 'L');
            $pdf->SetX(30);
            $pdf->SetFont('', '', 8);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->MultiCell(255, 55, (string) $quote->notes, 1, 'L', false, 1, 30, '', true, 0, false, true, 55, 'T');
            $leftEndY = $pdf->GetY();
        }

        $x = 330; $rightY = $afterTable + 10;
        $pdf->SetXY($x, $rightY);
        $pdf->SetFont('', '', 9);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $pdf->Cell(85, 20, 'Subtotal', 0, 0, 'L');
        $pdf->SetTextColor($text[0], $text[1], $text[2]);
        $pdf->Cell(140, 20, money($quote->subtotal, $user->parent->currency), 0, 1, 'R');
        $pdf->SetX($x);
        $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
        $pdf->Cell(85, 20, 'VAT', 0, 0, 'L');
        $pdf->SetTextColor($text[0], $text[1], $text[2]);
        $pdf->Cell(140, 20, money($quote->tax_amount, $user->parent->currency), 0, 1, 'R');
        $pdf->SetFillColor($navy[0], $navy[1], $navy[2]);
        $pdf->RoundedRect($x, $pdf->GetY() + 3, 225, 38, 6, '1111', 'F');
        $pdf->SetXY($x + 12, $pdf->GetY() + 13);
        $pdf->SetFont('', 'B', 10);
        $pdf->SetTextColor(255,255,255);
        $pdf->Cell(75, 18, 'TOTAL', 0, 0, 'L');
        $pdf->SetFont('', 'B', 14);
        $pdf->Cell(126, 18, money($quote->total, $user->parent->currency), 0, 1, 'R');
        $rightEndY = $pdf->GetY() + 24;
        $bottomY = max($rightEndY, $leftEndY + 22);

        if ($bottomY < 730) {
            $pdf->SetDrawColor($border[0], $border[1], $border[2]);
            $pdf->Line(30, $bottomY, 205, $bottomY);
            $pdf->SetXY(30, $bottomY + 6);
            $pdf->SetFont('', '', 8);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->Cell(175, 14, 'Authorized Signature', 0, 0, 'L');
            if ($user->parent->quote_signing == 'Enabled' && !empty($quote->signed_by)) {
                $pdf->SetXY(390, $bottomY + 6);
                $pdf->Cell(165, 14, 'Client: '.(string) $quote->signed_by, 0, 0, 'R');
            }
        }

        if (!empty($user->parent->quote_disclaimer)) {
            $disclaimerY = max($bottomY + 34, $leftEndY + 26, $rightEndY + 26);
            if ($disclaimerY > 735) { $pdf->AddPage(); $disclaimerY = 54; }
            $pdf->SetY($disclaimerY);
            $pdf->SetFont('', 'B', 8);
            $pdf->SetTextColor($navy[0], $navy[1], $navy[2]);
            $pdf->Cell(525, 14, 'TERMS & NOTES', 0, 1, 'L');
            $pdf->SetFont('', '', 7.5);
            $pdf->SetTextColor($muted[0], $muted[1], $muted[2]);
            $pdf->MultiCell(525, 38, html_entity_decode((string) $user->parent->quote_disclaimer, ENT_QUOTES, 'UTF-8'), 0, 'L');
        }

        if ($save) {
            $pdf->Output($outputPath, 'F');
            return $outputName;
        }
        pdf_inline_response($pdf, 'Quotation-'.$quote->id.'.pdf');
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
