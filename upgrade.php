<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

include_once 'vendor/autoload.php';

use Simcify\Application;
use Simcify\Database;
use DotEnvWriter\DotEnvWriter;

$app = new Application();


/**
 * New env fields
 * 
 */
$env = new DotEnvWriter(".env");
$env->castBooleans();
$options = array("SMS_PROVIDER","TWILIO_SID","TWILIO_AUTHTOKEN","TWILIO_PHONENUMBER","STRIPE_PUBLISHABLE_KEY","STRIPE_SECRET_KEY","SUBSCRIPTION_CURRENCY","DB_VERSION");
foreach ($options as $option) {
    if (empty(env($option))) {
        if ($option == "SUBSCRIPTION_CURRENCY") {
            $env->set($option, "USD");
        }else{
            $env->set($option, "");
        }
    }
}
$env->save();



/**
 * Database upgrade
 * 
 */
$version = env("DB_VERSION");
if (empty($version)) {
    $version = 1;
}
$changes = file_get_contents(config("app.storage")."app/databasechanges.json");
$changes = json_decode($changes, true);
if ($version < count(( array ) $changes)) {
    for ($version; $version <= count(( array ) $changes); $version++) {
        if (!empty($changes["v".$version])) {
            $commands = $changes["v".$version];
            foreach ($commands as $key => $command) {
                Database::table("table")->command($command);
            }
        }
        $env->set("DB_VERSION", $version);
        $env->save();
    }
}

echo "Upgrade complete!";
die();





