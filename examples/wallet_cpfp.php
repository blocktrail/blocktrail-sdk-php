<?php

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Script\ScriptFactory;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\TransactionBuilder;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletInterface;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');

/** @var Wallet $wallet */
$wallet = $client->initWallet([
    "identifier" => "example-wallet",
    "passphrase" => "example-strong-password"
]);

// fetch dynamic fees
$optimalFeePerKB = $wallet->getOptimalFeePerKB();
$lowPriorityFeePerKB = $wallet->getLowPriorityFeePerKB();

$txs = $wallet->transactions(1, 200, 'desc');
$txs = array_filter($txs['data'], function($tx) {
    return $tx['confirmations'] === 0;
});

if (!count($txs)) {
    throw new \Exception("No unconfirmed TXs for this wallet");
}

var_dump("unconfirmed txs: " . count($txs));

$totalSize = array_sum(array_map(function($tx) {
    return Wallet::estimateSize(Wallet::estimateSizeUTXOs(count($tx['inputs'])), Wallet::estimateSizeOutputs(count($tx['outputs'])));
}, $txs));
$totalFee = array_sum(array_map(function($tx) { return $tx['total_fee']; }, $txs));

var_dump("size: " . $totalSize, "fee paid " . BlocktrailSDK::toBTC($totalFee));

$desiredFee = (int)ceil(($totalSize / 1000) * $optimalFeePerKB);
$desiredFee = (int)ceil($desiredFee * 1.2); // add extra 20% for margin
$missingFee = $desiredFee - $totalFee;

var_dump("desired fee: " . BlocktrailSDK::toBTC($desiredFee));
var_dump("missing fee: " . BlocktrailSDK::toBTC($missingFee));

if ($missingFee <= 0.001 * 1e8) {
    throw new \Exception("No missing fees");
}

// send info
$myAddress = $wallet->getNewAddress();

// setup txbuilder
$txBuilder = new TransactionBuilder();

// set change address to our address
$txBuilder->setChangeAddress($myAddress);

// fetch UTXOs for our sending address
$utxos = $wallet->utxos(1, 200, 'asc');

$utxos = array_filter($utxos['data'], function($utxo) {
    return $utxo['confirmations'] === 0;
});

var_dump("utxos: " . count($utxos));
if (count($utxos) == 0) {
    throw new \Exception("No unconfirmed UTXOs for this wallet");
}

$txSize = Wallet::estimateSize(Wallet::estimateSizeUTXOs(count($utxos)), Wallet::estimateSizeOutputs(1));
$txFee = (int)ceil($txSize / 1000 * $optimalFeePerKB);

$cpfpFee = $txFee + $missingFee;

var_dump("tx fee: " . BlocktrailSDK::toBTC($txFee));
var_dump("cpfp fee: " . BlocktrailSDK::toBTC($cpfpFee));

$continue = readline("continue? [y/N]");

if (!in_array(strtolower($continue ?: ""), ['y', 'yes'])) {
    exit(0);
}


// add UTXOs to txbuilder
foreach ($utxos as $utxo) {
    $scriptPubKey = ScriptFactory::fromHex($utxo['scriptpubkey_hex']);
    $address = AddressFactory::fromString($utxo['address']);
    $path = $wallet->getPathForAddress($address->getAddress());
    $redeemScript = $wallet->getRedeemScriptByPath($path);
    $txBuilder->spendOutput($utxo['hash'], $utxo['idx'], $utxo['value'], $address, $scriptPubKey, $path, $redeemScript);
}

// set fixed fee
$txBuilder->setFee($cpfpFee);

// determine change
list($fee, $change) = $wallet->determineFeeAndChange($txBuilder, $optimalFeePerKB, $lowPriorityFeePerKB);

// send TX
var_dump($wallet->sendTx($txBuilder, false));
