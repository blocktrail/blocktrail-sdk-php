<?php

namespace Blocktrail\SDK\Tests\Network;

use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Buffertools\Buffer;
use Btccom\BitcoinCash\Address\CashAddress;
use Btccom\BitcoinCash\Network\NetworkFactory as BchNetworkFactory;

use Blocktrail\SDK\Network\BitcoinCash;
use Blocktrail\SDK\Network\BitcoinCashTestnet;

class BitcoinCashTest extends \PHPUnit_Framework_TestCase
{

    public function getCashAddressFixture()
    {
        return [
            [false, "scripthash", "76a04053bda0a88bda5177b86a15c3b29f559873", "bitcoincash:ppm2qsznhks23z7629mms6s4cwef74vcwvn0h829pq"],
            [true, "scripthash", "76a04053bda0a88bda5177b86a15c3b29f559873", "bchtest:ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu"],
            [false, "pubkeyhash", "011f28e473c95f4013d7d53ec5fbc3b42df8ed10", "bitcoincash:qqq3728yw0y47sqn6l2na30mcw6zm78dzqre909m2r"],
        ];
    }

    /**
     * @param $testnet
     * @param $type
     * @param $hashHex
     * @param $expected
     * @throws \Blocktrail\SDK\Exceptions\BlocktrailSDKException
     * @throws \CashAddr\Exception\Base32Exception
     * @throws \CashAddr\Exception\CashAddressException
     * @throws \Exception
     * @dataProvider getCashAddressFixture
     */
    public function testCashAddress($testnet, $type, $hashHex, $expected)
    {
        if ($testnet) {
            $network = BchNetworkFactory::bitcoinCashTestnet();
        } else {
            $network = BchNetworkFactory::bitcoinCash();
        }

        $hash = Buffer::hex($hashHex);
        $addr = new CashAddress($type, $hash);
        $this->assertEquals($type, $addr->getType());
        $this->assertTrue($hash->equals($addr->getHash()));
        $this->assertEquals($network->getCashAddressPrefix(), $addr->getPrefix($network));
        $this->assertEquals($expected, $addr->getAddress($network));
    }
}
