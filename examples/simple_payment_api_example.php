<?php

use Afk11\Bitcoin\Transaction\TransactionFactory;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');
// $client->setVerboseErrors();
// $client->setCurlDebugging();

$raw = "0100000001c9a2d5381a059bbab241524d69e28b76c8083c5f46000d268ee2e52686061d5d01000000fdfe0000493046022100d231e144ad82f009eae982df1e44f407dad98aac614e970565bd632cd081388e022100d3c138566c2f67162f8f250b26ac928b104272a5efee75818a48d7004da7d18b0147304402201e4106936aaf8e2582402d59f95cb3fa69a72fd812042cce7a54c5cc81dd7e9c022079dac8f738c676abbf84f1ad21c215b7782296bdf45f1a41a2c69b52e3039d51014c695221024882ca54cd89c1f14aea2c843fa0109f6339bd4df166a12454d195fefb9e84922102e04b69fe7139498cd99ae410f07d781900357f0b3b1ccaf997b2c9b1e7c185a82103ea5042dd903e5d717682ec9a5071f1516bf2cd6096c31f49b0d6c25ad9326ad853aeffffffff02a0e7be040000000017a9143409969e2d14b4694eb1188028e615353740273c87a08601000000000017a9141d6fcb721f40cac5b0f53f7f4924f4f20aee9cb78700000000";
var_dump($raw);
var_dump(TransactionFactory::fromHex($raw)->getTransactionId());
die();

/**
 * @var $wallet             \Blocktrail\SDK\WalletInterface
 * @var $backupMnemonic     string
 */
try {
    $wallet = $client->initWallet([
        "identifier" => "example-wallet",
        "passphrase" => "example-strong-password"
    ]);
} catch (ObjectNotFound $e) {
    list($wallet, $primaryMnemonic, $backupMnemonic, $blocktrailPublicKeys) = $client->createNewWallet([
        "identifier" => "example-wallet",
        "passphrase" => "example-strong-password",
        "key_index" => 9999
    ]);
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
