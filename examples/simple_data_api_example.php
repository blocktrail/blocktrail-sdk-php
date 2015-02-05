<?php

use Blocktrail\SDK\BlocktrailSDK;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK("YOUR_APIKEY_HERE", "YOUR_APISECRET_HERE");
// $client->setCurlDebugging();

// GET request
echo "\n -- Get Address Data -- \n";
$address = $client->address("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp");
var_dump($address['address'], $address['balance'], BlocktrailSDK::toBTC($address['balance']));


// POST Request
echo "\n -- Verify Address -- \n";
$address = "16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z";
$signature = "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=";
$result = $client->verifyAddress($address, $signature);
var_dump($result);

// Dealing with numbers
echo "\n -- 123456789 Satoshi to BTC -- \n";
var_dump(BlocktrailSDK::toBTC(123456789));
echo "\n -- 1.23456789 BTC to Satoshi -- \n";
var_dump(BlocktrailSDK::toSatoshi(1.23456789));
