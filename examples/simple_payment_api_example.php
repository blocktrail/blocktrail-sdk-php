<?php

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');
// $client->setVerboseErrors();
// $client->setCurlDebugging();

/**
 * @var $wallet             \Blocktrail\SDK\WalletInterface
 * @var $backupMnemonic     string
 */
try {
    $wallet = $client->initWallet("example-wallet", "example-strong-password");
} catch (ObjectNotFound $e) {
    list($wallet, $primaryMnemonic, $backupMnemonic, $blocktrailPublicKeys) = $client->createNewWallet("example-wallet", "example-strong-password", $_account=9999);
    $wallet->doDiscovery();
}

// var_dump($wallet->deleteWebhook());
// var_dump($wallet->setupWebhook("http://www.example.com/wallet/webhook/example-wallet"));

// print some new addresses
var_dump($wallet->getAddressByPath("M/9999'/0/0"), $wallet->getAddressByPath("M/9999'/0/1"));

// print the balance
list($confirmed, $unconfirmed) = $wallet->getBalance();
var_dump($confirmed, BlocktrailSDK::toBTC($confirmed));
var_dump($unconfirmed, BlocktrailSDK::toBTC($unconfirmed));

// send a payment (will fail unless you've send some BTC to an address part of this wallet)
$addr = $wallet->getNewAddress();
var_dump($wallet->pay([
    $addr => BlocktrailSDK::toSatoshi(0.001),
]));
