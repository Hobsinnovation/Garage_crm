<?php
namespace Simcify;

use Simcify\Database;
use \Stripe\Stripe;
use Simcify\Auth;

\Stripe\Stripe::setApiKey(env("STRIPE_SECRET_KEY"));

class Stripeapi {

    /**
     * Get stripe customer
     * 
     * @return array
     */
    public static function customer($user) {
    	if (!empty($user->stripe_id)) {
    		return $user->stripe_id;
    	}else{
    		return self::createcustomer($user);
    	}
    }

    /**
     * Create a stripe customer
     * 
     * @return array
     */
    public static function createcustomer($user) {
        
        try{
            $customer = \Stripe\Customer::create(array(
              "name" => $user->fname." ".$user->lname,
              "description" => $user->parent->name,
              "email" => $user->email
            ));
        }catch(\Exception $e){
            return response()->json(responder("error", "Hmmm!", $e->getMessage()));
        }

        Database::table('users')->where('id', $user->id)->update(array("stripe_id" => $customer->id));
        return $customer->id;

    }

    /**
     * Create a stripe card
     * 
     * @return array
     */
    public static function createcard($user, $customerid, $token) {

        try{
            $card = \Stripe\Customer::createSource(
              $customerid,
              array("source" => $token)
            );
        }catch(\Exception $e){
            return response()->json(responder("error", "Hmmm!", $e->getMessage()));
        }
        
        Database::table('users')->where('id', $user->id)->update(array("stripe_card" => $card->id, "stripe_card_last4" => $card->last4));
        return $card;

    }

    /**
     * Create a stripe charge
     * 
     * @return array
     */
    public static function charge($customer, $amount, $description) {
        try{
            $charge = \Stripe\Charge::create(array(
              "amount" => round($amount * 100),
              "currency" => strtolower(env("SUBSCRIPTION_CURRENCY")),
              "customer" => $customer, 
              "description" => $description
            ));
            
        }catch(\Exception $e){
            return response()->json(responder("error", "Hmmm!", $e->getMessage()));
        }
        
        return $charge;

    }


}