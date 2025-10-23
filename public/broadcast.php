<?php
use App\Libraries\TestEcho;

$message = $_GET['message'];
//echo $message;

$api = new TestEcho();
echo $api->SendAll($message);