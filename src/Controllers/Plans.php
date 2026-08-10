<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Auth;

class Plans {
    
    /**
     * Render makes page
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        
        $title = 'Plans';
        $user  = Auth::user();
        if ($user->role != "Admin") {
            return view('errors/404');
        }
        
        $plans = Database::table('plans')->orderBy("id", false)->get();
        foreach ($plans as $key => $plan) {
            $plan->companies = Database::table('companies')->where('subscription_plan', $plan->id)->count("id", "total")[0]->total;
        }
        
        return view("plans-admin", compact("user", "title", "plans"));
        
    }
    
    /**
     * Render billing page
     * 
     * @return \Pecee\Http\Response
     */
    public function payments() {

        $user = Auth::user();
        $title = "Billing Payments";
        if ($user->role != "Admin") {
            return view('errors/404');
        }

        $payments = Database::table('payments')->orderBy("id", false)->get();
        foreach ($payments as $key => $payment) {
            $payment->company = Database::table('companies')->where('id', $payment->company)->first();
        }

        return view("admin-payments", compact("user", "title", "payments"));

    }
    
    /**
     * Create a plan 
     * 
     * @return Json
     */
    public function create() {
        
        $user = Auth::user();
        
        $data = array(
            "name" => escape(input('name')),
            "description" => escape(input('description')),
            "monthly_fee" => escape(input('monthly_fee')),
            "annual_fee" => escape(input('annual_fee'))
        );

        if (!empty(input("trial_days"))) {
            $data["trial_days"] = escape(input("trial_days"));
        }

        if (!empty(input("users_limit"))) {
            $data["users_limit"] = escape(input("users_limit"));
        }

        if (!empty(input("projects_limit"))) {
            $data["projects_limit"] = escape(input("projects_limit"));
        }

        Database::table('plans')->insert($data);
        
        return response()->json(responder("success", "Alright!", "Plan successfully created.", "reload()"));
        
    }
    
    
    /**
     * plan update view
     * 
     * @return \Pecee\Http\Response
     */
    public function updateview() {
        
        $plan = Database::table('plans')->where('id', input("planid"))->first();
        
        return view('modals/update-plan', compact("plan"));
        
    }
    
    /**
     * Update plan
     * 
     * @return Json
     */
    public function update() {
        
        $user = Auth::user();
        
        $data = array(
            "name" => escape(input('name')),
            "description" => escape(input('description')),
            "monthly_fee" => escape(input('monthly_fee')),
            "annual_fee" => escape(input('annual_fee'))
        );

        if (!empty(input("trial_days"))) {
            $data["trial_days"] = escape(input("trial_days"));
        }else{
            $data["trial_days"] = "NULL";
        }

        if (!empty(input("users_limit"))) {
            $data["users_limit"] = escape(input("users_limit"));
        }else{
            $data["users_limit"] = "NULL";
        }

        if (!empty(input("projects_limit"))) {
            $data["projects_limit"] = escape(input("projects_limit"));
        }else{
            $data["projects_limit"] = "NULL";
        }
        
        Database::table('plans')->where('id', input('planid'))->update($data);
        
        return response()->json(responder("success", "Alright!", "Plan successfully updated.", "reload()"));
        
    }
    
    /**
     * Delete make
     * 
     * @return Json
     */
    public function delete() {
        
        $user = Auth::user();

        $company = Database::table('companies')->where('subscription_plan', input("planid"))->first();
        if (!empty($company)) {
            return response()->json(responder("warning", "Hmmm!", "This plan can not be deleted because a company is subscribed to it."));
        }

        Database::table('plans')->where('id', input("planid"))->delete();

        return response()->json(responder("success", "Alright!", "Plan successfully deleted.", "reload()"));
        
    }


    /**
     * Render overview page
     * 
     * @return \Pecee\Http\Response
     */
    public function overview() {

        $title = 'Super Admin Overview';
        $user = Auth::user();
        if ($user->role != "Admin") {
            return view('errors/404');
        }

        $widgets = $this->widgets($user);
        $plans = $this->plans($user);
        $companies = $this->companies($user);

        return view('admin-overview', compact("user","title","widgets","plans","companies"));

    }

    /**
     * Get subscription plan comparison
     * 
     * @return array
     */
    public function plans() {

        $plans = array();
        $pricingplans = Database::table('plans')->orderBy("id", false)->get();
        foreach ($pricingplans as $key => $pricingplan) {
            $plans["label"][] = $pricingplan->name;
            $plans["amount"][] = Database::table("companies")->where("subscription_status", "Active")->count("id", "total")[0]->total;
        }

        return ( object ) $plans;

    }

    /**
     * Companies List
     * 
     * @return array
     */
    public function companies() {

        $companies = Database::table('companies')->orderBy("id", false)->limit(10)->get();
        foreach ($companies as $key => $company) {
            if (!empty($company->subscription_plan)) {
                $company->plan = Database::table('plans')->where('id', $company->subscription_plan)->first();
            }
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
        $widgets["active"] = Database::table("companies")->where("subscription_status", "active")->where("on_trial", "No")->count("id", "total")[0]->total;

        // Free Trial
        $widgets["freetrial"] = Database::table("companies")->where("subscription_status", "active")->where("on_trial", "Yes")->count("id", "total")[0]->total;

        // Cancelled
        $widgets["cancelled"] = Database::table("companies")->where("subscription_cancelled", "Yes")->count("id", "total")[0]->total;

        // Dormant
        $widgets["dormant"] = Database::table("companies")->where("subscription_cancelled", "Yes")->orWhere("subscription_status","!=", "Active")->count("id", "total")[0]->total;

        // Total
        $widgets["total"] = Database::table("companies")->count("id", "total")[0]->total;

        return $widgets;

    }
    
}