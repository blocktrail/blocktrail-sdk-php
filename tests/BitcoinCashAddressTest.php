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

        $this->assertInstanceOf(BitcoinCashAddressReader::class, $legacyAddressWallet->getAddressReader());
        $this->assertInstanceOf(ScriptHashAddress::class, $legacyAddressWallet->getAddressReader()->fromString("2MsM9zVVyar93CWorEfH6PPW8QQmW3s1uh6", $tbcc));

        $newAddressWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "password" => "password",
            "use_cashaddress" => true,
        ]);

        $this->assertInstanceOf(BitcoinCashAddressReader::class, $newAddressWallet->getAddressReader());
        $this->assertInstanceOf(CashAddress::class, $newAddressWallet->getAddressReader()->fromString("bchtest:ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu", $tbcc));
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

//    public function testCanCoinSelectNewAddresses()
//    {
//        $isTestnet = true;
//        $tbcc = new BitcoinCash($isTestnet);
//
//        $client = $this->setupBlocktrailSDK("BCC", $isTestnet);
//        $cashAddrWallet = $client->initWallet([
//            "identifier" => "unittest-transaction",
//            "password" => "password",
//            "use_cashaddress" => true,
//        ]);
//
//        $selection = $cashAddrWallet->coinSelection([
//            "bchtest:ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu" => 1234123,
//        ], false);
//
//        var_Dump($selection);
//    }
}
