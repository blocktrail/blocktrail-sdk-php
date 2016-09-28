<?php

use Blocktrail\SDK\Services\BlocktrailBatchUnspentOutputFinder;
use Blocktrail\SDK\WalletV2Sweeper;

require_once __DIR__ . "/../vendor/autoload.php";

// Process can take a long time - disable php execution time limit
set_time_limit(0);

// our wallet passphrase
$passphrase = "example-strong-password";

// the 'Encrypted Primary Seed', obtained from our backup pdf (encoded as mnemonic)
$encryptedPrimarySeed = "fat arena brown skull echo quiz move screen obey merge human refuse item ocean during
spray tennis atom match moral degree spread lobster crucial amused window reward six tattoo strike assume 
tiger invest peace sustain gossip physical chunk vanish account pistol forget match suit twin dust combine 
search nurse foster kangaroo maze hybrid rabbit alcohol canal spray danger mosquito join oppose jealous tornado 
save hello young boring ten tell journey people ginger";

// the 'Password Encrypted Secret', obtained from our backup pdf (encoded as mnemonic)
// NOT TO BE CONFUSED WITH 'Encrypted Recovery Secret' !
$passwordEncryptedSecret = "fat arena brown skull echo quiz chapter cloud now dial vessel mail dinner radio
rookie report radio setup legal dolphin luxury unaware vast recall employ enemy lizard walnut smart ride pact
size sponsor grace canal taxi balance sudden throw will know pen intact shy inch idea caught average point
load summer differ calm scan remind carpet clerk pink someone defense regret eyebrow layer entry evil farm
emerge lazy panic inform shield fragile";

// the 'Backup Seed', obtained from our backup pdf (encoded as mnemonic)
$backupSeed = "rival damp cry cram foam hotel cry walk alien note attend year small fiber wide crucial
organ pull museum buyer swallow program cattle base happy dolphin course tiny narrow swarm false horse crunch 
employ toast palace sort poem tiger fine warfare toe mandate pulse city edit deer turkey";

// the blocktrail keys used by this wallet, obtained from the backup pdf
$blocktrailKeys = array(
    [
        'keyIndex'=> 0,
        'path' => "M/0'",
        'pubkey' => 'tpubD8UrAbbGkiJUmxDS9UxC6bvSGVd1vAEDMMkMBHTJ7xMMnkNuvBsVMQv6fXxAQgV3aaETetdaBBNQgULBzebM86MyYP526Ggqu8K8jPwBdP4',
    ],
    [
        // this one is only used for development, not for BlockTrail users
        'keyIndex'=> 9999,
        'path' => "M/9999'",
        'pubkey' => 'tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ',
    ]
);

// an instance of the bitcoin data service we want to use to search addresses for unspent outputs
$utxoFinder = new BlocktrailBatchUnspentOutputFinder("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');

// alternative UTXO finder
// $utxoFinder = new InsightUnspentOutputFinder(true /* testnet */);

// create instance of sweeper - will automatically create primary keys from mnemonics
$walletSweeper = new WalletV2Sweeper($encryptedPrimarySeed, $passwordEncryptedSecret, $backupSeed, $passphrase, $blocktrailKeys, $utxoFinder, 'btc', $_testnet=true);
$walletSweeper->enableLogging(); // enable logging for more info

// Do wallet fund discovery - can be run separately from sweeping
// var_dump($walletSweeper->discoverWalletFunds());

// Do wallet fund discovery and sweeping - if successful you will be returned a signed transaction ready to submit to the network
$receivingAddress = "2NCcm7hJfJ5wk6GyKvT2ZHCrNsBgjBv2MSF";
$result = $walletSweeper->sweepWallet($receivingAddress);
var_dump($result);
