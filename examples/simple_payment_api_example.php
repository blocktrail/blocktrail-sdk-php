<?php

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');
// $client->setVerboseErrors();
// $client->setCurlDebugging();

/**
 * @var $wallet             \Blocktrail\SDK\Wallet
 * @var $backupMnemonic     string
 */
try {
    $wallet = $client->initWallet("example-wallet", "example-strong-password");
} catch (ObjectNotFound $e) {
    list($wallet, $backupMnemonic) = $client->createNewWallet("example-wallet", "example-strong-password");
}

// print some new addresses
var_dump($wallet->getNewAddress(), $wallet->getNewAddress());

// print the balance
list($confirmed, $unconfirmed) = $wallet->getBalance();
var_dump($confirmed, BlocktrailSDK::toBTC($confirmed));
var_dump($unconfirmed, BlocktrailSDK::toBTC($unconfirmed));

// send a payment (will fail unless you've send some BTC to an address part of this wallet)
var_dump($wallet->pay([
    "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy" => BlocktrailSDK::toSatoshi(0.001)
]));
