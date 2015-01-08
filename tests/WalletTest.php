<?php

namespace Blocktrail\SDK\Tests;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BIP39\BIP39;
use BitWasp\BitcoinLib\BitcoinLib;
use Blocktrail\SDK\Bitcoin\BIP44;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletPath;

/**
 * Class WalletTest
 *
 * ! IMPORTANT NODE !
 * throughout the test cases we use key_index=9999 to force an insecure development key on the API side
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
        $walletPath = WalletPath::WalletPath(9999);

        $primaryMnemonic = "give pause forget seed dance crawl situate hole keen";
        $backupMnemonic = "give pause forget seed dance crawl situate hole give";

        $seed = BIP39::mnemonicToSeedHex($primaryMnemonic, $passphrase);
        $primaryPrivateKey = BIP32::master_key($seed, 'bitcoin', true);
        $primaryPublicKey = BIP32::build_key($primaryPrivateKey, (string)$walletPath->keyIndexPath()->publicPath());

        $seed = BIP39::mnemonicToSeedHex($backupMnemonic, "");
        $backupPrivateKey = BIP32::master_key($seed, 'bitcoin', true);
        $backupPublicKey = BIP32::build_key($backupPrivateKey, (string)"M");

        $testnet = true;

        $result = $client->_createNewWallet($identifier, $primaryPublicKey, $backupPublicKey, $primaryMnemonic, "", 9999);

        $blocktrailPublicKeys = $result['blocktrail_public_keys'];
        $keyIndex = $result['key_index'];

        return new Wallet($client, $identifier, $primaryPrivateKey, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $testnet);
    }

    public function testWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createTestWallet($client, $identifier);
        $this->wallets[] = $wallet; // store for cleanup

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2MtfSwmdDZYNydgEkBJdu7izu2fmeVpnuLe", $address);

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2MsPQJoR5tmne7VrQVZKGmLWerrkrAR1yh5", $address);

        // get the 2nd address again
        $this->assertEquals("2MsPQJoR5tmne7VrQVZKGmLWerrkrAR1yh5", $wallet->getAddress("M/9999'/0/1"));

        $balance = $wallet->doDiscovery();
        $this->assertGreaterThan(0, $balance['confirmed'] + $balance['unconfirmed']);

        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertTrue(strpos($path, "M/9999'/0/") === 0);
        $this->assertTrue(BitcoinLib::validate_address($address, false, null));

        $txHash = $wallet->pay([
            $address => BlocktrailSDK::toSatoshi(0.0001)
        ]);

        $this->assertTrue(!!$txHash);

        sleep(5); // sleep to wait for the TX to be processed

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
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2N3qxHeFQrg1NcvXspnPdhRva6cZeyNWDDi", $address);

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2MsKsLeJUb6DKiiDdjskTUz8qyEuPgJh8Kp", $address);

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
            list($wallet, $primaryMnemonic, $backupMnemonic) = $client->createNewWallet($identifier, "password", 9999);
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
