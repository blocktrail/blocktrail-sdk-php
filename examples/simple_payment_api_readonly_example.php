<?php

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletInterface;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');
// $client->setVerboseErrors();
// $client->setCurlDebugging();

/**
 * @var $wallet             \Blocktrail\SDK\WalletInterface
 * @var $backupMnemonic     string
 */
try {
    /** @var Wallet $wallet */
    $wallet = $client->initWallet([
        "identifier" => "example-wallet",
        "readonly" => true
    ]);
} catch (ObjectNotFound $e) {
    list($wallet, $primaryMnemonic, $backupMnemonic, $blocktrailPublicKeys) = $client->createNewWallet([
        "identifier" => "example-wallet",
        "passphrase" => "example-strong-password",
        "key_index" => 9999
    ]);
    $wallet->doDiscovery();
    $wallet->lock();
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

// unlock with 2nd argument a callable, wallet will only be unlocked within that callable
$wallet->unlock(["passphrase" => "example-strong-password"], function(Wallet $wallet) use($addr) {
    var_dump($wallet->pay([
        $addr => BlocktrailSDK::toSatoshi(0.001),
    ]));
});

// wallet is locked again after callable is done
var_dump($wallet->isLocked());

// unlock without callable, won't be locked until ->lock is called
$wallet->unlock(["passphrase" => "example-strong-password"]);

var_dump($wallet->pay([
    $addr => BlocktrailSDK::toSatoshi(0.001),
]));

// wallet still unlocked!
var_dump($wallet->isLocked());
