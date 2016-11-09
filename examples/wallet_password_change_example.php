<?php

use Blocktrail\SDK\BackupGenerator;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\WalletInterface;

require_once __DIR__ . "/../vendor/autoload.php";


$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');
$client->setVerboseErrors(true);

//create a new wallet
$bytes = BlocktrailSDK::randomBytes(10);
$identifier = bin2hex($bytes);

/** @var WalletInterface $wallet */
list($wallet, $backupInfo) = $client->createNewWallet($identifier, "example-strong-password", $_account=9999);

$backupInfo = $wallet->passwordChange("example-stronger-password");

//generate the backup document
$backupGenerator = new BackupGenerator($identifier, $backupInfo, null, ['page1' => false, 'page3' => false]);
$pdfStream = $backupGenerator->generatePDF();

//we can either save the pdf file locally
file_put_contents("my-wallet-backup-password-change.pdf", $pdfStream);
