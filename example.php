<?php

require_once __DIR__ . "/vendor/autoload.php";

$client = new \BlockTrail\SDK\APIClient("MYKEY", "MYSECRET");
$client->setCurlDebugging();

//GET request
echo "\n----------GET Test---------- \n";
$address = $client->address("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp");
var_dump($address['address'], $address['balance']);


//POST Request
echo "\n----------POST Test---------- \n";
$address = "16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z";
$signature = "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=";
$result = $client->verifyAddress($address, $signature);
var_dump($result);

