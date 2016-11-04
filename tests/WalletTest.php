<?php

namespace Blocktrail\SDK\Tests;

\error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Buffertools\Buffer;

use Blocktrail\CryptoJSAES\CryptoJSAES;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Blocktrail;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\BlocktrailSDKInterface;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\SignInfo;
use Blocktrail\SDK\TransactionBuilder;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletPath;
use Blocktrail\SDK\WalletV1;
use Blocktrail\SDK\WalletV2;
use Blocktrail\SDK\WalletV3;

/**
 * Class WalletTest
 *
 * ! IMPORTANT NOTE !
 * throughout the test cases we use key_index=9999 to force an insecure development key on the API side
 *  this insecure key is used instead of the normal one so that we can reproduce the result on our staging environment
 *  without our private keys having to leaving our safe production environment
 *
 *
 * @package Blocktrail\SDK\Tests
 */
class WalletTest extends \PHPUnit_Framework_TestCase {
    const DEFAULT_WALLET_VERSION = 'v3';
    const DEFAULT_WALLET_INSTANCE = WalletV3::class;

    /**
     * @var Wallet[]
     */
    protected $wallets = [];

    /**
     * setup an instance of BlocktrailSDK
     *
     * @return BlocktrailSDK
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

    protected function setUp() {
        $this->setupBlocktrailSDK();
    }

    protected function cleanUp() {
        foreach ($this->wallets as $wallet) {
            try {
                if ($wallet->isLocked()) {
                    $wallet->unlock(['passphrase' => 'password']);
                }
                $wallet->deleteWallet(true);
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

    /**
     * initial setup to create the wallets that we use
     */
    public function testSetup() {
        // $client = $this->setupBlocktrailSDK();

        // wallet used for testing sending a transaction
        //  - we reuse this wallet because doing discovery everytime is a pain
        // $this->createTransactionTestWallet($client);

        // wallet used for
        //  * testing keyIndex upgrade
        //  * discovery
        //  * bad password
        //  - we recreate this wallet everytime because we need to be able to upgrade it again
        //  - so it should contain a little bit of BTC to be found by discovery
        // $this->createDiscoveryTestWallet($client, "unittest-discovery");

        // wallet used for wallet recovery testing
        //  - we recreate this wallet everytime because we need to be able to upgrade it again
        //  - so it should contain a little bit of BTC to be found by discovery
        //    it should be spread across small gaps to test the recovery process
        // $this->createRecoveryTestWallet($client);
    }

    protected function createDiscoveryTestWallet(BlocktrailSDKInterface $client, $identifier, $passphrase = "password") {
        $primaryMnemonic = "give pause forget seed dance crawl situate hole kingdom";
        $backupMnemonic = "give pause forget seed dance crawl situate hole course";

        return $this->_createTestWallet($client, $identifier, $passphrase, $primaryMnemonic, $backupMnemonic);
    }

    protected function createTransactionTestWallet(BlocktrailSDKInterface $client, $identifier = "unittest-transaction") {
        $primaryMnemonic = "give pause forget seed dance crawl situate hole keen";
        $backupMnemonic = "give pause forget seed dance crawl situate hole give";
        $passphrase = "password";

        return $this->_createTestWallet($client, $identifier, $passphrase, $primaryMnemonic, $backupMnemonic);
    }

    protected function createRecoveryTestWallet(BlocktrailSDKInterface $client, $identifier = "unittest-wallet-recovery") {
        $primaryMnemonic = "give pause forget seed dance crawl situate hole join";
        $backupMnemonic = "give pause forget seed dance crawl situate hole crater";
        $passphrase = "password";

        return $this->_createTestWallet($client, $identifier, $passphrase, $primaryMnemonic, $backupMnemonic);
    }

    protected function _createTestWallet(BlocktrailSDKInterface $client, $identifier, $passphrase, $primaryMnemonic, $backupMnemonic, $readOnly = false) {
        $walletPath = WalletPath::create(9999);

        $secret = "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff";
        $encryptedSecret = CryptoJSAES::encrypt($secret, $passphrase);

        // still using BIP39 to get seedhex to keep all fixtures the same
        $seed = (new Bip39SeedGenerator())->getSeed($primaryMnemonic, $passphrase);
        $primaryPrivateKey = BIP32Key::create(HierarchicalKeyFactory::fromEntropy($seed), "m");

        $primaryPublicKey = $primaryPrivateKey->buildKey((string)$walletPath->keyIndexPath()->publicPath());
        $encryptedPrimarySeed = CryptoJSAES::encrypt(base64_encode($seed->getBinary()), $secret);

        // still using BIP39 to get seedhex to keep all fixtures the same
        $backupPrivateKey = BIP32Key::create(HierarchicalKeyFactory::fromEntropy((new Bip39SeedGenerator())->getSeed($backupMnemonic, "")), "m");
        $backupPublicKey = $backupPrivateKey->buildKey("M");

        $testnet = true;

        $checksum = $primaryPrivateKey->publicKey()->getAddress()->getAddress();

        $result = $client->storeNewWalletV2(
            $identifier,
            $primaryPublicKey->tuple(),
            $backupPublicKey->tuple(),
            $encryptedPrimarySeed,
            $encryptedSecret,
            false,
            $checksum,
            9999
        );

        $blocktrailPublicKeys = $result['blocktrail_public_keys'];
        $keyIndex = $result['key_index'];

        $wallet = new WalletV2(
            $client,
            $identifier,
            $encryptedPrimarySeed,
            $encryptedSecret,
            [$keyIndex => $primaryPublicKey],
            $backupPublicKey,
            $blocktrailPublicKeys,
            $keyIndex,
            'bitcoin',
            $testnet,
            $checksum
        );

        if (!$readOnly) {
            $wallet->unlock(['password' => $passphrase]);
        }

        return $wallet;
    }

    public function testBIP32() {
        $masterkey = HierarchicalKeyFactory::fromExtended("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ");

        $this->assertEquals("022f6b9339309e89efb41ecabae60e1d40b7809596c68c03b05deb5a694e33cd26", $masterkey->getPublicKey()->getHex());
        $this->assertEquals("tpubDAtJthHcm9MJwmHp4r2UwSTmiDYZWHbQUMqySJ1koGxQpRNSaJdyL2Ab8wwtMm5DsMMk3v68299LQE6KhT8XPQWzxPLK5TbTHKtnrmjV8Gg", $masterkey->derivePath("0")->toExtendedKey());
        $this->assertEquals("tpubDDfqpEKGqEVa5FbdLtwezc6Xgn81teTFFVA69ZfJBHp4UYmUmhqVZMmqXeJBDahvySZrPjpwMy4gKfNfrxuFHmzo1r6srB4MrsDKWbwEw3d", $masterkey->derivePath("0/0")->toExtendedKey());

        $this->assertEquals(
            "tpubDHNy3kAG39ThyiwwsgoKY4iRenXDRtce8qdCFJZXPMCJg5dsCUHayp84raLTpvyiNA9sXPob5rgqkKvkN8S7MMyXbnEhGJMW64Cf4vFAoaF",
            HierarchicalKeyFactory::fromEntropy(Buffer::hex("000102030405060708090a0b0c0d0e0f"))->derivePath("M/0'/1/2'/2/1000000000")->toExtendedPublicKey()
        );
    }

    public function testCreateWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createTransactionTestWallet($client, $identifier);
        $this->wallets[] = $wallet; // store for cleanup

        $wallets = $client->allWallets();
        $this->assertTrue(count($wallets) > 0);

        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key()->toExtendedKey());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key()->toExtendedKey());

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

        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertTrue(strpos($path, "M/9999'/0/") === 0);
        $this->assertEquals($address, AddressFactory::fromString($address)->getAddress());
    }

    public function testNormalizeOutputStruct() {
        $expected = [['address' => 'address1', 'value' => 'value1'], ['address' => 'address2', 'value' => 'value2']];

        $this->assertEquals($expected, Wallet::normalizeOutputsStruct(['address1' => 'value1', 'address2' => 'value2']));
        $this->assertEquals($expected, Wallet::normalizeOutputsStruct([['address1', 'value1'], ['address2', 'value2']]));
        $this->assertEquals($expected, Wallet::normalizeOutputsStruct($expected));

        // duplicate address
        $expected = [['address' => 'address1', 'value' => 'value1'], ['address' => 'address1', 'value' => 'value2']];

        // not possible, keyed by address
        // $this->assertEquals($expected, Wallet::normalizeOutputsStruct(['address1' => 'value1', 'address1' => 'value2']));
        $this->assertEquals($expected, Wallet::normalizeOutputsStruct([['address1', 'value1'], ['address1', 'value2']]));
        $this->assertEquals($expected, Wallet::normalizeOutputsStruct($expected));
    }

    /**
     * this test requires / asumes that the test wallet it uses contains a balance
     *
     * we keep the wallet topped off with some coins,
     * but if some funny guy ever empties it or if you use your own API key to run the test then it needs to be topped off again
     *
     * @throws \Exception
     */
    public function testWalletTransaction() {
        $client = $this->setupBlocktrailSDK();

        /** @var Wallet $wallet */
        $wallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "passphrase" => "password"
        ]);

        $this->assertEquals("unittest-transaction", $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key()->toExtendedKey());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key()->toExtendedKey());

        list($confirmed, $unconfirmed) = $wallet->getBalance();
        $this->assertGreaterThan(0, $confirmed + $unconfirmed, "positive unconfirmed balance");
        $this->assertGreaterThan(0, $confirmed, "positive confirmed balance");

        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertTrue(strpos($path, "M/9999'/0/") === 0);
        $this->assertTrue(AddressFactory::fromString($address)->getAddress() == $address);

        ///*
        $value = BlocktrailSDK::toSatoshi(0.0002);
        $txHash = $wallet->pay([$address => $value,], null, false, true, Wallet::FEE_STRATEGY_BASE_FEE);

        $this->assertTrue(!!$txHash);

        $tx = $this->getTx($client, $txHash);

        $this->assertEquals($txHash, $tx['hash']);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $tx['total_fee']);
        $this->assertTrue(count($tx['outputs']) <= 2);
        $this->assertTrue(in_array($value, array_column($tx['outputs'], 'value')));
        //*/

        /*
         * do another TX but with a LOW_PRIORITY_FEE
         */
        $value = BlocktrailSDK::toSatoshi(0.0001);
        $txHash = $wallet->pay([$address => $value,], null, false, true, Wallet::FEE_STRATEGY_LOW_PRIORITY);

        $this->assertTrue(!!$txHash);

        sleep(1); // sleep to wait for the TX to be processed

        try {
            $tx = $client->transaction($txHash);
        } catch (ObjectNotFound $e) {
            $this->fail("404 for tx[{$txHash}] [" . gmdate('Y-m-d H:i:s') . "]");
        }

        $this->assertTrue(!!$tx, "check for tx[{$txHash}] [" . gmdate('Y-m-d H:i:s') . "]");
        $this->assertEquals($txHash, $tx['hash']);
        $this->assertTrue(count($tx['outputs']) <= 2);
        $this->assertTrue(in_array($value, array_column($tx['outputs'], 'value')));

        /*
         * do another TX but with a custom - high - fee
         */
        $value = BlocktrailSDK::toSatoshi(0.0001);
        $forceFee = BlocktrailSDK::toSatoshi(0.0010);
        $txHash = $wallet->pay([$address => $value,], null, false, true, Wallet::FEE_STRATEGY_BASE_FEE, $forceFee);

        $this->assertTrue(!!$txHash);

        $tx = $this->getTx($client, $txHash);

        $this->assertEquals($txHash, $tx['hash']);
        $this->assertEquals($forceFee, $tx['total_fee']);
        $this->assertTrue(count($tx['outputs']) <= 2);
        $this->assertTrue(in_array($value, array_column($tx['outputs'], 'value')));

        /*
         * do another TX with OP_RETURN using TxBuilder
         */
        $value = BlocktrailSDK::toSatoshi(0.0002);
        $moon = "MOOOOOOOOOOOOON!";
        $txBuilder = new TransactionBuilder();
        $txBuilder->randomizeChangeOutput(false);
        $txBuilder->addRecipient($address, $value);
        $txBuilder->addOpReturn($moon);

        $txBuilder = $wallet->coinSelectionForTxBuilder($txBuilder);

        $txHash = $wallet->sendTx($txBuilder);

        $this->assertTrue(!!$txHash);

        $tx = $this->getTx($client, $txHash);

        $this->assertTrue(!!$tx, "check for tx[{$txHash}] [" . gmdate('Y-m-d H:i:s') . "]");
        $this->assertEquals($txHash, $tx['hash']);
        $this->assertTrue(count($tx['outputs']) <= 3);
        $this->assertEquals($value, $tx['outputs'][0]['value']);
        $this->assertEquals(0, $tx['outputs'][1]['value']);
        $this->assertEquals("6a" /* OP_RETURN */ . "10" /* OP_PUSH16 */ . bin2hex($moon), $tx['outputs'][1]['script_hex']);
    }

    /**
     * this test requires / asumes that the test wallet it uses contains a balance
     *
     * we keep the wallet topped off with some coins,
     * but if some funny guy ever empties it or if you use your own API key to run the test then it needs to be topped off again
     *
     * @throws \Exception
     */
    public function testWalletTransactionWithoutMnemonics() {
        $client = $this->setupBlocktrailSDK();

        $primaryPrivateKey = HierarchicalKeyFactory::fromEntropy((new Bip39SeedGenerator())->getSeed("give pause forget seed dance crawl situate hole keen", "password"));

        $wallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "primary_private_key" => $primaryPrivateKey,
            "primary_mnemonic" => false, // explicitly set false because we're reusing unittest-transaction which has a mnemonic stored
        ]);

        $this->assertEquals("unittest-transaction", $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key()->toExtendedKey());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key()->toExtendedKey());

        list($confirmed, $unconfirmed) = $wallet->getBalance();
        $this->assertGreaterThan(0, $confirmed + $unconfirmed, "positive unconfirmed balance");
        $this->assertGreaterThan(0, $confirmed, "positive confirmed balance");

        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertTrue(strpos($path, "M/9999'/0/") === 0);
        $this->assertTrue(AddressFactory::fromString($address)->getAddress() == $address);

        $value = BlocktrailSDK::toSatoshi(0.0002);
        $txHash = $wallet->pay([
            $address => $value,
        ]);

        $this->assertTrue(!!$txHash);

        $tx = $this->getTx($client, $txHash);

        $this->assertEquals($txHash, $tx['hash']);
        $this->assertTrue(count($tx['outputs']) <= 2);
        $this->assertTrue(in_array($value, array_column($tx['outputs'], 'value')));
    }

    public function testDiscoveryAndKeyIndexUpgrade() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createDiscoveryTestWallet($client, $identifier);
        $this->wallets[] = $wallet; // store for cleanup

        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key()->toExtendedKey());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key()->toExtendedKey());

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2Mtfn5S9tVWnnHsBQixCLTsCAPFHvfhu6bM", $address);

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2NG49GDkm5qCYvDFi4cxAnkSho8qLbEz6C4", $address);

        list($confirmed, $unconfirmed) = $wallet->doDiscovery(50);
        $this->assertGreaterThan(0, $confirmed + $unconfirmed);

        $wallet->upgradeKeyIndex(10000);

        $this->assertEquals("tpubD9m9hziKhYQExWgzMUNXdYMNUtourv96sjTUS9jJKdo3EDJAnCBJooMPm6vGSmkNTNAmVt988dzNfNY12YYzk9E6PkA7JbxYeZBFy4XAaCp", $wallet->getBlocktrailPublicKey("m/10000")->key()->toExtendedKey());

        $this->assertEquals("2N9ZLKXgs12JQKXvLkngn7u9tsYaQ5kXJmk", $wallet->getAddressByPath("M/10000'/0/0"));

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/10000'/0/0", $path);
        $this->assertEquals("2N9ZLKXgs12JQKXvLkngn7u9tsYaQ5kXJmk", $address);
    }

    public function testListWalletTxsAddrs() {
        $client = $this->setupBlocktrailSDK();

        $wallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "passphrase" => "password"
        ]);

        $transactions = $wallet->transactions(1, 23);

        $this->assertEquals(23, count($transactions['data']));
        $this->assertEquals('2cb21783635a5f22e9934b8c3262146b42d251dfb14ee961d120936a6c40fe89', $transactions['data'][0]['hash']);

        $addresses = $wallet->addresses(1, 23);

        $this->assertEquals(23, count($addresses['data']));
        $this->assertEquals('2MzyKviSL6pnWxkbHV7ecFRE3hWKfzmT8WS', $addresses['data'][0]['address']);

        $utxos = $wallet->utxos(0, 23);

        $this->assertEquals(23, count($utxos['data']));
    }

    /**
     * same wallet as testWallet but with a different password should result in a completely different wallet!
     */
    public function testBadPasswordWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createDiscoveryTestWallet($client, $identifier, "badpassword");
        $this->wallets[] = $wallet; // store for cleanup

        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key()->toExtendedKey());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key()->toExtendedKey());

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2N9SGrV4NKRjdACYvHLPpy2oiPrxTPd44rg", $address);

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2NDq3DRy9E3YgHDA3haPJj3FtUS6V93avkf", $address);

        list($confirmed, $unconfirmed) = $wallet->doDiscovery(50);
        $this->assertEquals(0, $confirmed + $unconfirmed);
    }

    public function getVersionedWalletVectors()
    {
        return [
            [Wallet::WALLET_VERSION_V2, WalletV2::class],
            [Wallet::WALLET_VERSION_V3, WalletV3::class],
            [null, self::DEFAULT_WALLET_INSTANCE]
        ];
    }

    /**
     * Tests creation of both V2 and V3, and the 'default' wallets.
     *
     * @dataProvider getVersionedWalletVectors
     * @param $walletVersion
     * @param $expectedWalletClass
     */
    public function testVersionedWallet($walletVersion, $expectedWalletClass)
    {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();

        /**
         * @var $wallet \Blocktrail\SDK\Wallet
         */
        $e = null;
        $wallet = null;
        try {
            // Specify a wallet version if provided
            $wallet = $client->initWallet([
                "identifier" => $identifier,
                "passphrase" => "password"
            ]);
        } catch (ObjectNotFound $e) {
            $new = [
                "identifier" => $identifier,
                "passphrase" => "password",
                "key_index" => 9999
            ];
            // Specify the wallet version if provided in the test
            if ($walletVersion) {
                $new['wallet_version'] = $walletVersion;
            }

            list($wallet, $backupInfo) = $client->createNewWallet($new);
        }
        $this->assertTrue(!!$e, "New wallet with ID [{$identifier}] already exists...");
        $this->assertEquals($expectedWalletClass, get_class($wallet));

        $wallet = $client->initWallet([
            "identifier" => $identifier,
            "passphrase" => "password"
        ]);
        $this->wallets[] = $wallet; // store for cleanup

        $this->assertEquals($expectedWalletClass, get_class($wallet));

        $this->_testNewBlankWallet($wallet);

        /*
         * test password change
         */
        $wallet->unlock(['passphrase' => "password"]);
        $wallet->passwordChange("password2");
        $wallet = $client->initWallet([
            "identifier" => $identifier,
            "passphrase" => "password2"
        ]);

        $this->assertEquals($expectedWalletClass, get_class($wallet));
        $this->assertTrue(!$wallet->isLocked());
    }

    public function testNewBlankWalletV1() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();

        /**
         * @var $wallet \Blocktrail\SDK\Wallet
         */
        $e = null;
        try {
            $wallet = $client->initWallet([
                "identifier" => $identifier,
                "passphrase" => "password"
            ]);
        } catch (ObjectNotFound $e) {
            list($wallet, $backupInfo) = $client->createNewWallet([
                "identifier" => $identifier,
                "passphrase" => "password",
                "key_index" => 9999,
                "wallet_version" => Wallet::WALLET_VERSION_V1
            ]);
        }
        $this->assertTrue(!!$e, "New wallet with ID [{$identifier}] already exists...");
        $this->assertTrue($wallet instanceof WalletV1);

        $wallet = $client->initWallet([
            "identifier" => $identifier,
            "passphrase" => "password"
        ]);
        $this->wallets[] = $wallet; // store for cleanup

        $this->assertTrue($wallet instanceof WalletV1);

        $this->_testNewBlankWallet($wallet);
    }

    public function testNewBlankWithoutMnemonicsWalletV2() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $primaryPrivateKey = BIP32Key::create(HierarchicalKeyFactory::generateMasterKey(), 'm');
        $backupPublicKey = BIP32Key::create(HierarchicalKeyFactory::generateMasterKey()->toPublic(), 'M');

        /**
         * @var $wallet \Blocktrail\SDK\Wallet
         */
        $e = null;
        try {
            $wallet = $client->initWallet([
                "identifier" => $identifier
            ]);
        } catch (ObjectNotFound $e) {
            list($wallet, $backupInfo) = $client->createNewWallet([
                "wallet_version" => Wallet::WALLET_VERSION_V2,
                "identifier" => $identifier,
                "primary_private_key" => $primaryPrivateKey,
                "backup_public_key" => $backupPublicKey,
                "key_index" => 9999
            ]);
        }
        $this->assertTrue(!!$e, "New wallet with ID [{$identifier}] already exists...");
        $this->assertTrue($wallet instanceof WalletV2);

        $wallet = $client->initWallet([
            "identifier" => $identifier,
            "primary_private_key" => $primaryPrivateKey
        ]);
        $this->wallets[] = $wallet; // store for cleanup

        $this->assertTrue($wallet instanceof WalletV2);
        $this->assertEquals(0, $wallet->getBalance()[0]);

        $e = null;
        try {
            $wallet->pay([
                "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy" => BlocktrailSDK::toSatoshi(0.001)
            ]);
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e, "Wallet without balance is able to pay...");
    }

    public function testNewBlankWalletOldSyntax() {
        $client = $this->setupBlocktrailSDK();
        $identifier = $this->getRandomTestIdentifier();
        $default = self::DEFAULT_WALLET_INSTANCE;

        /**
         * @var $wallet \Blocktrail\SDK\Wallet
         */
        $e = null;
        try {
            $wallet = $client->initWallet($identifier, "password");
        } catch (ObjectNotFound $e) {
            list($wallet, $backupInfo) = $client->createNewWallet($identifier, "password", 9999);
        }
        $this->assertTrue(!!$e, "New wallet with ID [{$identifier}] already exists...");
        $this->assertTrue($wallet instanceof $default);

        $wallet = $client->initWallet([
            "identifier" => $identifier,
            "passphrase" => "password"
        ]);
        $this->wallets[] = $wallet; // store for cleanup

        $this->assertTrue($wallet instanceof $default);

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
            $wallet = $client->initWallet($identifier, "password2");
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e, "Wallet with bad pass initialized");
    }

    /**
     * helper to test blank wallet
     *
     * @param Wallet $wallet
     * @throws \Exception
     */
    protected function _testNewBlankWallet(Wallet $wallet) {
        $client = $this->setupBlocktrailSDK();

        $this->assertFalse($wallet->isLocked());
        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $this->assertTrue(!!$wallet->getNewAddress());

        $this->assertEquals(0, $wallet->getBalance()[0]);

        $e = null;
        try {
            $wallet->pay([
                "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy" => BlocktrailSDK::toSatoshi(0.001)
            ]);
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e && strpos($e->getMessage(), "lock") !== false, "Locked wallet is able to pay...");

        $e = null;
        try {
            $wallet->upgradeKeyIndex(10000);
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e && strpos($e->getMessage(), "lock") !== false, "Locked wallet is able to upgrade key index...");

        // repeat above but starting with readonly = true
        $wallet = $client->initWallet([
            "identifier" => $wallet->getIdentifier(),
            "readonly" => true
        ]);

        $this->assertTrue($wallet->isLocked());

        $this->assertTrue(!!$wallet->getNewAddress());

        $this->assertEquals(0, $wallet->getBalance()[0]);

        $e = null;
        try {
            $wallet->pay([
                "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy" => BlocktrailSDK::toSatoshi(0.001)
            ]);
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e && strpos($e->getMessage(), "lock") !== false, "Locked wallet is able to pay...");

        $e = null;
        try {
            $wallet->upgradeKeyIndex(10000);
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e && strpos($e->getMessage(), "lock") !== false, "Locked wallet is able to upgrade key index...");

        $wallet->unlock(['passphrase' => "password"]);
        $this->assertFalse($wallet->isLocked());

        $e = null;
        try {
            $wallet->pay([
                "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy" => BlocktrailSDK::toSatoshi(0.001)
            ]);
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e, "Wallet without balance is able to pay...");

        $wallet->upgradeKeyIndex(10000);

        /*
         * init same wallet by with bad password
         */
        $e = null;
        try {
            $wallet = $client->initWallet([
                "identifier" => $wallet->getIdentifier(),
                "passphrase" => "password2",
            ]);
        } catch (\Exception $e) {
        }
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
            $wallet = $client->initWallet([
                "identifier" => $identifier,
                "passphrase" => "password"
            ]);
        } catch (ObjectNotFound $e) {
            list($wallet, $backupInfo) = $client->createNewWallet([
                "identifier" => $identifier,
                "passphrase" => "password",
                "key_index" => 9999
            ]);
        }
        $this->assertTrue(!!$e, "New wallet with ID [{$identifier}] already exists...");

        $wallet = $client->initWallet([
            "identifier" => $identifier,
            "passphrase" => "password"
        ]);
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
        } catch (ObjectNotFound $e) {
        }
        $this->assertTrue(!!$e, "should throw exception");
    }

    public function testEstimateFee() {
        $this->assertEquals(30000, Wallet::estimateFee(1, 66));
        $this->assertEquals(40000, Wallet::estimateFee(2, 71));
    }

    public function testBuildTx() {
        $client = $this->setupBlocktrailSDK();

        $wallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "passphrase" => "password"
        ]);

        /*
         * test simple (real world TX) scenario
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.0001)
        ];
        /** @var Transaction $tx */
        /** @var SignInfo[] $signInfo */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder())
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(0.0001),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", BlocktrailSDK::toSatoshi(0.0001))
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0), $fee);

        // assert the input(s)
        $this->assertEquals(1, count($tx->getInputs()));
        $this->assertEquals("ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396", $tx->getInput(0)->getOutPoint()->getTxId()->getHex());
        $this->assertEquals(0, $tx->getInput(0)->getOutPoint()->getVout());
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($signInfo[0]->output->getScript())->getAddress());
        $this->assertEquals("a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87", $signInfo[0]->output->getScript()->getHex());
        $this->assertEquals(10000, $signInfo[0]->output->getValue());
        $this->assertEquals("M/9999'/0/1537", $signInfo[0]->path);
        $this->assertEquals(
            "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae",
            $signInfo[0]->redeemScript->getHex()
        );

        // assert the output(s)
        $this->assertEquals(1, count($tx->getOutputs()));
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(0)->getScript())->getAddress());
        $this->assertEquals(10000, $tx->getOutput(0)->getValue());

        /*
         * test trying to spend too much
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.0001)
        ];
        $e = null;
        try {
            /** @var Transaction $tx */
            list($tx, $signInfo) = $wallet->buildTx(
                (new TransactionBuilder())
                    ->spendOutput(
                        "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                        0,
                        BlocktrailSDK::toSatoshi(0.0001),
                        "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                        "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                        "M/9999'/0/1537",
                        "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                    )
                    ->addRecipient("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", BlocktrailSDK::toSatoshi(0.0002))
                    ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
            );
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e);
        $this->assertEquals("Atempting to spend more than sum of UTXOs", $e->getMessage());


        /*
         * test change
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(1)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder())
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(1),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NE2uSqCktMXfe512kTPrKPhQck7vMNvaGK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(1), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9999), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);
        $this->assertEquals(14, count($tx->getOutputs()));
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(13)->getScript())->getAddress());
        $this->assertEquals(99860000, $tx->getOutput(13)->getValue());

        /*
         * 1 input (1 * 294b) = 294b
         * 19 recipients (19 * 34b) = 646b
         *
         * size = 8b + 294b + 646b = 948b
         * + change output (34b) = 982b
         *
         * fee = 0.0001
         *
         * 1 - (19 * 0.0001) = 0.9981
         * change = 0.9980
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(1)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder())
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(1),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mw34qYu3rCmFkzZeNsDJ9aQri8HjmUZ6wY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MsTtsupHuqy6JvWUscn5HQ54EscLiXaPSF", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtR3Qa9eeYEpBmw3kLNywWGVmUuGjwRGXk", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N6GmkegBNA1D8wbHMLZFwxMPoNRjVnZgvv", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NBCPVQ6xX3KAVPKmGENH1eHhPwJzmN1Bpf", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NAHY321fSVz4wKnE4eWjyLfRmoauCrQpBD", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N2anz2GmZdrKNNeEZD7Xym8djepwnTqPXY", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(1), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9999), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);
        $this->assertEquals(20, count($tx->getOutputs()));
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(19)->getScript())->getAddress());
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9980), $tx->getOutput(19)->getValue());

        /*
         * test change output bumps size over 1kb, fee += 0.0001
         *
         * 1 input (1 * 294b) = 294b
         * 20 recipients (19 * 34b) = 680b
         *
         * size = 8b + 294b + 680b = 982b
         * + change output (34b) = 1006b
         *
         * fee = 0.0002
         * input = 1.0000
         * 1.0000 - (20 * 0.0001) = 0.9980
         * change = 0.9978
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(1)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder())
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(1),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mw34qYu3rCmFkzZeNsDJ9aQri8HjmUZ6wY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MsTtsupHuqy6JvWUscn5HQ54EscLiXaPSF", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtR3Qa9eeYEpBmw3kLNywWGVmUuGjwRGXk", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N6GmkegBNA1D8wbHMLZFwxMPoNRjVnZgvv", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NBCPVQ6xX3KAVPKmGENH1eHhPwJzmN1Bpf", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NAHY321fSVz4wKnE4eWjyLfRmoauCrQpBD", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N2anz2GmZdrKNNeEZD7Xym8djepwnTqPXY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mvs5ik3nC9RBho2kPcgi5Q62xxAE2Aryse", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(1), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9998), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0002), $fee);
        $this->assertEquals(21, count($tx->getOutputs()));
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(20)->getScript())->getAddress());
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9978), $tx->getOutput(20)->getValue());

        /*
         * test change
         *
         * 1 input (1 * 294b) = 294b
         * 20 recipients (19 * 34b) = 680b
         *
         * size = 8b + 294b + 680b = 982b
         * + change output (34b) = 1006b
         *
         * fee = 0.0001
         * input = 0.0021
         * 0.0021 - (20 * 0.0001) = 0.0001
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.0021)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder())
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(0.0021),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mw34qYu3rCmFkzZeNsDJ9aQri8HjmUZ6wY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MsTtsupHuqy6JvWUscn5HQ54EscLiXaPSF", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtR3Qa9eeYEpBmw3kLNywWGVmUuGjwRGXk", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N6GmkegBNA1D8wbHMLZFwxMPoNRjVnZgvv", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NBCPVQ6xX3KAVPKmGENH1eHhPwJzmN1Bpf", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NAHY321fSVz4wKnE4eWjyLfRmoauCrQpBD", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N2anz2GmZdrKNNeEZD7Xym8djepwnTqPXY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mvs5ik3nC9RBho2kPcgi5Q62xxAE2Aryse", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0021), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0020), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);
        $this->assertEquals(20, count($tx->getOutputs()));

        /*
         * test change output bumps size over 1kb, fee += 0.0001
         *  but change was < 0.0001 so better to just fee it all
         *
         * 1 input (1 * 294b) = 294b
         * 20 recipients (19 * 34b) = 680b
         *
         * input = 0.00219
         *
         * size = 8b + 294b + 680b = 982b
         * fee = 0.0001
         * 0.00219 - (20 * 0.0001) = 0.00019
         *
         * + change output (0.00009) (34b) = 1006b
         * fee = 0.0002
         * 0.00219 - (20 * 0.0001) = 0.00019
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.00219)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder())
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(0.00219),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mw34qYu3rCmFkzZeNsDJ9aQri8HjmUZ6wY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MsTtsupHuqy6JvWUscn5HQ54EscLiXaPSF", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtR3Qa9eeYEpBmw3kLNywWGVmUuGjwRGXk", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N6GmkegBNA1D8wbHMLZFwxMPoNRjVnZgvv", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NBCPVQ6xX3KAVPKmGENH1eHhPwJzmN1Bpf", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NAHY321fSVz4wKnE4eWjyLfRmoauCrQpBD", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N2anz2GmZdrKNNeEZD7Xym8djepwnTqPXY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mvs5ik3nC9RBho2kPcgi5Q62xxAE2Aryse", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.00219), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0020), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.00019), $fee);
        $this->assertEquals(20, count($tx->getOutputs()));

        /*
         * custom fee
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.002001)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder())
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(0.002001),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.002))
                ->setFee(BlocktrailSDK::toSatoshi(0.000001))
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.002001), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.002), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.000001), $fee);

        /*
         * multiple outputs same address
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.002)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder())
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(0.002),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0005))
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0005))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.002), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0019), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);

        $this->assertEquals("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", AddressFactory::fromOutputScript($tx->getOutput(0)->getScript())->getAddress());
        $this->assertEquals("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", AddressFactory::fromOutputScript($tx->getOutput(1)->getScript())->getAddress());
    }

    protected function getTx(BlocktrailSDK $client, $txId, $retries = 3) {
        sleep(1);

        for ($i = 0; $i < $retries; $i++) {
            try {
                $tx = $client->transaction($txId);
                return $tx;
            } catch (ObjectNotFound $e) {
                sleep(1); // sleep to wait for the TX to be processed
            }
        }

        $this->fail("404 for tx[{$txId}] [" . gmdate('Y-m-d H:i:s') . "]");
    }
}
