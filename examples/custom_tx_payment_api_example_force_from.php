<?php

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Script\ScriptFactory;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\TransactionBuilder;
use Blocktrail\SDK\UTXO;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletInterface;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, "v1");

/**
 * @var $wallet             Wallet
 * @var $backupMnemonic     string
 */
try {
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

// send info
$amount = BlocktrailSDK::toSatoshi(0.001);
$paymentAddress = $wallet->getNewAddress();

// address to force sending from
$myAddress = "2MxoF6N1g6p8SgWrEYBM2DtH5MQEy3zpiF9";

// fetch dynamic fees
$optimalFeePerKB = $wallet->getOptimalFeePerKB();
$lowPriorityFeePerKB = $wallet->getLowPriorityFeePerKB();

// setup txbuilder
$txBuilder = new TransactionBuilder();
// set send info
$txBuilder->addRecipient($paymentAddress, $amount);
// set change address to our sending address
$txBuilder->setChangeAddress($myAddress);

// fetch UTXOs for our sending address
$utxos = $client->addressUnspentOutputs($myAddress);
if (count($utxos['data']) == 0) {
    throw new \Exception("No UTXOs for address");
}

// add UTXOs to txbuilder
foreach ($utxos['data'] as $utxo) {
    $scriptPubKey = ScriptFactory::fromHex($utxo['script_hex']);
    $address = AddressFactory::fromString($utxo['address']);
    $path = $wallet->getPathForAddress($address->getAddress());
    $redeemScript = $wallet->getRedeemScriptByPath($path);
    $txBuilder->spendOutput($utxo['hash'], $utxo['index'], $utxo['value'], $address, $scriptPubKey, $path, $redeemScript);
}

// determine fee
list($fee, $change) = $wallet->determineFeeAndChange($txBuilder, $optimalFeePerKB, $lowPriorityFeePerKB);
$txBuilder->setFee($fee);

// remove any UTXOs we don't need to minize the fee of the TX
$outsum = array_sum(array_column($txBuilder->getOutputs(), 'value')) + $fee;
$utxosum = array_sum(array_map(function(UTXO $utxo) { return $utxo->value; }, $txBuilder->getUtxos()));
$utxos = $txBuilder->getUtxos();
foreach ($utxos as $idx => $utxo) {
    if ($utxosum - $utxo->value >= $outsum) {
        unset($utxos[$idx]);
        $utxosum -= $utxo->value;
    }
}
$txBuilder->setUtxos(array_values($utxos)); // array_values to reset indexes

// update fee after we reduced the UTXOs
list($fee, $change) = $wallet->determineFeeAndChange($txBuilder, $optimalFeePerKB, $lowPriorityFeePerKB);
$txBuilder->setFee($fee);

// send TX
var_dump($wallet->sendTx($txBuilder, false));
