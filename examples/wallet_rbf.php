<?php

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\TransactionBuilder;
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
        "passphrase" => "example-strong-password"
    ]);
} catch (ObjectNotFound $e) {
    list($wallet, $backupInfo) = $client->createNewWallet([
        "identifier" => "example-wallet",
        "passphrase" => "example-strong-password",
        "key_index" => 9999
    ]);
    $wallet->doDiscovery();
}

$utxos = $wallet->utxos()['data'];
$utxos = array_values(array_filter($utxos, function($utxo) { return $utxo['value'] >= BlocktrailSDK::toSatoshi(0.0001); }));
$utxo1 = $utxos[0];
$utxo2 = $utxos[1];

$addr = $utxo1['address'];
$path = $utxo1['path'];
$val = $utxo1['value'];

// setup txbuilder
$txBuilder = new TransactionBuilder();

// set change address to our address
$txBuilder->setChangeAddress($addr);

$address = AddressFactory::fromString($addr);
$scriptPubKey = $address->getScriptPubKey();
$redeemScript = $wallet->getRedeemScriptByPath($path)[1];
$txBuilder->spendOutput($utxo1['hash'], $utxo1['idx'], $val, $address, $scriptPubKey, $path, $redeemScript);
$txBuilder->getUtxos()[0]->sequence = TransactionInput::SEQUENCE_FINAL - 2;

// send TX
var_dump($wallet->sendTx($txBuilder, false, true, true));


$addr = $utxo2['address'];
$path = $utxo2['path'];
$val = $utxo2['value'];

$address = AddressFactory::fromString($addr);
$scriptPubKey = $address->getScriptPubKey();
$redeemScript = $wallet->getRedeemScriptByPath($path)[1];
$txBuilder->spendOutput($utxo2['hash'], $utxo2['idx'], $val, $address, $scriptPubKey, $path, $redeemScript);
$txBuilder->getUtxos()[0]->sequence = TransactionInput::SEQUENCE_FINAL - 2;

// send TX
var_dump($wallet->sendTx($txBuilder, false, false, true));

