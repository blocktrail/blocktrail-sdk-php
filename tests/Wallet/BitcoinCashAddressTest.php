<?php

namespace Blocktrail\SDK\Tests\Wallet;


use BitWasp\Bitcoin\Address\ScriptHashAddress;
use Blocktrail\SDK\Address\BitcoinCashAddressReader;
use Blocktrail\SDK\Address\CashAddress;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Network\BitcoinCashRegtest;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\Network\BitcoinCash;
use Blocktrail\SDK\Wallet;
use Mockery\Mock;

class BitcoinCashAddressTest extends WalletTestBase
{
    /**
     * @expectedException \Blocktrail\SDK\Exceptions\BlocktrailSDKException
     * @expectedExceptionMessage Address not recognized
     */
    public function testShortCashAddress() {
        $bch = new BitcoinCash();
        $tbch = new BitcoinCashRegtest();

        $address = "bchtest:ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu";
        $short = "ppm2qsznhks23z7629mms6s4cwef74vcwvhanqgjxu";
        $reader = new BitcoinCashAddressReader(true);
        $this->assertEquals($address, $reader->fromString($address, $bch)->getAddress($bch));
        $this->assertEquals($address, $reader->fromString($short, $bch)->getAddress($bch));

        $this->setExpectedException(BlocktrailSDKException::class, "Address not recognized");
        $reader->fromString($short, $tbch);
    }

    public function testInitializeWithDefaultFormat() {
        $tbcc = new BitcoinCashRegtest();
        /** @var BlocktrailSDK|Mock $client */
        $client = $this->mockSDK(true);

        /** @var Wallet $legacyAddressWallet */
        list($legacyAddressWallet, $client) = $this->initWallet($client);

        $legacyAddress = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $this->assertInstanceOf(BitcoinCashAddressReader::class, $legacyAddressWallet->getAddressReader());
        $this->assertInstanceOf(ScriptHashAddress::class, $legacyAddressWallet->getAddressReader()->fromString($legacyAddress, $tbcc));

        $cashAddress = "bchreg:pqaql0az73r2lrtk43l3w3scuazgvarqdyufw37rsn";

        /** @var Wallet $newAddressWallet */
        list($newAddressWallet, $client) = $this->initWallet($client, self::WALLET_IDENTIFIER, self::WALLET_PASSWORD, false, [
            'use_cashaddress' => true,
        ]);

        $this->assertInstanceOf(BitcoinCashAddressReader::class, $newAddressWallet->getAddressReader());
        $this->assertInstanceOf(CashAddress::class, $newAddressWallet->getAddressReader()->fromString($cashAddress, $tbcc));

        $convertedLegacy = $client->getLegacyBitcoinCashAddress($cashAddress);

        $this->assertEquals($legacyAddress, $convertedLegacy);
    }

    public function testCurrentDefaultIsOldFormat() {
        $tbcc = new BitcoinCashRegtest();
        /** @var BlocktrailSDK|Mock $client */
        $client = $this->mockSDK(true);

        /** @var Wallet $cashAddrWallet */
        list($cashAddrWallet, $client) = $this->initWallet($client);

        $this->shouldGetNewDerivation($client, self::WALLET_IDENTIFIER, "m/9999'/1/*", "M/9999'/1/0");

        $newAddress = $cashAddrWallet->getNewAddress();

        $reader = $cashAddrWallet->getAddressReader();
        $this->assertInstanceOf(ScriptHashAddress::class, $reader->fromString($newAddress, $tbcc));
    }

    public function testCanOptIntoNewAddressFormat() {
        $tbcc = new BitcoinCashRegtest();
        /** @var BlocktrailSDK|Mock $client */
        $client = $this->mockSDK(true);

        /** @var Wallet $cashAddrWallet */
        list($cashAddrWallet, $client) = $this->initWallet($client, self::WALLET_IDENTIFIER, self::WALLET_PASSWORD, false, [
            'use_cashaddress' => true,
        ]);

        $this->shouldGetNewDerivation($client, self::WALLET_IDENTIFIER, "m/9999'/1/*", "M/9999'/1/0");

        $newAddress = $cashAddrWallet->getNewAddress();

        $reader = $cashAddrWallet->getAddressReader();
        $this->assertInstanceOf(CashAddress::class, $reader->fromString($newAddress, $tbcc));
    }

    public function testCanCoinSelectNewCashAddresses() {
        $tbcc = new BitcoinCashRegtest();
        /** @var BlocktrailSDK|Mock $client */
        $client = $this->mockSDK(true);

        /** @var Wallet $cashAddrWallet */
        list($cashAddrWallet, $client) = $this->initWallet($client, self::WALLET_IDENTIFIER, self::WALLET_PASSWORD, false, [
            'use_cashaddress' => true,
        ]);

        $str = "bchreg:pqaql0az73r2lrtk43l3w3scuazgvarqdyufw37rsn";
        $cashaddr = $cashAddrWallet->getAddressReader()->fromString($str, $tbcc);

        $this->shouldCoinSelect($client, self::WALLET_IDENTIFIER, 1234123, $cashaddr->getScriptPubKey()->getHex(), [
            [
                'scriptpubkey_hex' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'path' => 'M/9999\'/0/0',
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ],
        ]);

        $selection = $cashAddrWallet->coinSelection([
             $cashaddr->getAddress($tbcc) => 1234123,
        ]);

        $this->assertArrayHasKey('utxos', $selection);
        $this->assertTrue(count($selection['utxos']) > 0);
    }
}
