<?php

namespace Blocktrail\SDK\Tests;


use BitWasp\Bitcoin\Address\ScriptHashAddress;
use Btccom\BitcoinCash\Address\AddressCreator as BitcoinCashAddressCreator;
use Btccom\BitcoinCash\Network\Networks\BitcoinCash;
use Btccom\BitcoinCash\Network\Networks\BitcoinCashTestnet;
use Btccom\BitcoinCash\Address\CashAddress;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

class BitcoinCashAddressTest extends BlocktrailTestCase
{
    public function testShortCashAddress() {
        $bch = new BitcoinCash();
        $tbch = new BitcoinCashTestnet();

        $address = "bchtest:ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu";
        $short = "ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu";
        $reader = new BitcoinCashAddressCreator(true);
        $this->assertEquals($address, $reader->fromString($address, $tbch)->getAddress($tbch));
        $this->assertEquals($address, $reader->fromString($short, $tbch)->getAddress($tbch));

        $this->setExpectedException(\BitWasp\Bitcoin\Exceptions\UnrecognizedAddressException::class, "Address not recognized");
        $reader->fromString($short, $bch);
    }

    public function testInitializeWithDefaultFormat() {
        $isTestnet = true;
        $tbcc = new BitcoinCashTestnet();
        $client = $this->setupBlocktrailSDK("BCC", $isTestnet);
        $legacyAddressWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "password" => "password"
        ]);

        $legacyAddress = "2N44ThNe8NXHyv4bsX8AoVCXquBRW94Ls7W";
        $this->assertInstanceOf(BitcoinCashAddressCreator::class, $legacyAddressWallet->getAddressReader());
        $this->assertInstanceOf(ScriptHashAddress::class, $legacyAddressWallet->getAddressReader()->fromString($legacyAddress, $tbcc));

        $cashAddress = "bchtest:ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu";
        $newAddressWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "password" => "password",
            "use_cashaddress" => true,
        ]);

        $this->assertInstanceOf(BitcoinCashAddressCreator::class, $newAddressWallet->getAddressReader());
        $this->assertInstanceOf(CashAddress::class, $newAddressWallet->getAddressReader()->fromString($cashAddress, $tbcc));

        $convertedLegacy = $client->getLegacyBitcoinCashAddress($cashAddress);

        $this->assertEquals($legacyAddress, $convertedLegacy);
    }

    public function testCurrentDefaultIsOldFormat() {
        $isTestnet = true;
        $tbcc = new BitcoinCashTestnet();

        $client = $this->setupBlocktrailSDK("BCC", $isTestnet);
        $cashAddrWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "password" => "password",
            "use_cashaddress" => false,
        ]);

        $newAddress = $cashAddrWallet->getNewAddress();

        $reader = $cashAddrWallet->getAddressReader();
        $this->assertInstanceOf(ScriptHashAddress::class, $reader->fromString($newAddress, $tbcc));
    }

    public function testCanOptIntoNewAddressFormat() {
        $isTestnet = true;
        $tbcc = new BitcoinCashTestnet();

        $client = $this->setupBlocktrailSDK("BCC", $isTestnet);
        $cashAddrWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "password" => "password",
            "use_cashaddress" => true,
        ]);

        $newAddress = $cashAddrWallet->getNewAddress();

        $reader = $cashAddrWallet->getAddressReader();
        $this->assertInstanceOf(CashAddress::class, $reader->fromString($newAddress, $tbcc));
    }

    public function testCanCoinSelectNewCashAddresses()
    {
        $isTestnet = true;
        $tbcc = new BitcoinCashTestnet();
        $client = $this->setupBlocktrailSDK("BCC", $isTestnet);
        $cashAddrWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "password" => "password",
            "use_cashaddress" => true,
        ]);

        $str = "bchtest:ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu";
        $cashaddr = $cashAddrWallet->getAddressReader()->fromString($str, $tbcc);

        $selection = $cashAddrWallet->coinSelection([
             $cashaddr->getAddress($tbcc) => 1234123,
        ], false);

        $this->assertArrayHasKey('utxos', $selection);
        $this->assertTrue(count($selection['utxos']) > 0);
    }
}
