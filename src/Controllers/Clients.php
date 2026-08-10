<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;

class Clients {
    
    /**
     * Render clients page
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        
        $title = 'Clients';
        $user  = Auth::user();
        
        if ($user->role == "Staff" || $user->role == "Inventory Manager" || $user->role == "Booking Manager") {
            return view('errors/404');
        }
        
        $clients = Database::table('clients')->where('company', $user->company)->orderBy("id", false)->get();
        foreach ($clients as $key => $client) {
            $client->active_projects = Database::table('projects')->where('client', $client->id)->where('status', "In progress")->count("id", "total")[0]->total;
            $client->total_projects = Database::table('projects')->where('client', $client->id)->count("id", "total")[0]->total;

            $total = Database::table('invoices')->where('client', $client->id)->sum("total", "total")[0]->total;
            $paid = Database::table('invoices')->where('client', $client->id)->sum("amount_paid", "total")[0]->total;

            $client->balance = $total - $paid;
        }
        
        return view("clients", compact("user", "title", "clients"));
        
    }
    
    /**
     * Create client account 
     * 
     * @return Json
     */
    public function create() {
        
        $user = Auth::user();
        
        $data = array(
            "company" => $user->company,
            "fullname" => escape(input('fullname')),
            "email" => escape(input('email')),
            "phonenumber" => escape(input('phonenumber')),
            "address" => escape(input('address'))
        );
        Database::table('clients')->insert($data);
        $clientid = Database::table('clients')->insertId();
        
        return response()->json(responder("success", "Alright!", "Client account successfully created.", "redirect('" . url('Clients@details', array(
            'clientid' => $clientid
        )) . "')"));
        
    }
    
    /**
     * Render client's details page
     * 
     * @return \Pecee\Http\Response
     */
    public function details($clientid) {
        
        $projects = $staffmembers = $quotes = $invoices = $payments = $jobcards = array();

        // Statement variables - default values taake view mein error na aaye
        $statement_invoices = array();
        $statement_total    = 0;
        $statement_paid     = 0;
        $statement_balance  = 0;
        $from = date("Y-m-d", strtotime("-6 months"));
        $to   = date("Y-m-d");

        $user   = Auth::user();
        $client = Database::table('clients')->where('company', $user->company)->where('id', $clientid)->first();
        
        if (empty($client)) {
            return view('errors/404');
        }

        $total = Database::table('invoices')->where('client', $client->id)->sum("total", "total")[0]->total;
        $paid = Database::table('invoices')->where('client', $client->id)->sum("amount_paid", "total")[0]->total;
        $client->balance = $total - $paid;

        $client->total_invoices = Database::table('invoices')->where('client', $client->id)->count("id", "total")[0]->total;
        $client->total_quotes = Database::table('quotes')->where('client', $client->id)->count("id", "total")[0]->total;
        $client->total_paid = Database::table('projectpayments')->where('client', $client->id)->sum("amount", "total")[0]->total;
        
        $title = $client->fullname;
        $client->active_projects = Database::table('projects')->where('client', $client->id)->where('status', "In progress")->count("id", "total")[0]->total;
        $client->total_projects = Database::table('projects')->where('client', $client->id)->count("id", "total")[0]->total;

        $notes = Database::table('notes')->where('item', $clientid)->where('type', "Client")->where('company', $user->company)->orderBy("id", false)->get();

        if (isset($_GET["view"]) && $_GET["view"] == "projects") {
            $projects = Database::table('projects')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            foreach ($projects as $key => $project) {
                $project->pending_tasks = Database::table('tasks')->where('project', $project->id)->where('status', "In progress")->count("id", "total")[0]->total;
                $project->total_tasks = Database::table('tasks')->where('project', $project->id)->count("id", "total")[0]->total;
                $project->expenses = Database::table('expenses')->where('project', $project->id)->sum("amount", "total")[0]->total;
                $project->taskcost = Database::table('tasks')->where('project', $project->id)->sum("cost", "total")[0]->total;
                
                $project->invoiced = Database::table('invoices')->where('project', $project->id)->sum("total", "total")[0]->total;
                $project->cost = $project->taskcost + $project->expenses;
            }
            $staffmembers = Database::table('users')->where('company', $user->company)->where('role', "Staff")->orderBy("id", false)->get();

        } elseif (isset($_GET["view"]) && $_GET["view"] == "quotes") {

            $quotes = Database::table('quotes')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            foreach ($quotes as $key => $quote) {
                $quote->items = Database::table('quoteitems')->where('quote', $quote->id)->count("id", "total")[0]->total;
                $projectId = $quote->project;
                $quote->project = Database::table('projects')->where('company', $user->company)->where('id', $projectId)->first();
                if (empty($quote->project)) {
                    $quote->project = $this->archivedProject($projectId);
                }
            }

        } elseif (isset($_GET["view"]) && $_GET["view"] == "payments") {

            $payments = Database::table('projectpayments')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            foreach ($payments as $key => $payment) {
                $projectId = $payment->project;
                $payment->project = Database::table('projects')->where('company', $user->company)->where('id', $projectId)->first();
                if (empty($payment->project)) {
                    $payment->project = $this->archivedProject($projectId);
                }
            }

        } elseif (isset($_GET["view"]) && $_GET["view"] == "jobcards") {

            $jobcards = Database::table('jobcards')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            foreach ($jobcards as $key => $jobcard) {
                $projectId = $jobcard->project;
                $jobcard->project = Database::table('projects')->where('company', $user->company)->where('id', $projectId)->first();
                if (empty($jobcard->project)) {
                    $jobcard->project = $this->archivedProject($projectId);
                }
            }

        } elseif (isset($_GET["view"]) && $_GET["view"] == "statement") {

            // Date range from GET params
            $from = !empty($_GET["from"]) ? escape($_GET["from"]) : date("Y-m-d", strtotime("-6 months"));
            $to   = !empty($_GET["to"])   ? escape($_GET["to"])   : date("Y-m-d");

            // Only calculate when dates are submitted
            if (isset($_GET['from']) && isset($_GET['to'])) {

                $statement_invoices = Database::table('invoices')
                    ->where('company', $user->company)
                    ->where('client', $client->id)
                    ->where('invoice_date', '>=', $from)
                    ->where('invoice_date', '<=', $to)
                    ->orderBy("invoice_date", true)
                    ->get();

                foreach ($statement_invoices as $key => $inv) {
                    $projectId = $inv->project;
                    $inv->project = Database::table('projects')->where('company', $user->company)->where('id', $projectId)->first();
                    if (empty($inv->project)) {
                        $inv->project = $this->archivedProject($projectId);
                    }
                    $inv->balance = (float)$inv->total - (float)$inv->amount_paid;
                    $statement_total += (float)$inv->total;
                    $statement_paid  += (float)$inv->amount_paid;
                }

                $statement_balance = $statement_total - $statement_paid;
            }

        }

        if (isset($_GET["view"]) && ($_GET["view"] == "invoices" || $_GET["view"] == "payments")) {

            $invoices = Database::table('invoices')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            foreach ($invoices as $key => $invoice) {
                $invoice->items = Database::table('invoiceitems')->where('invoice', $invoice->id)->count("id", "total")[0]->total;
                $projectId = $invoice->project;
                $invoice->project = Database::table('projects')->where('company', $user->company)->where('id', $projectId)->first();
                if (empty($invoice->project)) {
                    $invoice->project = $this->archivedProject($projectId);
                }
                $invoice->balance = $invoice->total - $invoice->amount_paid;
            }

        }
        
        return view("client-details", compact(
            "user", "title", "client", "notes",
            "projects", "staffmembers", "quotes", "invoices", "payments", "jobcards",
            "statement_invoices", "statement_total", "statement_paid", "statement_balance",
            "from", "to"
        ));
        
    }
    
    
    /**
     * Client update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        
        $user   = Auth::user();
        $client = Database::table('clients')->where('company', $user->company)->where('id', input("clientid"))->first();
        
        return view('modals/update-client', compact("client"));
        
    }
    
    /**
     * Update Client account
     * 
     * @return Json
     */
    public function update() {
        
        $user = Auth::user();
        
        $data = array(
            "fullname" => escape(input('fullname')),
            "email" => escape(input('email')),
            "phonenumber" => escape(input('phonenumber')),
            "address" => escape(input('address'))
        );
        
        Database::table('clients')->where('id', input('clientid'))->where('company', $user->company)->update($data);
        return response()->json(responder("success", "Alright!", "Client account successfully updated.", "reload()"));
        
    }
    
    /**
     * Delete client account
     * 
     * @return Json
     */
    public function delete() {
        
        $user = Auth::user();
        Database::table('clients')->where('id', input('clientid'))->where('company', $user->company)->delete();
        Database::table('notes')->where('item', input('clientid'))->where('type', "Client")->where('company', $user->company)->delete();
        
        return response()->json(responder("success", "Alright!", "Client account successfully deleted.", "redirect('" . url("Clients@get") . "', true)"));
        
    }
    


    /**
     * Global Client Statements center.
     * No database changes: totals are calculated from the existing invoices.
     */
    public function statements() {

        $user = Auth::user();
        if ($user->role == "Staff" || $user->role == "Inventory Manager" || $user->role == "Booking Manager") {
            return view('errors/404');
        }

        $from = !empty($_GET["from"]) ? escape($_GET["from"]) : date("Y-m-d", strtotime("-6 months"));
        $to   = !empty($_GET["to"]) ? escape($_GET["to"]) : date("Y-m-d");

        // When a client is selected, use the existing detailed statement screen.
        if (!empty($_GET["client"])) {
            $clientid = (int) $_GET["client"];
            $client = Database::table('clients')
                ->where('company', $user->company)
                ->where('id', $clientid)
                ->first();

            if (!empty($client)) {
                $target = (string) url('Clients@details', array('clientid' => $client->id));
                $target .= '?view=statement&from=' . urlencode($from) . '&to=' . urlencode($to);
                return response()->redirect($target);
            }
        }

        $title = 'Client Statements';
        $clients = Database::table('clients')
            ->where('company', $user->company)
            ->orderBy('id', false)
            ->get();

        // Aggregate from the current invoice table so every number matches the live data.
        $invoiceTotals = array();
        $allInvoices = Database::table('invoices')
            ->where('company', $user->company)
            ->get();

        foreach ($allInvoices as $invoice) {
            if (empty($invoice->client)) {
                continue;
            }
            $cid = (int) $invoice->client;
            if (!isset($invoiceTotals[$cid])) {
                $invoiceTotals[$cid] = array('total' => 0, 'paid' => 0, 'count' => 0);
            }
            $invoiceTotals[$cid]['total'] += (float) $invoice->total;
            $invoiceTotals[$cid]['paid'] += (float) $invoice->amount_paid;
            $invoiceTotals[$cid]['count']++;
        }

        $portfolioTotal = $portfolioPaid = $portfolioBalance = 0;
        $clientsWithBalance = 0;
        foreach ($clients as $client) {
            $stats = isset($invoiceTotals[$client->id])
                ? $invoiceTotals[$client->id]
                : array('total' => 0, 'paid' => 0, 'count' => 0);

            $client->statement_total = $stats['total'];
            $client->statement_paid = $stats['paid'];
            $client->statement_balance = $stats['total'] - $stats['paid'];
            $client->statement_invoices = $stats['count'];

            $portfolioTotal += $client->statement_total;
            $portfolioPaid += $client->statement_paid;
            $portfolioBalance += $client->statement_balance;
            if ($client->statement_balance > 0.009) {
                $clientsWithBalance++;
            }
        }

        return view('client-statements', compact(
            'user', 'title', 'clients', 'from', 'to',
            'portfolioTotal', 'portfolioPaid', 'portfolioBalance', 'clientsWithBalance'
        ));
    }

    /**
     * Client Account Statement (separate route - agar use ho)
     *
     * @return \Pecee\Http\Response
     */
    public function statement($clientid) {

        $user   = Auth::user();
        $client = Database::table('clients')
            ->where('company', $user->company)
            ->where('id', $clientid)
            ->first();

        if (empty($client)) {
            return view('errors/404');
        }

        $title = $client->fullname . " - Statement";
        $_GET["view"] = "statement";

        $from = !empty($_GET["from"]) ? escape($_GET["from"]) : date("Y-m-d", strtotime("-6 months"));
        $to   = !empty($_GET["to"])   ? escape($_GET["to"])   : date("Y-m-d");

        $statement_invoices = array();
        $statement_total    = 0;
        $statement_paid     = 0;
        $statement_balance  = 0;

        if (isset($_GET['from']) && isset($_GET['to'])) {

            $statement_invoices = Database::table('invoices')
                ->where('company', $user->company)
                ->where('client', $client->id)
                ->where('invoice_date', '>=', $from)
                ->where('invoice_date', '<=', $to)
                ->orderBy("invoice_date", true)
                ->get();

            foreach ($statement_invoices as $key => $inv) {
                $projectId = $inv->project;
                $inv->project = Database::table('projects')->where('company', $user->company)->where('id', $projectId)->first();
                if (empty($inv->project)) {
                    $inv->project = $this->archivedProject($projectId);
                }
                $inv->balance = (float)$inv->total - (float)$inv->amount_paid;
                $statement_total += (float)$inv->total;
                $statement_paid  += (float)$inv->amount_paid;
            }

            $statement_balance = $statement_total - $statement_paid;
        }

        return view("client-details", compact(
            "user", "title", "client",
            "statement_invoices", "statement_total", "statement_paid", "statement_balance",
            "from", "to"
        ) + [
            "notes"        => [],
            "projects"     => [],
            "staffmembers" => [],
            "quotes"       => [],
            "invoices"     => [],
            "payments"     => [],
            "jobcards"     => []
        ]);
    }

    /**
     * Keep historical financial records viewable when a vehicle/project
     * was removed from the active projects table.
     */
    private function archivedProject($projectId) {
        return (object) array(
            'id' => $projectId,
            'make' => 'Archived',
            'model' => 'Vehicle',
            'registration_number' => 'Record unavailable',
            'status' => 'Archived'
        );
    }

}