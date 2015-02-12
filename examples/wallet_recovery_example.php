<?php

use Blocktrail\SDK\Services\BlocktrailBitcoinService;
use Blocktrail\SDK\WalletSweeper;

require_once __DIR__ . "/../vendor/autoload.php";


//Process can take a long time - disable php execution time limit
set_time_limit(0);

//the primary mnemonic, obtained from our backup pdf
$primaryMnemonic    = "craft scatter train family grass bind sense double sample raven luxury
vacant north limit swamp scatter warm core enable exist limit genius knee
clutch document metal decline shaft canal utility powder earth later
symptom movie pluck traffic ozone power evoke spread gym habit legal cruel
uniform fold high";

//our wallet passphrase
$primaryPassphrase  = "example-strong-password";

//the primary mnemonic, obtained from our backup pdf
$backupMnemonic     = "evil pill forum vault pelican method clever dwarf hurt morning survey core
teach culture parade slam fee where flower climb silk piano ribbon pelican
candy genius battle entire lawn topic embark scheme heavy tackle proof
solution mimic crop average drama jar lava announce toy copper maid reduce
hand";

//the blocktrail keys used by this wallet, obtained from the backup pdf
$blocktrailKeys = array(
    [
        'keyIndex'=> 0,
        'path' => "M/0'",
        'pubkey' => 'tpubD8UrAbbGkiJUmxDS9UxC6bvSGVd1vAEDMMkMBHTJ7xMMnkNuvBsVMQv6fXxAQgV3aaETetdaBBNQgULBzebM86MyYP526Ggqu8K8jPwBdP4',
    ],
    [
    'keyIndex'=> 9999,
    'path' => "M/9999'",
    'pubkey' => 'tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ',
    ]
);

//an instance of the bitcoin data service we want to use to search addresses for unspent outputs
$bitcoinClient = new BlocktrailBitcoinService("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');

//create instance of sweeper - will automatically create primary keys from mnemonics
$walletSweeper = new WalletSweeper($primaryMnemonic, $primaryPassphrase, $backupMnemonic, $blocktrailKeys, $bitcoinClient, 'btc', $_testnet=true);
$walletSweeper->enableLogging();    //enable logging for more info

//Do wallet fund discovery - can be run separately from sweeping
//var_dump($walletSweeper->discoverWalletFunds());

//Do wallet fund discovery and sweeping - if successful you will be returned a signed transaction ready to submit to the network
$receivingAddress = "2NCcm7hJfJ5wk6GyKvT2ZHCrNsBgjBv2MSF";
$result = $walletSweeper->sweepWallet($receivingAddress);
var_dump($result);
