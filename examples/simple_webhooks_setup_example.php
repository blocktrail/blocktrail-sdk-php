<?php

use Blocktrail\SDK\BlocktrailSDK;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');

$client->setupWebhook("http://rubensayshi.ngrok.com/webhook", "rubensayshi-ngrok");
$client->subscribeAddressTransactions("rubensayshi-ngrok", "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy");
