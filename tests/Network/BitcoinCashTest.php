<?php

namespace Blocktrail\SDK\Tests\Network;

use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\Address\CashAddress;
use Blocktrail\SDK\Network\BitcoinCash;
use Blocktrail\SDK\Network\BitcoinCashTestnet;

class BitcoinCashTest extends \PHPUnit_Framework_TestCase
{
    public function testnetProvider()
    {
        return [
            [true, "bchtest",],
            [false, "bitcoincash",],
        ];
    }

    /**
     * @param bool $testnet
     * @dataProvider testnetProvider
     */
    public function testBitcoinCash($testnet, $cashAddrPrefix)
    {
        if ($testnet) {
            $network = new BitcoinCashTestnet();
        } else {
            $network = new BitcoinCash();
        }

        $this->assertEquals($testnet, $network->isTestnet());
        $this->assertEquals($cashAddrPrefix, $network->getCashAddressPrefix());

        if ($testnet) {
            $cmp = NetworkFactory::bitcoinTestnet();
        } else {
            $cmp = NetworkFactory::bitcoin();
        }

        $this->assertEquals($cmp->getAddressByte(), $network->getAddressByte());
        $this->assertEquals($cmp->getP2shByte(), $network->getP2shByte());
        $this->assertEquals($cmp->getPrivByte(), $network->getPrivByte());
        $this->assertEquals($cmp->isTestnet(), $network->isTestnet());
        $this->assertEquals($cmp->getNetMagicBytes(), $network->getNetMagicBytes());
        $this->assertEquals($cmp->getHDPrivByte(), $network->getHDPrivByte());
        $this->assertEquals($cmp->getHDPubByte(), $network->getHDPubByte());
    }

    /**
     * @param $testnet
     * @throws \Exception
     * @dataProvider testnetProvider
     * @expectedException \Exception
     * @expectedExceptionMessage No bech32 prefix for segwit addresses set
     */
    public function testNoBech32($testnet)
    {
        if ($testnet) {
            $network = new BitcoinCashTestnet();
        } else {
            $network = new BitcoinCash();
        }

        $network->getSegwitBech32Prefix();
    }

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
            $network = new BitcoinCashTestnet();
        } else {
            $network = new BitcoinCash();
        }

        $hash = Buffer::hex($hashHex);
        $addr = new CashAddress($type, $hash);
        $this->assertEquals($type, $addr->getType());
        $this->assertTrue($hash->equals($addr->getHash()));
        $this->assertEquals($network->getCashAddressPrefix(), $addr->getPrefix($network));
        $this->assertEquals($expected, $addr->getAddress($network));
    }
}
