<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\Bitcoin\BIP32Path;

class BIP32PathTest extends \PHPUnit_Framework_TestCase {

    public function testBIP32Path() {
        $this->assertEquals(new BIP32Path("m/0"), BIP32Path::path("m/0"));

        $p = BIP32Path::path("m/0");
        $this->assertEquals("m/0", (string)$p);
        $this->assertEquals("m", (string)$p->parent());
        $this->assertEquals("m/1", (string)$p->next());
        $this->assertEquals("m/1", (string)$p->last(1));
        $this->assertEquals("m/0/1", (string)$p->child(1));
        $this->assertEquals("m/0", (string)$p->child(1)->parent());
        $this->assertEquals("m/1/1/3", (string)$p->next()->child(1)->child(1)->last(3));
        $this->assertEquals("m/0'", (string)$p->hardened());
        $this->assertTrue($p->hardened()->isHardened());
        $this->assertTrue(!$p->isPublicPath());
        $this->assertEquals("m/0", $p->privatePath());
        $this->assertEquals("M/0", $p->publicPath());
        $this->assertTrue($p->publicPath()->isPublicPath());
        $this->assertEquals("m/0", $p->hardened()->unhardenedLast());
        $this->assertEquals("m/0'/1/1", $p->hardened()->child(1)->child(1));
        $this->assertEquals("m/0'/1/1", $p->hardened()->child(1)->child(1)->unhardenedLast());
        $this->assertEquals("m/0/1/1", $p->hardened()->child(1)->child(1)->unhardenedPath());

        $p = BIP32Path::path("m/0'/1/2");

        $this->assertEquals("m", $p[0]);
        $this->assertEquals("0'", $p[1]);
        $this->assertEquals("1", $p[2]);
        $this->assertEquals("2", $p[3]);

        $this->assertEquals("m/0'/6/1/2", (string)$p->insert("6", 1));
        $this->assertEquals("m/6/0'/1/2", (string)$p->insert("6", 0));
    }

    public function testIsParentOf() {
        $ok = [
            "M/9999'/0" => [
                "M/9999'/0/0", "M/9999'/0/1", "M/9999'/0/0", "M/9999'/0/1", "M/9999'/0/0'",
            ],
            "m/9999'/0" => [
                "m/9999'/0/0", "m/9999'/0/1", "m/9999'/0/0", "m/9999'/0/1",
            ],
        ];

        foreach ($ok as $p => $cs) {
            foreach ($cs as $c) {
                $this->assertTrue(BIP32Path::path($p)->isParentOf(BIP32Path::path($c)), "parent[{$p}] child[{$c}]");
            }
        }

        $fail = [
            "M/9999'/0" => [
                "M/9999'/0", "m/9999/0'/0", "M/9999/0'/0", "M/999'/0/1",
            ],
            "m/999'/0" => [
                "m/999'/0", "M/999'/0/0", "m/9999'/0/0", "m/9999'/0/1", "m/9999'/0/0", "m/9999'/0/1",
            ],
        ];

        foreach ($fail as $p => $cs) {
            foreach ($cs as $c) {
                $this->assertFalse(BIP32Path::path($p)->isParentOf(BIP32Path::path($c)), "parent[{$p}] child[{$c}]");
            }
        }

    }
}
