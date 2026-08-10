<?php
namespace Simcify\Controllers;

use Simcify\Database;
use Simcify\Stripeapi;
use Simcify\Asilify;
use \Stripe\Stripe;
use Simcify\Auth;
use Simcify\Sms;

\Stripe\Stripe::setApiKey(env("STRIPE_SECRET_KEY"));

class Billing {
    
    /**
     * Render billing page
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {

    	$user = Auth::user();
    	$title = "Billing";
        
      if ($user->role == "Admin" || $user->role == "Staff" || $user->role == "Inventory Manager" || $user->role == "Booking Manager") {
          return view('errors/404');
      }

      $admin = Database::table('companies')->where('admin', "Yes")->first();
      if (!empty($admin)) {
        $user->supportemail = $admin->email;
      }else{
        $user->supportemail = "";
      }
      
      if (!empty($user->parent->subscription_plan)) {
        $subscription_plan = Database::table('plans')->where('id', $user->parent->subscription_plan)->first();
      }else{
        $subscription_plan = NULL;
      }
    	$payments = Database::table('payments')->where('company', $user->company)->orderBy("id", false)->limit(3)->get();
      $plans = Database::table('plans')->orderBy("id", false)->get();

        return view("billing", compact("user", "title", "payments","plans","subscription_plan"));

    }
    
    /**
     * Render billing page
     * 
     * @return \Pecee\Http\Response
     */
    public function payments() {

    	$user = Auth::user();
    	$title = "Billing Payments";

    	$payments = Database::table('payments')->where('company', $user->company)->orderBy("id", false)->get();

        return view("billing-payments", compact("user", "title", "payments"));

    }
    
    /**
     * Checkout form
     * 
     * @return \Pecee\Http\Response
     */
    public function checkoutform() {

      $user = Auth::user();
      $plan = Database::table('plans')->where('id', input("planid"))->first();

      $payload = ( object ) $_POST;

        return view("modals/checkout", compact("user","plan","payload"));

    }
    
    /**
     * Checkout
     * 
     * @return Json
     */
    public function checkout() {

      $user = Auth::user();
      $plan = Database::table('plans')->where('id', input("planid"))->first();
      $customer = Stripeapi::customer($user);
      $card = Stripeapi::createcard($user, $customer, input("token"));
      $plan->cycle = input("cycle");

      if (empty($plan->trial_days)) {
        if ($plan->cycle == "Monthly") {
          $plan->price = $plan->monthly_fee;
        }else{
          $plan->price = $plan->annual_fee;
        }

        $description = "Payment for ".$plan->name." subscription.";
        $charge = Stripeapi::charge($customer, $plan->price, $description);
        if ($charge["status"] != "succeeded") {
          return response()->json(responder("error", "Oops", "Payment failed, please try again."));
        }

        $data = array(
          "company" => $user->company,
          "paid_by" => $user->fname." ".$user->lname,
          "amount" => $plan->price,
          "card_last4" => $card->last4,
          "description" => escape($description),
          "currency" => env("SUBSCRIPTION_CURRENCY"),
        );
        Database::table('payments')->insert($data);
      }

      $data = array(
        "subscription_plan" => $plan->id,
        "subscription_stripe" => $customer,
        "subscription_status" => "Active",
        "subscription_cycle" => $plan->cycle,
        "subscription_cancelled" => "No",
      );
      if (!empty($plan->trial_days)) {
        $data["on_trial"] = "Yes";
        $data["subscription_start"] = date("Y-m-d", strtotime(date("Y-m-d"). ' + '.$plan->trial_days.' days'));
        if ($plan->cycle == "Monthly") {
          $data["subscription_end"] = date('Y-m-d', strtotime(date("Y-m-d"). ' + '.(30 + $plan->trial_days).' days'));
        }else{
          $data["subscription_end"] = date('Y-m-d', strtotime(date("Y-m-d"). ' + '.(365 + $plan->trial_days).' days'));
        }
      }else{
        $data["on_trial"] = "No";
        $data["subscription_start"] = date("Y-m-d");
        if ($plan->cycle == "Monthly") {
          $data["subscription_end"] = date('Y-m-d', strtotime(date("Y-m-d"). ' + 30 days'));
        }else{
          $data["subscription_end"] = date('Y-m-d', strtotime(date("Y-m-d"). ' + 365 days'));
        }

      }

      Database::table('companies')->where("id", $user->company)->update($data);
      if (!empty($plan->trial_days)) {
        return response()->json(responder("success", "Hooray!", "Your free trial has now started.","redirect('".url("Billing@get")."', true)"));
      }else{
        return response()->json(responder("success", "Hooray!", "You're now subscribed.","redirect('".url("Billing@get")."', true)"));
      }

    }
    
    /**
     * Cancel a subscription plan
     * 
     * @return \Pecee\Http\Response
     */
    public function cancel() {

      $user = Auth::user();

      if (!hash_compare($user->password, Auth::password(input("password")))) {
        return response()->json(responder("error", "Oops", "You have entered an incorrect password."));
      }

      $company = array(
          "subscription_cancelled" => "Yes"
      );

      Database::table("companies")->where("id", $user->company)->update($company);

      return response()->json(responder("success", "Subscription cancelled!", "Your subscription is successfully cancelled. You will still have access to the access until you current subscription ends","reload()"));

    }
    
    /**
     * Verify a transaction
     * 
     * @return \Pecee\Http\Response
     */
    public function verify($type) {

    	$user = Auth::user();

    	if (isset($_GET["status"]) && $_GET["status"] == "successful" && isset($_GET["tx_ref"]) &&  !empty($_GET["tx_ref"])) {

    		$response =Asilify::transaction($_GET["tx_ref"]);

    		$payment = Database::table("payments")->where("txid", $response["txid"])->first();
    		if (!empty($payment)) {
    			if ($type == "topup" || $type == "t") {
	    			redirect(url("Marketing@get")."?payment=error");
	    		}elseif ($type == "subscription" || $type == "s") {
                    redirect(url("Billing@get")."?payment=error");
                }
    		}

    		if ($response["status"] == "success") {

	    		$response["type"] = $type;
	    		$response["company"] = $user->company;
	    		$response["user"] = $user->id;
	    		
	    		$company = Database::table("companies")->where("id", $user->company)->first();

	    		if ($type == "topup" || $type == "t") {

	    			$balance = $response["amount"] + $company->sms_balance;
		            Database::table("companies")->where("id", $user->company)->update(array(
		                "sms_balance" => $balance
		            ));
	    		}elseif ($type == "subscription" || $type == "s") {
                    $subscription =Asilify::subscription($response["txid"]);

                    if (empty($subscription)) {
                        redirect(url("Billing@get")."?payment=error");
                    }else{
                        $subscription = $subscription[0];

                        $plan = Asilify::plan($response["amount"]);
                        $period = Asilify::period($company, $plan["cycle"]);

                        $update = array(
                            "subscription_status" => "Active",
                            "subscription_start" => $period['start'],
                            "subscription_end" => $period['end'],
                            "subscription_plan" => $plan["name"]
                        );

                        $response["bill_for"] = $plan["name"]." Subscrition";
                        $response["subscription_plan"] = $plan["cycle"];

                        Database::table("companies")->where("id", $user->company)->update($update);

                    }
                }

    			Database::table("payments")->insert($response);

				if ($type == "topup" || $type == "t") {
	    			redirect(url("Marketing@get")."?payment=success");
	    		}elseif ($type == "subscription" || $type == "s") {
                    redirect(url("Billing@get")."?payment=success");
                }

    		}

    	}else{
			if ($type == "topup" || $type == "t") {
    			redirect(url("Marketing@get")."?payment=error");
    		}elseif ($type == "subscription" || $type == "s") {
                redirect(url("Billing@get")."?payment=error");
            }
    	}

		die();

    }

}