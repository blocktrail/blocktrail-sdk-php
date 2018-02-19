<?php

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletInterface;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK(getenv('BLOCKTRAIL_SDK_APIKEY') ?: "MY_APIKEY", getenv('BLOCKTRAIL_SDK_APISECRET') ?: "MY_APISECRET", "BTC", true /* testnet */, 'v1');

// initWallet or createNewWallet if initWallte fails cuz not found
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
}


$sort = 'desc';
$limit = 50;
$page = 1;

// store lines of CSV in here as arrays
$lines = [];
// init the headers of the CSV
$lines[] = [
    'hash',
    'time',
    'incoming',
    'fee_paid',
    'balance_diff',
];

// loop to get all TXs
do {
    $transactions = $wallet->transactions($page, $limit, $sort);
    $transactions = $transactions['data'];

    foreach ($transactions as $transaction) {
        // skip unconfirmed
        if ($transaction['confirmations'] === 0) {
            continue;
        }

        // if balance diff > 0 then it's an incoming tx
        $incoming = $transaction['wallet']['balance'] > 0;

        // add line for CSV output
        $lines[] = [
            $transaction['hash'],
            $transaction['time'],
            $incoming ? 'IN' : 'OUT',
            $incoming ? 0 : $transaction['total_fee'],
            $transaction['wallet']['balance'],
        ];
    }

    // increment page counter for next page
    $page += 1;

} while (count($transactions) === $limit);


// open in-memory file handler
$fp = fopen('php://memory', 'rw');

// write lines to file handler
foreach ($lines as $line) {
    fputcsv($fp, $line);
}

// reset file handler to start
fseek($fp, 0);

// read all contents from file handler into var
$csv = stream_get_contents($fp);
// close file handler
fclose($fp);

// print CSV or do other stuff with it
echo $csv;
