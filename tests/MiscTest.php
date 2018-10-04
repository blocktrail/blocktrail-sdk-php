<?php

namespace Blocktrail\SDK\Tests;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\Wallet;

class MiscTest extends \PHPUnit_Framework_TestCase {

    public function testBIP32() {
        Bitcoin::setNetwork(NetworkFactory::bitcoinTestnet());

        $masterkey = HierarchicalKeyFactory::fromExtended("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ");

        $this->assertEquals("022f6b9339309e89efb41ecabae60e1d40b7809596c68c03b05deb5a694e33cd26", $masterkey->getPublicKey()->getHex());
        $this->assertEquals("tpubDAtJthHcm9MJwmHp4r2UwSTmiDYZWHbQUMqySJ1koGxQpRNSaJdyL2Ab8wwtMm5DsMMk3v68299LQE6KhT8XPQWzxPLK5TbTHKtnrmjV8Gg", $masterkey->derivePath("0")->toExtendedKey());
        $this->assertEquals("tpubDDfqpEKGqEVa5FbdLtwezc6Xgn81teTFFVA69ZfJBHp4UYmUmhqVZMmqXeJBDahvySZrPjpwMy4gKfNfrxuFHmzo1r6srB4MrsDKWbwEw3d", $masterkey->derivePath("0/0")->toExtendedKey());

        $this->assertEquals(
            "tpubDHNy3kAG39ThyiwwsgoKY4iRenXDRtce8qdCFJZXPMCJg5dsCUHayp84raLTpvyiNA9sXPob5rgqkKvkN8S7MMyXbnEhGJMW64Cf4vFAoaF",
            HierarchicalKeyFactory::fromEntropy(Buffer::hex("000102030405060708090a0b0c0d0e0f"))->derivePath("M/0'/1/2'/2/1000000000")->toExtendedPublicKey()
        );
    }

    public function testNormalizeOutputStruct() {
        $expected = [['address' => 'address1', 'value' => 'value1'], ['address' => 'address2', 'value' => 'value2']];

        $this->assertEquals($expected, Wallet::normalizeOutputsStruct(['address1' => 'value1', 'address2' => 'value2']));
        $this->assertEquals($expected, Wallet::normalizeOutputsStruct([['address1', 'value1'], ['address2', 'value2']]));
        $this->assertEquals($expected, Wallet::normalizeOutputsStruct($expected));

        // duplicate address
        $expected = [['address' => 'address1', 'value' => 'value1'], ['address' => 'address1', 'value' => 'value2']];

        // not possible, keyed by address
        // $this->assertEquals($expected, Wallet::normalizeOutputsStruct(['address1' => 'value1', 'address1' => 'value2']));
        $this->assertEquals($expected, Wallet::normalizeOutputsStruct([['address1', 'value1'], ['address1', 'value2']]));
        $this->assertEquals($expected, Wallet::normalizeOutputsStruct($expected));
    }

    public function testEstimateFee() {
        $this->assertEquals(30000, Wallet::estimateFee(1, 66));
        $this->assertEquals(40000, Wallet::estimateFee(2, 71));
    }

    public function testEstimateSizeOutputs() {
        $this->assertEquals(34, Wallet::estimateSizeOutputs(1));
        $this->assertEquals(68, Wallet::estimateSizeOutputs(2));
        $this->assertEquals(102, Wallet::estimateSizeOutputs(3));
        $this->assertEquals(3366, Wallet::estimateSizeOutputs(99));
    }

    public function testEstimateSizeUTXOs() {
        $this->assertEquals(297, Wallet::estimateSizeUTXOs(1));
        $this->assertEquals(594, Wallet::estimateSizeUTXOs(2));
        $this->assertEquals(891, Wallet::estimateSizeUTXOs(3));
        $this->assertEquals(29403, Wallet::estimateSizeUTXOs(99));
    }

    public function testEstimateSize() {
        $this->assertEquals(347, Wallet::estimateSize(34, 297));
        $this->assertEquals(29453, Wallet::estimateSize(34, 29403));
        $this->assertEquals(3679, Wallet::estimateSize(3366, 297));
    }
}
