<?php

namespace Blocktrail\SDK\Tests;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BIP39\BIP39;
use Blocktrail\SDK\Bitcoin\BIP44;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\Wallet;

/**
 * Class WalletTest
 *
 * ! IMPORTANT NODE !
 * throughout the test cases we use account=9999 to force an insecure development key on the API side
 *  this insecure key is used instead of the normal one so that we can reproduce the result on our staging environment
 *  without our private keys having to leaving our safe production environment
 *
 *
 * @package Blocktrail\SDK\Tests
 */
class WalletTest extends \PHPUnit_Framework_TestCase {

    protected $wallets = [];

    /**
     * setup an instance of BlocktrailSDK
     *
     * @return BlocktrailSDK
     */
    public function setupBlocktrailSDK() {
        $client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true, 'v1');
        // $client->setCurlDebugging();
        return $client;
    }

    protected function tearDown() {
        $this->cleanUp();
    }

    protected function onNotSuccessfulTest(\Exception $e) {
        //called when a test fails
        $this->cleanUp();
        throw $e;
    }

    protected function cleanUp() {
        /**
         * @var Wallet $wallet
         */
        foreach ($this->wallets as $wallet) {
            $wallet->deleteWallet();
        }

        $this->wallets = [];
    }

    protected function getRandomTestIdentifier() {
        $identifier = md5(openssl_random_pseudo_bytes(128));
        $time = time();

        return "unittest-{$time}-{$identifier}";
    }

    protected function createTestWallet(BlocktrailSDK $client, $identifier, $passphrase = "password") {
        $primaryMnemonic = "give pause forget seed dance crawl situate hole keen";

        $seed = BIP39::mnemonicToSeedHex($primaryMnemonic, $passphrase);
        $primaryPrivateKey = BIP32::master_key($seed, 'bitcoin', true);
        $primaryPublicKey = BIP32::extended_private_to_public(BIP32::build_key($primaryPrivateKey, (string)BIP44::BIP44(1, 9999)->accountPath()));

        $backupPublicKey = "02018179b2c46b1bb5ce2fce07c7a5badeada97ac686581670a174f2dee61d3df2";
        $testnet = true;

        $result = $client->_createNewWallet($identifier, $primaryPublicKey, $backupPublicKey, $primaryMnemonic, "", 9999);
        $blocktrailPublicKeys = $result['blocktrail_public_keys'];
        $account = $result['account'];

        return new Wallet($client, $identifier, $primaryPrivateKey, $backupPublicKey, $blocktrailPublicKeys, $account, $testnet);
    }

    public function testWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createTestWallet($client, $identifier);
        $this->wallets[] = $wallet; // store for cleanup

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/44'/1'/9999'/0/0", $path);
        $this->assertEquals("2N31kiJ7JR7VoMf1La3xyNHMPni2Xh33FK7", $address);

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/44'/1'/9999'/0/1", $path);
        $this->assertEquals("2N3F17VrrVxvvb1x1K4zfwLaYuebCf9ZtHp", $address);

        // get the 2nd address again
        $this->assertEquals("2N3F17VrrVxvvb1x1K4zfwLaYuebCf9ZtHp", $wallet->getAddress("M/44'/1'/9999'/0/1"));

        $this->assertGreaterThan(0, $wallet->doDiscovery()['confirmed']);

        $txHash = $wallet->pay([
            "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy" => BlocktrailSDK::toSatoshi(0.001)
        ]);

        $this->assertTrue(!!$txHash);

        sleep(2); // sleep to wait for the TX to be processed

        try {
            $tx = $client->transaction($txHash);
        } catch (ObjectNotFound $e) {
            $this->fail("check for tx[{$txHash}]");
        }

        $this->assertTrue(!!$tx, "check for tx[{$txHash}]");
    }

    /**
     * same wallet as testWallet but with a different password should result in a completely different wallet!
     */
    public function testWalletBadPassword() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createTestWallet($client, $identifier, "password2");
        $this->wallets[] = $wallet; // store for cleanup

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/44'/1'/9999'/0/0", $path);
        $this->assertEquals("2NFaCPAFbPLVLkd5qimPysQxMs6anXdNHv2", $address);

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/44'/1'/9999'/0/1", $path);
        $this->assertEquals("2N9t7yVa4q5VMdiUZttZgmzFhYmDMrSxeeU", $address);

        $this->assertEquals(0, $wallet->doDiscovery()['confirmed']);
    }

    public function testNewBlankWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();

        /**
         * @var $wallet \Blocktrail\SDK\Wallet
         */
        try {
            $wallet = $client->initWallet($identifier, "password");
            $this->fail("New wallet with ID [{$identifier}] already exists...");
        } catch (ObjectNotFound $e) {
            list($wallet, $backupMnemonic) = $client->createNewWallet($identifier, "password", 9999);
            // $this->assertEquals(0, $wallet->doDiscovery()['confirmed']);
        }

        $wallet = $client->initWallet($identifier, "password");
        $this->wallets[] = $wallet; // store for cleanup

        $this->assertEquals(0, $wallet->getBalance()[0]);

        try {
            $wallet->pay([
                "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy" => BlocktrailSDK::toSatoshi(0.001)
            ]);
            $this->fail("Wallet without balance is able to pay...");
        } catch (\Exception $e) {
            $this->assertTrue(!!$e);
        }

        /*
         * init same wallet by with bad password
         */
        try {
            $wallet = $client->initWallet($identifier, "password2", 9999);
            $this->fail("Wallet with bad pass initialized");
        } catch (\Exception $e) {
            $this->assertTrue(!!$e);
        }
    }
}
