<?php

use Blocktrail\SDK\Services\BlocktrailBitcoinService;
use Blocktrail\SDK\WalletSweeper;

require_once __DIR__ . "/../vendor/autoload.php";


//Process can take a long time - disable php execution time limit
set_time_limit(0);

//the primary mnemonic, obtained from our backup pdf
$primaryMnemonic    = "print work mail summer small crouch auto one taste boat spin dog reward
winter milk onion civil nice symbol bright boy whale fabric cement collect
voice diagram dress tank warfare skin where brand immune this rescue
security ahead weasel stay kind tornado butter process promote cheap scheme
kid";

//our wallet passphrase
$primaryPassphrase  = "example-strong-password";

//the primary mnemonic, obtained from our backup pdf
$backupMnemonic     = "toddler green make maple gas slush chest tag dutch employ noodle fade
borrow carry pupil argue deer shadow attack escape unfair worry shuffle
regular local away shrimp amount endless venue practice decade author
trouble elite view squirrel alone swear squirrel latin stable fat window
express diesel design cart";

//the blocktrail keys used by this wallet, obtained from the backup pdf
$blocktrailKeys = array(
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

//Do wallet fund discovery - can be run separately from sweeping
//var_dump($walletSweeper->discoverWalletFunds());

//Do wallet fund discovery and sweeping - if successful you will be returned a transaction ready to submit to the network
$receivingAddress = "2NDGLXM74jXQRbXUDBiJCSS8ERnD1jgp9hZ";
$result = $walletSweeper->sweepWallet($receivingAddress);
var_dump($result);
