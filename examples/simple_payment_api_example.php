<?php

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;

require_once __DIR__ . "/../vendor/autoload.php";

$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true, 'v1');
$client->setVerboseErrors();
// $client->setCurlDebugging();

/**
 * @var $wallet             \Blocktrail\SDK\Wallet
 * @var $backupMnemonic     string
 */
try {
    $wallet = $client->initWallet("rubensayshi3", "password");
} catch (ObjectNotFound $e) {
    list($wallet, $backupMnemonic) = $client->createNewWallet("rubensayshi3", "password", 9999); // 9999 = use blocktrail development key
    // var_dump($wallet->doDiscovery());
    var_dump($wallet->getNewAddress(), $wallet->getNewAddress(), $wallet->getNewAddress(), $wallet->getNewAddress());
    /*
        string(35) "2N31kiJ7JR7VoMf1La3xyNHMPni2Xh33FK7"
        string(35) "2N3F17VrrVxvvb1x1K4zfwLaYuebCf9ZtHp"
        string(35) "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy"
        string(35) "2N4xQKufeRWx7n15FPE8ziJrjfrKe3Gw3TT"
     */
}

list($confirmed, $unconfirmed) = $wallet->getBalance();
var_dump($confirmed, BlocktrailSDK::toBTC($confirmed));
var_dump($unconfirmed, BlocktrailSDK::toBTC($unconfirmed));

var_dump($wallet->pay([
    "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy" => BlocktrailSDK::toSatoshi(0.001)
]));
