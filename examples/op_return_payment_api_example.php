<?php

use BitWasp\BitcoinLib\RawTransaction;
use Blocktrail\SDK\Blocktrail;
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
    /** @var Wallet $wallet */
    list($wallet, $primaryMnemonic, $backupMnemonic, $blocktrailPublicKeys) = $client->createNewWallet([
        "identifier" => "example-wallet",
        "passphrase" => "example-strong-password",
        "key_index" => 9999
    ]);
    $wallet->doDiscovery();
}

// var_dump($wallet->deleteWebhook());
// var_dump($wallet->setupWebhook("http://www.example.com/wallet/webhook/example-wallet"));

// $utxos = $wallet->utxos()['data'];
// $utxo = $utxos[array_rand($utxos)];

$txBuilder = new TransactionBuilder();
$txBuilder->addRecipient($wallet->getNewAddress(), Blocktrail::DUST + 1);
$txBuilder->addOpReturn("TO DAAAA MOOOON@!的");

$txBuilder = $wallet->coinSelectionForTxBuilder($txBuilder);

echo $wallet->sendTx($txBuilder);
