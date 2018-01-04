<?php

namespace Blocktrail\SDK\Tests;

use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Bitcoin\BIP32Path;

class BIP32KeyTest extends \PHPUnit_Framework_TestCase {

    public function testBIP32Key() {
        $network = NetworkFactory::bitcoin();

        $e = null;
        try {
            HierarchicalKeyFactory::fromExtended("xpub1", $network);
        } catch (\Exception $e) {
            // key was invalid
        }
        $this->assertTrue(!!$e, "an exception should be thrown");

        $xprv = "xprv9s21ZrQH143K44ed3A1NBn3udjmm6qHRpX4Da47ZpRdhqxpkhCWwMFWNpFbSkxAtkZ2s2345tyX5GdTuDWQYZ9jZPuTbkkBeHx3h6RmzL8J";

        $k = BIP32Key::create($network, HierarchicalKeyFactory::fromExtended($xprv, $network), "m");

        $this->assertEquals($xprv, $k->key()->toExtendedKey($network));
        $this->assertEquals("m", $k->path());
        $this->assertEquals([$xprv, "m"], $k->tuple());

        $c1 = $k->buildKey("m/1");

        $this->assertEquals("xprv9uyqGTZ6imvYQgfExoMn4ja1cD8DhHqhTxZVDBKSeETMqd87Nx5vexxEnuXYFMFi5ViQFdvw7AQt3RovuTivKdSFmygPBadBmuCVLszfHDc", $c1->key()->toExtendedKey($network));
        $this->assertEquals("m/1", $c1->path());
        $this->assertEquals(["xprv9uyqGTZ6imvYQgfExoMn4ja1cD8DhHqhTxZVDBKSeETMqd87Nx5vexxEnuXYFMFi5ViQFdvw7AQt3RovuTivKdSFmygPBadBmuCVLszfHDc", "m/1"], $c1->tuple());

        $p = $c1->bip32Path();

        $this->assertTrue($p instanceof BIP32Path);
        $this->assertEquals("m/1", (string)$p);

        $this->assertEquals("039e3650a99c00105dee96b0ceee7ace9ad6f898fc728275937b9f58e831570210", $c1->publicKeyHex());
    }
}
