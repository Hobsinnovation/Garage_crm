<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;

class Companies {
    
    /**
     * Render Companies page
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        
        $title = 'Companies';
        $user  = Auth::user();
        
        if ($user->role != "Admin") {
            return view('errors/404');
        }
        
        $companies = Database::table('companies')->orderBy("id", false)->get();
        foreach ($companies as $key => $company) {
            if (!empty($company->subscription_plan)) {
                $company->plan = Database::table('plans')->where('id', $company->subscription_plan)->first();
            }
            $company->users = Database::table('users')->where("company", $company->id)->count("id", "total")[0]->total;
            $company->clients = Database::table('clients')->where("company", $company->id)->count("id", "total")[0]->total;
            $company->projects = Database::table('projects')->where("company", $company->id)->count("id", "total")[0]->total;
        }

        return view("companies", compact("user", "title", "companies"));
        
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
        }elseif (isset($_GET["view"]) && $_GET["view"] == "quotes") {

            $quotes = Database::table('quotes')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            foreach ($quotes as $key => $quote) {
                $quote->items = Database::table('quoteitems')->where('quote', $quote->id)->count("id", "total")[0]->total;
                $quote->project = Database::table('projects')->where('id', $quote->project)->first();
            }

        }elseif (isset($_GET["view"]) && $_GET["view"] == "payments") {

            $payments = Database::table('projectpayments')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            foreach ($payments as $key => $payment) {
                $payment->project = Database::table('projects')->where('id', $payment->project)->first();
            }

        }elseif (isset($_GET["view"]) && $_GET["view"] == "jobcards") {

            $jobcards = Database::table('jobcards')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            foreach ($jobcards as $key => $jobcard) {
                $jobcard->project = Database::table('projects')->where('id', $jobcard->project)->first();
            }

        }

        if (isset($_GET["view"]) && $_GET["view"] == "invoices" || isset($_GET["view"]) && $_GET["view"] == "payments") {

            $invoices = Database::table('invoices')->where('company', $user->company)->where('client', $client->id)->orderBy("id", false)->get();
            foreach ($invoices as $key => $invoice) {
                $invoice->items = Database::table('invoiceitems')->where('invoice', $invoice->id)->count("id", "total")[0]->total;
                $invoice->project = Database::table('projects')->where('id', $invoice->project)->first();
                $invoice->balance = $invoice->total - $invoice->amount_paid;
            }

        }
        
        return view("client-details", compact("user", "title", "client","notes","projects","staffmembers","quotes","invoices","payments","jobcards"));
        
    }
    
    
    /**
     * Company update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        
        $user   = Auth::user();
        $company = Database::table('companies')->where("id", input("companyid"))->first();
        
        return view('modals/update-company', compact("company"));
        
    }
    
    /**
     * Update Company account
     * 
     * @return Json
     */
    public function update() {
        
        $user = Auth::user();
        
        $data = array(
            "name" => escape(input('name')),
            "email" => escape(input('email')),
            "phone" => escape(input('phone')),
            "status" => escape(input('status'))
        );
        
        Database::table('companies')->where('id', input('companyid'))->update($data);
        return response()->json(responder("success", "Alright!", "Company account successfully updated.", "reload()"));
        
    }
    
    
    /**
     * Delete Company
     * 
     * @return Json
     */
    public function delete() {
        
        $user = Auth::user();
        if ($user->company == input("companyid")) {
            return response()->json(responder("warning", "Hmmm!", "You can not delete your own company."));
        }
        Database::table('companies')->where('id', input('companyid'))->delete();
        
        return response()->json(responder("success", "Alright!", "Company successfully deleted.", "reload()"));
        
    }

    /**
     * Render overview page
     * 
     * @return \Pecee\Http\Response
     */
    public function overview() {

        $title = 'Admin Overview';
        $user = Auth::user();

        $widgets = $this->widgets($user);
        $plans = $this->plans($user);
        $companies = $this->companies($user);

        return view('overview', compact("user","title","widgets","plans","companies"));

    }

    /**
     * Get subscription plan comparison
     * 
     * @return array
     */
    public function plans() {

        $plans = array();

        $plans["freetrial"] = Database::table("companies")->where("subscription_status", "active")->where("subscription_plan", "Free Trial")->count("id", "total")[0]->total;

        $plans["monthly"] = Database::table("companies")->where("subscription_status", "active")->where("subscription_plan", "Premium Monthly")->count("id", "total")[0]->total;

        $plans["biannually"] = Database::table("companies")->where("subscription_status", "active")->where("subscription_plan", "Premium Biannually")->count("id", "total")[0]->total;

        $plans["annually"] = Database::table("companies")->where("subscription_status", "active")->where("subscription_plan", "Premium Annually")->count("id", "total")[0]->total;

        return $plans;

    }

    /**
     * Companies List
     * 
     * @return array
     */
    public function companies() {

        $companies = Database::table('companies')->orderBy("id", false)->limit(20)->get();
        foreach ($companies as $key => $company) {
            $company->users = Database::table('users')->where("company", $company->id)->count("id", "total")[0]->total;
            $company->clients = Database::table('clients')->where("company", $company->id)->count("id", "total")[0]->total;
            $company->projects = Database::table('projects')->where("company", $company->id)->count("id", "total")[0]->total;
        }

        return $companies;
        
    }

    /**
     * Get widgets data
     * 
     * @return array
     */
    public function widgets() {

        $widgets = array();

        // active
        $widgets["active"] = Database::table("companies")->where("subscription_status", "active")->where("subscription_plan", "!=", "Free Trial")->count("id", "total")[0]->total;

        // Free Trial
        $widgets["freetrial"] = Database::table("companies")->where("subscription_status", "active")->where("subscription_plan", "Free Trial")->count("id", "total")[0]->total;

        // Cancelled
        $widgets["cancelled"] = Database::table("companies")->where("subscription_status", "!=", "active")->count("id", "total")[0]->total;

        // Total
        $widgets["total"] = Database::table("companies")->count("id", "total")[0]->total;

        return $widgets;

    }
    
}