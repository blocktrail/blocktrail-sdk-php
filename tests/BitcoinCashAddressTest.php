<?php

namespace Blocktrail\SDK\Tests;


use BitWasp\Bitcoin\Address\ScriptHashAddress;
use Blocktrail\SDK\Address\BitcoinCashAddressReader;
use Blocktrail\SDK\Address\CashAddress;
use Blocktrail\SDK\Network\BitcoinCash;

class BitcoinCashAddressTest extends BlocktrailTestCase
{
    public function testInitializeWithDefaultFormat() {
        $isTestnet = true;
        $tbcc = new BitcoinCash($isTestnet);
        $client = $this->setupBlocktrailSDK("BCC", $isTestnet);
        $legacyAddressWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "password" => "password"
        ]);

        $legacyAddress = "2N44ThNe8NXHyv4bsX8AoVCXquBRW94Ls7W";
        $this->assertInstanceOf(BitcoinCashAddressReader::class, $legacyAddressWallet->getAddressReader());
        $this->assertInstanceOf(ScriptHashAddress::class, $legacyAddressWallet->getAddressReader()->fromString($legacyAddress, $tbcc));

        $cashAddress = "bchtest:ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu";
        $newAddressWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "password" => "password",
            "use_cashaddress" => true,
        ]);

        $this->assertInstanceOf(BitcoinCashAddressReader::class, $newAddressWallet->getAddressReader());
        $this->assertInstanceOf(CashAddress::class, $newAddressWallet->getAddressReader()->fromString($cashAddress, $tbcc));

        $convertedLegacy = $client->getLegacyBitcoinCashAddress($cashAddress);

        $this->assertEquals($legacyAddress, $convertedLegacy);
    }

    public function testCurrentDefaultIsOldFormat() {
        $isTestnet = true;
        $tbcc = new BitcoinCash($isTestnet);

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
        $tbcc = new BitcoinCash($isTestnet);

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
        $network = new BitcoinCash($isTestnet);
        $client = $this->setupBlocktrailSDK("BCC", $isTestnet);
        $cashAddrWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "password" => "password",
            "use_cashaddress" => true,
        ]);

        $str = "bchtest:ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu";
        $cashaddr = $cashAddrWallet->getAddressReader()->fromString($str, $network);

        $selection = $cashAddrWallet->coinSelection([
             $cashaddr->getAddress($network) => 1234123,
        ], false);

        $this->assertArrayHasKey('utxos', $selection);
        $this->assertTrue(count($selection['utxos']) > 0);
    }
}
