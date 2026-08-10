<?php

// Buffer the whole request from the first byte. PDF endpoints can then
// discard any warning/notice output before sending the binary PDF stream.
if (ob_get_level() === 0) { ob_start(); }

ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

include_once 'vendor/autoload.php';

use Simcify\Application;

$app = new Application();
$app->route();
