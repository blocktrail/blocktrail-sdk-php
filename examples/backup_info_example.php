<?php

use Blocktrail\SDK\BackupGenerator;
use Blocktrail\SDK\BlocktrailSDK;

require_once __DIR__ . "/../vendor/autoload.php";


$client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true /* testnet */, 'v1');
$client->setVerboseErrors(true);

//create a new wallet
$bytes = openssl_random_pseudo_bytes(10);
$identifier = bin2hex($bytes);

list($wallet, $primaryMnemonic, $backupMnemonic, $blocktrailPublicKeys) = $client->createNewWallet($identifier, "example-strong-password", $_account=9999);

//generate the backup document
$backupGenerator = new BackupGenerator($primaryMnemonic, $backupMnemonic, $blocktrailPublicKeys);
$pdfStream = $backupGenerator->generatePDF();

//we can either save the pdf file locally
file_put_contents("my wallet backup.pdf", $pdfStream);

//or output the pdf to the browser
header("Content-type:application/pdf");
echo $pdfStream;


//html, img, and plain txt documents can also be generated
//$backupHTML = $backupGenerator->generateHTML();
//echo $backupHTML;

//$backupGenerator->generateImg('my wallet backup.png');

//$backupText = $backupGenerator->generateTxt();
//echo $backupText;