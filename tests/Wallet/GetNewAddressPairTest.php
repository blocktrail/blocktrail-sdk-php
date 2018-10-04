<?php

namespace Blocktrail\SDK\Tests\Wallet;

use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletInterface;
use Mockery\Mock;

class GetNewAddressPairTest extends WalletTestBase {

    public function testWalletGetNewAddressPairNonSegwit() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, "mypassword", false);

        $this->assertFalse($wallet->isSegwit());

        $this->shouldGetNewDerivation($client, $identifier, "m/9999'/0/*", "M/9999'/0/0");
        $this->checkWalletScriptAgainstAddressPair($wallet, Wallet::CHAIN_BTC_DEFAULT);

        $path = "M/9999'/0/0";
        $script = $wallet->getWalletScriptByPath($path);

        $this->assertTrue($script->isP2SH());
        $this->assertFalse($script->isP2WSH());

        $this->isBase58Address($script);
        $this->checkP2sh($script->getScriptPubKey(), $script->getRedeemScript());

        $this->assertEquals("2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa", $script->getAddress()->getAddress());
        $this->assertEquals("a9143a0fbfa2f446af8d76ac7f174618e7448674606987", $script->getScriptPubKey()->getHex());
        $this->assertEquals("5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae", $script->getRedeemScript()->getHex());
        $this->assertEquals(null, $script->getWitnessScript(true));
    }

    /**
     * @expectedException \Blocktrail\SDK\Exceptions\BlocktrailSDKException
     * @expectedExceptionMessage Unsupported chain in path
     */
    public function testWalletGetNewAddressPairNonSegwitDisallowSegwit() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, "mypassword", false);

        $this->assertFalse($wallet->isSegwit());
        $wallet->getRedeemScriptByPath("M/9999'/2/0");
    }

    public function testWalletGetNewAddressPairBcash() {
        $client = $this->mockSDK(true);

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, "mypassword", false, [
            'use_cashaddress' => true
        ]);

        $this->assertFalse($wallet->isSegwit());

        $this->shouldGetNewDerivation($client, $identifier, "m/9999'/0/*", "M/9999'/0/0");
        $this->checkWalletScriptAgainstAddressPair($wallet, Wallet::CHAIN_BTC_DEFAULT);

        $path = "M/9999'/0/0";
        $script = $wallet->getWalletScriptByPath($path);

        $this->assertTrue($script->isP2SH());
        $this->assertFalse($script->isP2WSH());

        $this->isCashAddress($script);
        $this->checkP2sh($script->getScriptPubKey(), $script->getRedeemScript());

        $this->assertEquals("bchreg:pqaql0az73r2lrtk43l3w3scuazgvarqdyufw37rsn", $script->getAddress()->getAddress());
        $this->assertEquals("a9143a0fbfa2f446af8d76ac7f174618e7448674606987", $script->getScriptPubKey()->getHex());
        $this->assertEquals("5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae", $script->getRedeemScript()->getHex());
        $this->assertEquals(null, $script->getWitnessScript(true));
    }

    public function testWalletGetNewAddressPairSegwit() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, "mypassword", true);

        $this->assertTrue($wallet->isSegwit());

        $this->shouldGetNewDerivation($client, $identifier, "m/9999'/0/*", "M/9999'/0/0");
        $this->checkWalletScriptAgainstAddressPair($wallet, Wallet::CHAIN_BTC_DEFAULT);

        $this->shouldGetNewDerivation($client, $identifier, "m/9999'/2/*", "M/9999'/2/0");
        $this->checkWalletScriptAgainstAddressPair($wallet, Wallet::CHAIN_BTC_SEGWIT);

        $nestedP2wshPath = "M/9999'/2/0";
        $script = $wallet->getWalletScriptByPath($nestedP2wshPath);

        $this->assertTrue($script->isP2SH());
        $this->assertTrue($script->isP2WSH());

        $this->isBase58Address($script);
        $this->checkP2sh($script->getScriptPubKey(), $script->getRedeemScript());
        $this->checkP2wsh($script->getRedeemScript(), $script->getWitnessScript());

        $this->assertEquals("2MtPQLhhzFNtaY59QQ9wt28a5qyQ2t8rnvd", $script->getAddress()->getAddress());
        $this->assertEquals("a9140c84184d6d00096482c1359ce0194376ad248d2287", $script->getScriptPubKey()->getHex());
        $this->assertEquals("00204679ecfef34eb556071e349442e74837d059cb42ef22c4946a2efbb0c9041e68", $script->getRedeemScript()->getHex());
        $this->assertEquals("522102381a4cf140c24080523b5e63082496b514e99657d3506444b7f77c121766353021028e2ec167fbdd100fcb95e22ce4b49d4733b1f291eef6b5f5748852c96aa9522721038ba8bf95fc3449c3b745232eeca5493fd3277b801e659482cc868ca6e55ce23b53ae", $script->getWitnessScript()->getHex());
    }

    private function checkWalletScriptAgainstAddressPair(WalletInterface $wallet, $chainIdx)
    {
        list ($path, $address) = $wallet->getNewAddressPair($chainIdx);
        $this->assertTrue(strpos("M/9999'/{$chainIdx}/", $path) !== -1);

        $defaultScript = $wallet->getWalletScriptByPath($path);
        $this->assertEquals($defaultScript->getAddress()->getAddress(), $address);

        $classifier = new OutputClassifier();

        switch($chainIdx) {
            case Wallet::CHAIN_BTC_SEGWIT:
                $this->assertTrue($defaultScript->isP2SH());
                $this->assertTrue($defaultScript->isP2WSH());
                $this->assertTrue($classifier->isMultisig($defaultScript->getWitnessScript()));
                $this->assertTrue($classifier->isWitness($defaultScript->getRedeemScript()));
                break;
            case Wallet::CHAIN_BTC_DEFAULT:
                $this->assertTrue($defaultScript->isP2SH());
                $this->assertTrue($classifier->isMultisig($defaultScript->getRedeemScript()));
                break;
        }
    }

    /**
     * @expectedException \Blocktrail\SDK\Exceptions\BlocktrailSDKException
     * @expectedExceptionMessage Unsupported chain in path
     */
    public function testWalletRejectsUnknownPaths()
    {
        $client = $this->mockSDK(true);

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client);

        $wallet->getWalletScriptByPath("M/9999'/123123123/0");
    }


    /**
     * @expectedException \Blocktrail\SDK\Exceptions\BlocktrailSDKException
     * @expectedExceptionMessage Chain index is invalid - should be an integer
     */
    public function testCheckRejectsInvalidChainINdex()
    {
        $client = $this->mockSDK(true);

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client);

        $wallet->getNewAddress('');
    }
}
