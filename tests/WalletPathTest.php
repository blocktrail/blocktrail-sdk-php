<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\Bitcoin\BIP32Path;
use Blocktrail\SDK\WalletPath;

class WalletPathTest extends \PHPUnit_Framework_TestCase {

    public function testWalletPath() {
        $this->assertEquals(new WalletPath(), WalletPath::create());

        $w = WalletPath::create();
        $this->assertTrue($w->path() instanceof BIP32Path);
        $this->assertTrue($w->keyIndexPath() instanceof BIP32Path);
        $this->assertTrue($w->backupPath() instanceof BIP32Path);
        $this->assertTrue($w->keyIndexBackupPath() instanceof BIP32Path);

        $w = WalletPath::create();
        $this->assertEquals("m/0'/0/0", (string)$w->path());
        $this->assertEquals("m/0'", (string)$w->keyIndexPath());
        $this->assertEquals("m/0/0/0", (string)$w->backupPath());
        $this->assertEquals("m/0", (string)$w->keyIndexBackupPath());

        $w = $w->address(1);
        $this->assertEquals("m/0'/0/1", (string)$w->path());
        $this->assertEquals("m/0'", (string)$w->keyIndexPath());
        $this->assertEquals("m/0/0/1", (string)$w->backupPath());
        $this->assertEquals("m/0", (string)$w->keyIndexBackupPath());

        $w = WalletPath::create(1, 1, 1);
        $this->assertEquals("m/1'/1/1", (string)$w->path());
        $this->assertEquals("m/1'", (string)$w->keyIndexPath());
        $this->assertEquals("m/1/1/1", (string)$w->backupPath());
        $this->assertEquals("m/1", (string)$w->keyIndexBackupPath());

        $w = $w->address(2);
        $this->assertEquals("m/1'/1/2", (string)$w->path());
        $this->assertEquals("m/1'", (string)$w->keyIndexPath());
        $this->assertEquals("m/1/1/2", (string)$w->backupPath());
        $this->assertEquals("m/1", (string)$w->keyIndexBackupPath());
    }
}
