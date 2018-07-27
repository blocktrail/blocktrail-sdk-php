<?php

namespace Blocktrail\SDK\Tests\Wallet;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Wallet;
use Mockery\Mock;

class InitWalletCheckBackupKeyTest extends WalletTestBase {

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testChecksBackupKeyShouldBeXpub() {
        $client = $this->mockSDK();

        $identifier = "mywallet";
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, "mypassword", true, [
            'check_backup_key' => [],
        ]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testChecksBackupKeyShouldBeXpub2() {
        $client = $this->mockSDK();

        $identifier = "mywallet";
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, "mypassword", true, [
            'check_backup_key' => 'for demonstration purposes only'
        ]);
    }

    public function testChecksBackupKey() {
        $client = $this->mockSDK();

        $identifier = "mywallet";
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, "mypassword", true, [
            'check_backup_key' => 'tpubD6NzVbkrYhZ4WTekiCroqzjETeQbxvQy8azRH4MT3tKhzZvf8G2QWWFdrTaiTHdYJVNPJJ85nvHjhP9dP7CZAkKsBEJ95DuY7bNZH1Gvw4w',
        ]);
    }
}
