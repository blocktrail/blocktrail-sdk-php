<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\BackupGenerator;

\error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

/**
 * Class WalletRecoveryTest
 * We have set up a testnet wallet with known unspent outputs in certain addresses for these test
 *
 *
 * @package Blocktrail\SDK\Tests
 */
class WalletBackupTest extends WalletTestCase
{
    public function testIntegrationV1WalletBackup() {

        $client = $this->setupBlocktrailSDK("BTC", true);

        $identifier = $this->getRandomTestIdentifier();
        $passphrase = "password";
        $keyIndex = 9999;

        $walletOpt = [
            "identifier" => $identifier,
            "passphrase" => $passphrase,
            "key_index" => $keyIndex,
        ];

        list($wallet, $backupInfo) = $client->createNewWallet($walletOpt);

        $this->cleanupData['wallets'][] = $wallet;

        //generate the backup document
        $backupGenerator = new BackupGenerator($identifier, $backupInfo, /* $extra = */['username' => 'testing123', 'note to self' => 'buy pizza with BTC 2night!']);
        $pdfStream = $backupGenerator->generatePDF();

        $tempDir = sys_get_temp_dir();
        $path = $tempDir . "/backuptest-v1.pdf";

        $this->assertEquals(strlen($pdfStream), file_put_contents($path, $pdfStream));
        unlink($path);
    }

    public function testV2WalletBackup() {

    }

    public function testV3WalletBackup() {

    }
}
