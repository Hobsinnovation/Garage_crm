<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

include_once 'vendor/autoload.php';

use Simcify\Application;
use Simcify\Database;
use Simcify\Stripeapi;
use Simcify\Asilify;
use Simcify\Auth;
use Simcify\Mail;
use Simcify\Sms;


$app = new Application();

$today = date("Y-m-d");


/**
 * Send queued messages
 * 
 */
$messages = Database::table("messages")->where("status", "Queued")->orderBy("id", false)->get();
if (!empty($messages)) {
    foreach ($messages as $key => $message) {
        
        $data = array();
        
        $company = Database::table("companies")->where("id", $message->company)->first();
        if ($company->sms_balance <= 0) {
            Database::table('messages')->where("id", $message->id)->update(array(
                "status" => "Failed"
            ));
            
            continue;
        }
        
        $response = Sms::africastalking($message->phonenumber, $message->message);
        
        if ($response->status == "success") {
            $data["status"]       = "Sent";
            $data["messageId"]    = $response->data->SMSMessageData->Recipients[0]->messageId;
            $data["messageParts"] = $response->data->SMSMessageData->Recipients[0]->messageParts;
            $data["cost"]         = str_replace("KES ", "", $response->data->SMSMessageData->Recipients[0]->cost);
            
            Asilify::smsbalance($message->company, $data["cost"]);
        } else {
            $data["status"] = "Failed";
        }
        
        Database::table('messages')->where("id", $message->id)->update($data);
        
    }
    
}

/**
 * Mark sending campaign as complete when completed
 * 
 */
$campaigns = Database::table("campaigns")->where("status", "Queued")->orderBy("id", false)->get();
if (!empty($campaigns)) {
    foreach ($campaigns as $key => $campaign) {
        $queuedmessages = Database::table("messages")->where("campaign", $campaign->id)->where("status", "Queued")->count("id", "total")[0]->total;
        if ($queuedmessages == 0) {
            Database::table('campaigns')->where("id", $campaign->id)->update(array(
                "status" => "Completed"
            ));
        }
    }
}



/**
 * Mark cal
 * 
 */
$companies = Database::table("companies")->where("subscription_status", "Active")->where("subscription_end", "<=", $today)->orWhere("subscription_status", "Active")->orwhere("on_trial", "Yes")->where("subscription_status", "<=", $today)->orWhere("subscription_status", "Active")->orwhere("subscription_cancelled", "Yes")->where("subscription_end", "<=", $today)->get();
if (!empty($companies)) {
    foreach ($companies as $key => $company) {
        
        // Cancelled
        if ($company->subscription_cancelled == "Yes") {
            Database::table('companies')->where("id", $company->id)->update(array(
                "subscription_status" => "Cancelled"
            ));
            continue;
        }
        
        if (empty($company->subscription_plan)) {
            Database::table('companies')->where("id", $company->id)->update(array(
                "subscription_status" => "Expired",
                "subscription_cancelled" => "No"
            ));
            continue;
        }
        
        
        $plan = Database::table('plans')->where('id', $company->subscription_plan)->first();
        if ($company->subscription_cycle == "Monthly") {
            $plan->price = $plan->monthly_fee;
        } else {
            $plan->price = $plan->annual_fee;
        }
        $description = "Payment for " . $plan->name . " subscription.";
        $charge      = Stripeapi::charge($company->subscription_stripe, $plan->price, $description);
        if ($charge["status"] != "succeeded") {
            Database::table('companies')->where("id", $company->id)->update(array(
                "subscription_status" => "Expired",
                "subscription_cancelled" => "No"
            ));
            continue;
        }
        
        $data = array(
            "company" => $company->id,
            "paid_by" => $company->name,
            "amount" => $plan->price,
            "card_last4" => $charge["payment_method_details"]["card"]["last4"],
            "description" => escape($description),
            "currency" => env("SUBSCRIPTION_CURRENCY")
        );
        Database::table('payments')->insert($data);
        
        // Free Trials
        if ($company->on_trial == "Yes") {
            $data = array(
                "on_trial" => "No"
            );
            Database::table('companies')->where("id", $company->id)->update($data);
            continue;
        }
        
        $data = array(
            "subscription_status" => "Active",
            "subscription_cancelled" => "No",
            "on_trial" => "No"
        );
        
        $data["subscription_start"] = date("Y-m-d");
        if ($company->subscription_cycle == "Monthly") {
            $data["subscription_end"] = date('Y-m-d', strtotime(date("Y-m-d") . ' + 30 days'));
        } else {
            $data["subscription_end"] = date('Y-m-d', strtotime(date("Y-m-d") . ' + 365 days'));
        }
        
        Database::table('companies')->where("id", $company->id)->update($data);
        
    }
}