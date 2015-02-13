<?php

namespace Blocktrail\SDK\Tests;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BIP39\BIP39;
use BitWasp\BitcoinLib\BitcoinLib;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\BlocktrailSDKInterface;
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
     * @return BlocktrailSDKInterface
     */
    public function setupBlocktrailSDK() {
        $apiKey = getenv('BLOCKTRAIL_SDK_APIKEY') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APIKEY';
        $apiSecret = getenv('BLOCKTRAIL_SDK_APISECRET') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APISECRET';
        $client = new BlocktrailSDK($apiKey, $apiSecret, "BTC", true, 'v1');

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
            try {
                $wallet->deleteWallet();
            } catch (ObjectNotFound $e) {
                // that's ok
            }
        }

        $this->wallets = [];
    }

    protected function getRandomTestIdentifier() {
        $identifier = md5(openssl_random_pseudo_bytes(128));
        $time = time();

        return "php-sdk-{$time}-{$identifier}";
    }

    protected function createTestWallet(BlocktrailSDKInterface $client, $identifier, $passphrase = "password") {
        $walletPath = WalletPath::create(9999);

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

        return new Wallet($client, $identifier, $primaryMnemonic, $primaryPrivateKey, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $testnet);
    }

    public function testKeyIndexUpgrade() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createTestWallet($client, $identifier);
        $this->wallets[] = $wallet; // store for cleanup

        $wallets = $client->allWallets();
        $this->assertTrue(count($wallets) > 0);

        $this->assertEquals("give pause forget seed dance crawl situate hole keen", $wallet->getPrimaryMnemonic());
        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key());

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2MzyKviSL6pnWxkbHV7ecFRE3hWKfzmT8WS", $address);

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2N65RcfKHiKQcPGZAA2QVeqitJvAQ8HroHD", $address);

        $balance = $wallet->doDiscovery();
        $this->assertGreaterThan(0, $balance['confirmed'] + $balance['unconfirmed']);

        $wallet->upgradeKeyIndex(10000);

        $this->assertEquals("tpubD9m9hziKhYQExWgzMUNXdYMNUtourv96sjTUS9jJKdo3EDJAnCBJooMPm6vGSmkNTNAmVt988dzNfNY12YYzk9E6PkA7JbxYeZBFy4XAaCp", $wallet->getBlocktrailPublicKey("m/10000")->key());

        $this->assertEquals("2NDwndDJAdu8RGHB6L9xNBAbbZ6bMjFgErK", $wallet->getAddressByPath("M/10000'/0/0"));

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/10000'/0/0", $path);
        $this->assertEquals("2NDwndDJAdu8RGHB6L9xNBAbbZ6bMjFgErK", $address);

    }

    /**
     * this test requires / asumes that the test wallet it (re)creates contains a balance
     *
     * we keep the wallet topped off with some coins,
     * but if some funny guy ever empties it or if you use your own API key to run the test then it needs to be topped off again
     *
     * @throws \Exception
     */
    public function testWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createTestWallet($client, $identifier);
        $this->wallets[] = $wallet; // store for cleanup

        $wallets = $client->allWallets();
        $this->assertTrue(count($wallets) > 0);

        $this->assertEquals("give pause forget seed dance crawl situate hole keen", $wallet->getPrimaryMnemonic());
        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key());

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2MzyKviSL6pnWxkbHV7ecFRE3hWKfzmT8WS", $address);

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2N65RcfKHiKQcPGZAA2QVeqitJvAQ8HroHD", $address);

        // get another new path, directly from the SDK
        $this->assertEquals("M/9999'/0/2", $client->getNewDerivation($wallet->getIdentifier(), "M/9999'/0"));

        // get the 2nd address again
        $this->assertEquals("2N65RcfKHiKQcPGZAA2QVeqitJvAQ8HroHD", $wallet->getAddressByPath("M/9999'/0/1"));

        // get some more addresses
        $this->assertEquals("2MynrezSyqCq1x5dMPtRDupTPA4sfVrNBKq", $wallet->getAddressByPath("M/9999'/0/6"));
        $this->assertEquals("2N5eqrZE7LcfRyCWqpeh1T1YpMdgrq8HWzh", $wallet->getAddressByPath("M/9999'/0/44"));

        $balance = $wallet->doDiscovery();
        $this->assertGreaterThan(0, $balance['confirmed'] + $balance['unconfirmed']);

        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertTrue(strpos($path, "M/9999'/0/") === 0);
        $this->assertTrue(BitcoinLib::validate_address($address, false, null));

        $txHash = $wallet->pay([
            $address => BlocktrailSDK::toSatoshi(0.0001),
        ]);

        $this->assertTrue(!!$txHash);

        sleep(1); // sleep to wait for the TX to be processed

        try {
            $tx = $client->transaction($txHash);
        } catch (ObjectNotFound $e) {
            $this->fail("404 for tx[{$txHash}] [" . gmdate('Y-m-d H:i:s') . "]");
        }

        $this->assertTrue(!!$tx, "check for tx[{$txHash}] [" . gmdate('Y-m-d H:i:s') . "]");
    }

    /**
     * same wallet as testWallet but with a different password should result in a completely different wallet!
     */
    public function testBadPasswordWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createTestWallet($client, $identifier, "password2");
        $this->wallets[] = $wallet; // store for cleanup

        $wallets = $client->allWallets();
        $this->assertTrue(count($wallets) > 0);

        $this->assertEquals("give pause forget seed dance crawl situate hole keen", $wallet->getPrimaryMnemonic());
        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key());

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2N7UAbCwVcbno9W42Yz6KQAjyLVy2NqYN3Z", $address);

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2N9ZFKNnCamy9sJYZiH9uMrbXaDNw8D8zcb", $address);

        $this->assertEquals(0, $wallet->doDiscovery()['confirmed']);
    }

    public function testNewBlankWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();

        /**
         * @var $wallet \Blocktrail\SDK\Wallet
         */
        $e = null;
        try {
            $wallet = $client->initWallet($identifier, "password");
        } catch (ObjectNotFound $e) {
            list($wallet, $primaryMnemonic, $backupMnemonic, $blocktrailPublicKeys) = $client->createNewWallet($identifier, "password", 9999);
            // $this->assertEquals(0, $wallet->doDiscovery()['confirmed']);
        }
        $this->assertTrue(!!$e, "New wallet with ID [{$identifier}] already exists...");

        $wallet = $client->initWallet($identifier, "password");
        $this->wallets[] = $wallet; // store for cleanup

        $this->assertEquals(0, $wallet->getBalance()[0]);

        $e = null;
        try {
            $wallet->pay([
                "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy" => BlocktrailSDK::toSatoshi(0.001)
            ]);
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e, "Wallet without balance is able to pay...");

        /*
         * init same wallet by with bad password
         */
        $e = null;
        try {
            $wallet = $client->initWallet($identifier, "password2", 9999);
        } catch (\Exception $e) {}
        $this->assertTrue(!!$e, "Wallet with bad pass initialized");
    }

    public function testWebhookForWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();

        /**
         * @var $wallet \Blocktrail\SDK\Wallet
         */
        $e = null;
        try {
            $wallet = $client->initWallet($identifier, "password");
        } catch (ObjectNotFound $e) {
            list($wallet, $primaryMnemonic, $backupMnemonic, $blocktrailPublicKeys) = $client->createNewWallet($identifier, "password", 9999);
            // $this->assertEquals(0, $wallet->doDiscovery()['confirmed']);
        }
        $this->assertTrue(!!$e, "New wallet with ID [{$identifier}] already exists...");

        $wallet = $client->initWallet($identifier, "password");
        $this->wallets[] = $wallet; // store for cleanup

        $wallets = $client->allWallets();
        $this->assertTrue(count($wallets) > 0);

        $this->assertEquals(0, $wallet->getBalance()[0]);

        // create webhook with default identifier
        $webhook = $wallet->setupWebhook("https://www.blocktrail.com/webhook-test");
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $webhook["url"]);
        $this->assertEquals("WALLET-{$wallet->getIdentifier()}", $webhook["identifier"]);

        // delete webhook
        $this->assertTrue($wallet->deleteWebhook());

        // create webhook with custom identifier
        $webhookIdentifier = $this->getRandomTestIdentifier();
        $webhook = $wallet->setupWebhook("https://www.blocktrail.com/webhook-test", $webhookIdentifier);
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $webhook["url"]);
        $this->assertEquals($webhookIdentifier, $webhook["identifier"]);

        // get events
        $events = $client->getWebhookEvents($webhookIdentifier);

        // events should still be 0 at this point
        // @TODO: $this->assertEquals(0, count($events['data']));

        // create address
        $addr1 = $wallet->getNewAddress();

        $this->assertTrue($wallet->deleteWebhook($webhookIdentifier));

        // create webhook with custom identifier
        $webhookIdentifier = $this->getRandomTestIdentifier();
        $webhook = $wallet->setupWebhook("https://www.blocktrail.com/webhook-test", $webhookIdentifier);
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $webhook["url"]);
        $this->assertEquals($webhookIdentifier, $webhook["identifier"]);

        // get events
        $events = $client->getWebhookEvents($webhookIdentifier);

        // event for first address should already be there
        // @TODO: $this->assertEquals(1, count($events['data']));
        $this->assertTrue(count(array_diff([$addr1], array_column($events['data'], 'address'))) == 0);

        // create address
        $addr2 = $wallet->getNewAddress();

        // get events
        $events = $client->getWebhookEvents($webhookIdentifier);

        // event for 2nd address should be there too
        // @TODO: $this->assertEquals(2, count($events['data']));
        // using inarray because order isn't deterministic
        $this->assertTrue(count(array_diff([$addr1, $addr2], array_column($events['data'], 'address'))) == 0);

        // delete wallet (should delete webhook too)
        $wallet->deleteWallet();

        // check if webhook is deleted
        $e = null;
        try {
            $this->assertFalse($wallet->deleteWebhook($webhookIdentifier));
        } catch (ObjectNotFound $e) {}
        $this->assertTrue(!!$e, "should throw exception");
    }

    public function testListWalletTxsAddrs() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createTestWallet($client, $identifier);
        $this->wallets[] = $wallet; // store for cleanup

        $wallets = $client->allWallets();
        $this->assertTrue(count($wallets) > 0);

        $this->assertEquals("give pause forget seed dance crawl situate hole keen", $wallet->getPrimaryMnemonic());
        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key());

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2MzyKviSL6pnWxkbHV7ecFRE3hWKfzmT8WS", $address);

        $balance = $wallet->doDiscovery();
        $this->assertGreaterThan(0, $balance['confirmed'] + $balance['unconfirmed']);

        $transactions = $wallet->transactions(1, 23);

        $this->assertEquals(23, count($transactions['data']));
        $this->assertEquals('2cb21783635a5f22e9934b8c3262146b42d251dfb14ee961d120936a6c40fe89', $transactions['data'][0]['hash']);

        $addresses = $wallet->addresses(1, 23);

        $this->assertEquals(23, count($addresses['data']));
        $this->assertEquals('2MzyKviSL6pnWxkbHV7ecFRE3hWKfzmT8WS', $addresses['data'][0]['address']);
    }

    public function testEstimateFee() {
        $this->assertEquals(30000, Wallet::estimateFee(1, 66));
        $this->assertEquals(40000, Wallet::estimateFee(2, 71));
    }
}
