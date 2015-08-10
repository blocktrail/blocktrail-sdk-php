<?php

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

/*
 * custom created TX using the TransactionBuilder class,
 *  in this example we substract the fee required from the amount send
 *  and the change is then automatically properly updated as well
 */

$amount = BlocktrailSDK::toSatoshi(0.001);
$paymentAddress = $wallet->getNewAddress();

// uncomment to create a couple of 0.001 BTC UTXOs (and 1 big output remains)
//  after doing this run the example twice and the 2nd time around you can see it only using a single UTXO
//var_dump($wallet->pay([$paymentAddress => $wallet->getMaxSpendable()['max']]));
//var_dump($wallet->pay([
//    $wallet->getNewAddress() => $amount,
//    $wallet->getNewAddress() => $amount,
//    $wallet->getNewAddress() => $amount,
//    $wallet->getNewAddress() => $amount,
//    $wallet->getNewAddress() => $amount,
//    $wallet->getNewAddress() => $amount,
//    $wallet->getNewAddress() => $amount,
//]));
//exit(1);

$optimalFeePerKB = $wallet->getOptimalFeePerKB();
$lowPriorityFeePerKB = $wallet->getLowPriorityFeePerKB();

$txBuilder = new TransactionBuilder();
$txBuilder->addRecipient($paymentAddress, $amount);

// get coinselection for the payment we want to make
$txBuilder = $wallet->coinSelectionForTxBuilder($txBuilder);

// debug info
echo "--------------------------------------------\n";
var_dump("utxos", $txBuilder->getUtxos());

echo "--------------------------------------------\n";
var_dump("outputs", $txBuilder->getOutputs());
list($fee, $change) = $wallet->determineFeeAndChange($txBuilder, $optimalFeePerKB, $lowPriorityFeePerKB);
var_dump("fee", $fee);
var_dump("change", $change);

// set the fee and update the output value
$txBuilder->setFee($fee);
$txBuilder->updateOutputValue(0, $amount - $fee);

// EXTRA: remove any UTXOs we don't need to minize the fee of the TX
$outsum = array_sum(array_column($txBuilder->getOutputs(), 'value')) + $fee;
$utxosum = array_sum(array_map(function(UTXO $utxo) { return $utxo->value; }, $txBuilder->getUtxos()));
$utxos = $txBuilder->getUtxos();
foreach ($utxos as $idx => $utxo) {
    if ($utxosum - $utxo->value >= $outsum) {
        unset($utxos[$idx]);
        $utxosum -= $utxo->value;
    }
}
$txBuilder->setUtxos(array_values($utxos)); // set updated list of UTXOs, array_values to reset indexes


// more debug info
echo "--------------------------------------------\n";
var_dump("utxos", $txBuilder->getUtxos());

echo "--------------------------------------------\n";
var_dump("outputs", $txBuilder->getOutputs());
list($fee, $change) = $wallet->determineFeeAndChange($txBuilder, $optimalFeePerKB, $lowPriorityFeePerKB);
var_dump("fee", $fee);
var_dump("change", $change);

// send TX
var_dump($wallet->sendTx($txBuilder, false));
