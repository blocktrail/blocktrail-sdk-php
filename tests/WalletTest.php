<?php

namespace Blocktrail\SDK\Tests;

\error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Script\ScriptType;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Buffertools\Buffer;

use Blocktrail\CryptoJSAES\CryptoJSAES;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Blocktrail;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\BlocktrailSDKInterface;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\SignInfo;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletInterface;
use Blocktrail\SDK\WalletPath;
use Blocktrail\SDK\WalletScript;
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
class WalletTest extends BlocktrailTestCase {
    const DEFAULT_WALLET_VERSION = 'v3';
    const DEFAULT_WALLET_INSTANCE = WalletV3::class;

    /**
     * @var Wallet[]
     */
    protected $wallets = [];

    /**
     * params are same defaults as BlocktrailSDK, except testnet, which is `true` for these tests
     * @see BlocktrailTestCase::setupBlocktrailSDK
     * @return BlocktrailSDK
     */
    public function setupBlocktrailSDK($network = 'BTC', $testnet = true, $apiVersion = 'v1', $apiEndpoint = null) {
        $apiKey = getenv('BLOCKTRAIL_SDK_APIKEY') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APIKEY';
        $apiSecret = getenv('BLOCKTRAIL_SDK_APISECRET') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APISECRET';

        $client = new BlocktrailSDK($apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint);

        return $client;
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
        $network = $client->getNetworkParams()->getNetwork();
        $seed = (new Bip39SeedGenerator())->getSeed($primaryMnemonic, $passphrase);
        $primaryPrivateKey = BIP32Key::create($network, HierarchicalKeyFactory::fromEntropy($seed), "m");

        $primaryPublicKey = $primaryPrivateKey->buildKey((string)$walletPath->keyIndexPath()->publicPath());
        $encryptedPrimarySeed = CryptoJSAES::encrypt(base64_encode($seed->getBinary()), $secret);

        // still using BIP39 to get seedhex to keep all fixtures the same
        $backupPrivateKey = BIP32Key::create($network, HierarchicalKeyFactory::fromEntropy((new Bip39SeedGenerator())->getSeed($backupMnemonic, "")), "m");
        $backupPublicKey = $backupPrivateKey->buildKey("M");

        $checksum = $primaryPrivateKey->publicKey()->getAddress()->getAddress($network);

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
            false,
            $checksum
        );

        if (!$readOnly) {
            $wallet->unlock(['password' => $passphrase]);
        }

        return $wallet;
    }

    public function testBIP32() {
        $network = NetworkFactory::bitcoinTestnet();
        $masterkey = HierarchicalKeyFactory::fromExtended("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $network);

        $this->assertEquals("022f6b9339309e89efb41ecabae60e1d40b7809596c68c03b05deb5a694e33cd26", $masterkey->getPublicKey()->getHex());
        $this->assertEquals("tpubDAtJthHcm9MJwmHp4r2UwSTmiDYZWHbQUMqySJ1koGxQpRNSaJdyL2Ab8wwtMm5DsMMk3v68299LQE6KhT8XPQWzxPLK5TbTHKtnrmjV8Gg", $masterkey->derivePath("0")->toExtendedKey($network));
        $this->assertEquals("tpubDDfqpEKGqEVa5FbdLtwezc6Xgn81teTFFVA69ZfJBHp4UYmUmhqVZMmqXeJBDahvySZrPjpwMy4gKfNfrxuFHmzo1r6srB4MrsDKWbwEw3d", $masterkey->derivePath("0/0")->toExtendedKey($network));

        $this->assertEquals(
            "tpubDHNy3kAG39ThyiwwsgoKY4iRenXDRtce8qdCFJZXPMCJg5dsCUHayp84raLTpvyiNA9sXPob5rgqkKvkN8S7MMyXbnEhGJMW64Cf4vFAoaF",
            HierarchicalKeyFactory::fromEntropy(Buffer::hex("000102030405060708090a0b0c0d0e0f"))->derivePath("M/0'/1/2'/2/1000000000")->toExtendedPublicKey($network)
        );
    }

    public function testCreateWallet() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();
        $wallet = $this->createTransactionTestWallet($client, $identifier);
        $this->cleanupData['wallets'][] = $wallet; // store for cleanup

        $wallets = $client->allWallets();
        $this->assertTrue(count($wallets) > 0);

        $network = $client->getNetworkParams()->getNetwork();
        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key()->toExtendedKey($network));
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key()->toExtendedKey($network));

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
        $this->assertEquals($address, AddressFactory::fromString($address, $network)->getAddress($network));
    }

    private function checkP2sh(ScriptInterface $spk, ScriptInterface $rs) {
        $spkData = (new OutputClassifier())->decode($spk);
        $this->assertEquals(ScriptType::P2SH, $spkData->getType());

        $scriptHash = $rs->getScriptHash();
        $this->assertTrue($spkData->getSolution()->equals($scriptHash));
    }

    private function checkP2wsh(ScriptInterface $wp, ScriptInterface $ws) {
        $spkData = (new OutputClassifier())->decode($wp);
        $this->assertEquals(ScriptType::P2WSH, $spkData->getType());

        $scriptHash = $ws->getWitnessScriptHash();
        $this->assertTrue($spkData->getSolution()->equals($scriptHash));
    }

    private function isBase58Address(WalletScript $script) {

        try {
            $addr = $script->getAddress();
            $ok = $addr instanceof PayToPubKeyHashAddress || $addr instanceof ScriptHashAddress;
        } catch (\Exception $e) {
            $ok = false;
        }

        $this->assertTrue($ok, "address should be a base58 type");

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

    public function testChecksBackupKey() {
        $identifier = "unittest-transaction";
        $password = 'password';

        $client = $this->setupBlocktrailSDK();

        $backupMnemonic = "give pause forget seed dance crawl situate hole give";
        $network = $client->getNetworkParams()->getNetwork();
        $backupPrivateKey = BIP32Key::create($network, HierarchicalKeyFactory::fromEntropy((new Bip39SeedGenerator())->getSeed($backupMnemonic, "")), "m");
        $backupKey = $backupPrivateKey->buildKey("M")->key()->toExtendedPublicKey($network);

        try {
            $client->initWallet([
                'identifier' => $identifier,
                'password' => $password,
                'check_backup_key' => []
            ]);
            $this->fail("this should have caused an exception");
        } catch (\Exception $e) {
            $this->assertEquals("check_backup_key should be a string (the xpub)", $e->getMessage());
        }

        try {
            $client->initWallet([
                'identifier' => $identifier,
                'password' => $password,
                'check_backup_key' => 'for demonstration purposes only'
            ]);
            $this->fail("test should have caused an exception");
        } catch (\Exception $e) {
            $this->assertEquals("Backup key returned from server didn't match our own", $e->getMessage());
        }

        try {
            $init = $client->initWallet([
                'identifier' => $identifier,
                'password' => $password,
            ]);
            $this->assertEquals($backupKey, $init->getBackupKey()[0]);
        } catch (\Exception $e) {
            $this->fail("this should not fail");
        }

        try {
            $init = $client->initWallet([
                'identifier' => $identifier,
                'password' => $password,
                'check_backup_key' => $backupKey,
            ]);
            $this->assertEquals($backupKey, $init->getBackupKey()[0]);
        } catch (\Exception $e) {
            $this->fail("this should not fail");
        }
    }

    /**
     * this test requires / asumes that the test wallet it uses contains a balance
     *
     * we keep the wallet topped off with some coins,
     * but if some funny guy ever empties it or if you use your own API key to run the test then it needs to be topped off again
     *
     * @throws \Exception
     */
    public function testSegwitWalletTransaction()
    {
        $client = $this->setupBlocktrailSDK();

        // This test was edited so it works out
        // even if segwit isn't enabled yet

        /** @var Wallet $segwitwallet */
        $segwitwallet = $client->initWallet([
            "identifier" => "unittest-transaction-sw",
            "passphrase" => "password"
        ]);

        $this->assertTrue($segwitwallet->isSegwit());

        $network = $client->getNetworkParams()->getNetwork();
        list ($path, $address) = $segwitwallet->getNewAddressPair();
        $addrObj = AddressFactory::fromString($address, $network)->getAddress($network);

        $segwitScript = $segwitwallet->getWalletScriptByPath($path);
        $this->assertEquals($segwitScript->getAddress()->getAddress($network), $address);

        // Send back to unittest-transaction-sw

        $rand = random_int(1, 3);

        $builder = $segwitwallet->createTransaction()
            ->addRecipient($address, BlocktrailSDK::toSatoshi(0.015) * $rand)
            ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE);

        $builder = $segwitwallet->coinSelectionForTxBuilder($builder);

        $this->assertTrue(count($builder->getUtxos()) > 0);
        $this->assertEquals(1, count($builder->getOutputs()));

        $txHash = $segwitwallet->sendTx($builder);

        $tx = $this->getTx($client, $txHash);

        $this->assertTrue(count($tx['outputs']) === 1 || count($tx['outputs']) === 2);

        $checkChange = count($tx['outputs']) > 1;

        $changeIdx = null;
        $utxoIdx = null;
        foreach ($tx['outputs'] as $i => $output) {
            if ($output['address'] === $address) {
                $utxoIdx = $i;
            }
            if ($checkChange && $output['address'] !== $address) {
                $changeIdx = $i;
            }
        }

        if ($checkChange) {
            $pathForChangeAddr = $segwitwallet->getPathForAddress($tx['outputs'][$changeIdx]['address']);
            $this->assertTrue(strpos("M/9999'/" . Wallet::CHAIN_BTC_SEGWIT . "/", $pathForChangeAddr) !== -1);
        }

        $addresses = implode(" ", array_column($tx['outputs'], 'address'));
        $this->assertNotNull($utxoIdx, "should have found utxo paying {$address} in $addresses");

        $utxo = $tx['outputs'][$utxoIdx];

        $spendSegwit = $segwitwallet->createTransaction()
            ->addRecipient($segwitwallet->getNewAddress(), BlocktrailSDK::toSatoshi(0.010) * $rand)
            ->spendOutput($tx['hash'], $utxoIdx, $utxo['value'], $utxo['address'], $utxo['script_hex'], $path)
            ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE);

        $txid = $segwitwallet->sendTx($spendSegwit);

        $this->assertTrue(strlen($txid) === 64);

        $tx = $this->getTx($client, $txid);
        $this->assertEquals(1, count($tx['inputs']));

    }

    /**
     * this test requires / asumes that the test wallet it uses contains a balance
     *
     * we keep the wallet topped off with some coins,
     * but if some funny guy ever empties it or if you use your own API key to run the test then it needs to be topped off again
     *
     * @throws \Exception
     */
    public function testCheckRejectsInvalidChainINdex()
    {
        $client = $this->setupBlocktrailSDK();

        $unittestWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "passphrase" => "password"
        ]);

        $exception = BlocktrailSDKException::class;
        $msg = "Chain index is invalid - should be an integer";
        $this->setExpectedException($exception, $msg);

        $unittestWallet->getNewAddress('');
    }

    /**
     * this test requires / asumes that the test wallet it uses contains a balance
     *
     * we keep the wallet topped off with some coins,
     * but if some funny guy ever empties it or if you use your own API key to run the test then it needs to be topped off again
     *
     * @throws \Exception
     */
    public function testSendToBech32()
    {
        $client = $this->setupBlocktrailSDK();

        $unittestWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "passphrase" => "password"
        ]);

        $pubKeyHash = Buffer::hex('5d6f02f47dc6c57093df246e3742cfe1e22ab410');
        $wp = WitnessProgram::v0($pubKeyHash);
        $addr = new SegwitAddress($wp);

        $random = random_int(1, 4);
        $receiveAmount = BlocktrailSDK::toSatoshi(0.0001) * $random;

        $network = $client->getNetworkParams()->getNetwork();
        $builder = $unittestWallet->createTransaction()
            ->addRecipient($addr->getAddress($network), $receiveAmount)
            ->setFeeStrategy(Wallet::FEE_STRATEGY_OPTIMAL);

        $builder = $unittestWallet->coinSelectionForTxBuilder($builder, false, true);
        $this->assertTrue(count($builder->getUtxos()) > 0);
        $this->assertTrue(count($builder->getOutputs()) <= 2);

        /**
         * @var TransactionInterface $tx
         * @var SignInfo $signInfo
         */
        list ($tx, $signInfo) = $unittestWallet->buildTx($builder);

        $found = false;
        foreach ($tx->getOutputs() as $output) {
            if ($output->getScript()->equals($wp->getScript())) {
                $found = true;
                $this->assertEquals($receiveAmount, $output->getValue());
            }
        }

        $this->assertTrue($found, 'should find the address with our output script');

    }

    /**
     * this test requires / asumes that the test wallet it uses contains a balance
     *
     * we keep the wallet topped off with some coins,
     * but if some funny guy ever empties it or if you use your own API key to run the test then it needs to be topped off again
     *
     * @throws \Exception
     */
    public function testSpendMixedUtxoTypes()
    {
        $client = $this->setupBlocktrailSDK();

        /** @var Wallet $unittestWallet */
        /** @var Wallet $segwitwallet */
        $unittestWallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "passphrase" => "password"
        ]);

        $segwitwallet = $client->initWallet([
            "identifier" => "unittest-transaction-sw",
            "passphrase" => "password"
        ]);

        $this->assertTrue($segwitwallet->isSegwit());

        list (, $unittestAddress) = $unittestWallet->getNewAddressPair();

        $testingChain = Wallet::CHAIN_BTC_SEGWIT;
        $defaultChain = Wallet::CHAIN_BTC_DEFAULT;

        $task = [$testingChain, $defaultChain];

        $quanta = random_int(1, 5);
        $value = BlocktrailSDK::toSatoshi(0.0005) * $quanta;

        $fundTxHash = [];
        $fundTx = [];
        $fundTxOutIdx = [];

        $builder = $segwitwallet->createTransaction()
            ->addRecipient($unittestAddress, BlocktrailSDK::toSatoshi(0.0003))
            ->setFeeStrategy(Wallet::FEE_STRATEGY_OPTIMAL);

        foreach ($task as $i => $chainIndex) {
            list ($path, $address) = $segwitwallet->getNewAddressPair($chainIndex);
            $this->assertTrue(strpos($path, "M/9999'/{$chainIndex}/") !== -1);

            // Fund segwit address 1
            $fundTxHash[$i] = $unittestWallet->pay([$address => $value,], null, true, true, Wallet::FEE_STRATEGY_OPTIMAL);

            $this->assertTrue(!!$fundTxHash[$i]);
            $fundTx[$i] = $this->getTx($client, $fundTxHash[$i]);
            $this->assertEquals($fundTxHash[$i], $fundTx[$i]['hash']);

            $outIdx = -1;
            foreach ($fundTx[$i]['outputs'] as $j => $output) {
                if ($output['address'] == $address) {
                    $outIdx = $j;
                }
            }

            if ($outIdx === -1) {
                var_dump($fundTxHash[$i]);
                var_dump($value);
                var_dump($fundTx[$i]);
            }

            $this->assertNotEquals(-1, $outIdx, "should find the output we created");

            $builder->spendOutput($fundTxHash[$i], $outIdx, $value, $address, null, $path, null, null);
            $fundTxOutIdx[$i] = $outIdx;
        }

        // Send back to unittest-wallet

        $spendAt = [];
        foreach ($fundTx as $i => $tx) {
            $hash = $tx['hash'];
            $vout = $fundTxOutIdx[$i];
            foreach ($builder->getUtxos() as $k => $utxo) {
                if ($utxo->hash == $hash && $utxo->index == $vout) {
                    $spendAt[$i] = $k;
                }
            }
            $this->assertTrue(array_key_exists($i, $spendAt), "should have found utxo in transaction");
        }

        list ($tx, ) = $segwitwallet->buildTx($builder);
        $this->assertInstanceOf(TransactionInterface::class, $tx);
    }

    private function checkWalletScriptAgainstAddressPair(BlocktrailSDKInterface $sdk, WalletInterface $wallet, $chainIdx)
    {
        list ($path, $address) = $wallet->getNewAddressPair($chainIdx);
        $this->assertTrue(strpos("M/9999'/{$chainIdx}/", $path) !== -1);

        $defaultScript = $wallet->getWalletScriptByPath($path);
        $this->assertEquals($defaultScript->getAddress()->getAddress($sdk->getNetworkParams()->getNetwork()), $address);

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

    public function testWalletRejectsUnknownPaths()
    {
        $client = $this->setupBlocktrailSDK();

        /** @var Wallet $wallet */
        $wallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "passphrase" => "password"
        ]);

        $this->setExpectedException(BlocktrailSDKException::class, "Unsupported chain in path");

        $wallet->getWalletScriptByPath("M/9999'/123123123/0");

    }

    public function testWalletGetNewAddressPair() {
        $client = $this->setupBlocktrailSDK();

        // testnet/mainnet only

        /** @var Wallet $wallet */
        $wallet = $client->initWallet([
            "identifier" => "unittest-transaction-sw",
            "passphrase" => "password"
        ]);

        $this->assertTrue($wallet->isSegwit());

        $this->checkWalletScriptAgainstAddressPair($client, $wallet, Wallet::CHAIN_BTC_DEFAULT);

        $this->checkWalletScriptAgainstAddressPair($client, $wallet, Wallet::CHAIN_BTC_SEGWIT);

        $nestedP2wshPath = "M/9999'/2/0";
        $script = $wallet->getWalletScriptByPath($nestedP2wshPath);

        $this->assertTrue($script->isP2SH());
        $this->assertTrue($script->isP2WSH());

        $this->isBase58Address($script);
        $this->checkP2sh($script->getScriptPubKey(), $script->getRedeemScript());
        $this->checkP2wsh($script->getRedeemScript(), $script->getWitnessScript());

        $network = $client->getNetworkParams()->getNetwork();
        $this->assertEquals("2N3j4Vx3D9LPumjtRbRe2RJpwVocvCCkHKh", $script->getAddress()->getAddress($network));
        $this->assertEquals("a91472f4fbf13b171d3acfe3316264835cc4767549a187", $script->getScriptPubKey()->getHex());
        $this->assertEquals("0020bbb712fe7c81544b588b6f6d8d915b4e6f485ba2b43a70761e1dd9c68e391094", $script->getRedeemScript()->getHex());
        $this->assertEquals("5221020c9855979a83bedd4f45f47938f1008038b703506dd097bf81b76c4c8127482e2102381a4cf140c24080523b5e63082496b514e99657d3506444b7f77c12176635302102ff3475471c1f6caa27def90b97ceee72e2e9c569ebe59232fc19ef3db9e7ecbc53ae", $script->getWitnessScript()->getHex());
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
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->tuple()[0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->tuple()[0]);

        list($confirmed, $unconfirmed) = $wallet->getBalance();
        $this->assertGreaterThan(0, $confirmed + $unconfirmed, "positive unconfirmed balance");
        $this->assertGreaterThan(0, $confirmed, "positive confirmed balance");

        list($path, $address) = $wallet->getNewAddressPair();
        $network = $client->getNetworkParams()->getNetwork();
        $this->assertTrue(strpos($path, "M/9999'/0/") === 0);
        $this->assertTrue(AddressFactory::fromString($address, $network)->getAddress($network) == $address);

        $value = BlocktrailSDK::toSatoshi(0.0002);
        $txHash = $wallet->pay([$address => $value,], null, false, true, Wallet::FEE_STRATEGY_BASE_FEE);

        $this->assertTrue(!!$txHash);

        $tx = $this->getTx($client, $txHash);

        $this->assertEquals($txHash, $tx['hash']);
        $this->assertTrue(count($tx['outputs']) <= 2, "txId={$txHash}");
        $this->assertTrue(in_array($value, array_column($tx['outputs'], 'value')), "txId={$txHash}");

        $baseFee = ceil($tx['size'] / 1000) * BlocktrailSDK::toSatoshi(0.0001);

        // dust was added to fee
        if (count($tx['outputs']) === 1) {
            $this->assertTrue(abs($baseFee - $tx['total_fee']) < Blocktrail::DUST, "txId={$txHash}");
        } else {
            $this->assertEquals($baseFee, $tx['total_fee'], "txId={$txHash}");
        }

        /*
         * do another TX but with a LOW_PRIORITY_FEE
         */
        $value = BlocktrailSDK::toSatoshi(0.0001);
        $txHash = $wallet->pay([$address => $value,], null, false, true, Wallet::FEE_STRATEGY_LOW_PRIORITY);

        $this->assertTrue(!!$txHash);

        $tx = $this->getTx($client, $txHash);

        $this->assertTrue(!!$tx, "check for tx[{$txHash}] [" . gmdate('Y-m-d H:i:s') . "]");
        $this->assertEquals($txHash, $tx['hash']);
        $this->assertLessThanOrEqual(2, count($tx['outputs']));
        $this->assertTrue(in_array($value, array_column($tx['outputs'], 'value')));

        /*
         * do another TX but with a custom - high - fee
         */
        $value = BlocktrailSDK::toSatoshi(0.0001);
        $forceFee = BlocktrailSDK::toSatoshi(0.0010);
        $txHash = $wallet->pay([$address => $value,], null, false, true, Wallet::FEE_STRATEGY_FORCE_FEE, $forceFee);

        $this->assertTrue(!!$txHash);

        $tx = $this->getTx($client, $txHash);


        $this->assertEquals($txHash, $tx['hash']);
        $this->assertTrue(count($tx['outputs']) <= 2, "txId={$txHash}");
        $this->assertTrue(in_array($value, array_column($tx['outputs'], 'value')), "txId={$txHash}");
        // dust was added to fee
        if (count($tx['outputs']) === 1) {
            $this->assertTrue(abs($forceFee - $tx['total_fee']) < Blocktrail::DUST, "txId={$txHash}");
        } else {
            $this->assertEquals($forceFee, $tx['total_fee'], "txId={$txHash}");
        }

        /*
         * do another TX with OP_RETURN using TxBuilder
         */
        $value = BlocktrailSDK::toSatoshi(0.0002);
        $moon = "MOOOOOOOOOOOOON!";
        $txBuilder = $wallet->createTransaction();
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
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->tuple()[0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->tuple()[0]);

        list($confirmed, $unconfirmed) = $wallet->getBalance();
        $this->assertGreaterThan(0, $confirmed + $unconfirmed, "positive unconfirmed balance");
        $this->assertGreaterThan(0, $confirmed, "positive confirmed balance");

        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertTrue(strpos($path, "M/9999'/0/") === 0);

        $network = $client->getNetworkParams()->getNetwork();
        $this->assertEquals($address, AddressFactory::fromString($address, $network)->getAddress($network));

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
        $this->cleanupData['wallets'][] = $wallet; // store for cleanup

        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->tuple()[0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->tuple()[0]);

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

        $this->assertEquals("tpubD9m9hziKhYQExWgzMUNXdYMNUtourv96sjTUS9jJKdo3EDJAnCBJooMPm6vGSmkNTNAmVt988dzNfNY12YYzk9E6PkA7JbxYeZBFy4XAaCp", $wallet->getBlocktrailPublicKey("m/10000")->tuple()[0]);

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
        $this->cleanupData['wallets'][] = $wallet; // store for cleanup

        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->tuple()[0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->tuple()[0]);

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
        $this->cleanupData['wallets'][] = $wallet; // store for cleanup

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
        $this->cleanupData['wallets'][] = $wallet; // store for cleanup

        $this->assertTrue($wallet instanceof WalletV1);

        $this->_testNewBlankWallet($wallet);
    }

    public function testNewBlankWithoutMnemonicsWalletV2() {
        $client = $this->setupBlocktrailSDK();

        $identifier = $this->getRandomTestIdentifier();

        $network = $client->getNetworkParams()->getNetwork();
        $primaryPrivateKey = BIP32Key::create($network, HierarchicalKeyFactory::generateMasterKey(), 'm');
        $backupPublicKey = BIP32Key::create($network, HierarchicalKeyFactory::generateMasterKey()->toPublic(), 'M');

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
        $this->cleanupData['wallets'][] = $wallet; // store for cleanup

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
        $this->cleanupData['wallets'][] = $wallet; // store for cleanup

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

        $address = $wallet->getNewAddress();
        $this->assertTrue(!!$address, "should generate an address, was `".$address."`");

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
        $this->cleanupData['wallets'][] = $wallet; // store for cleanup

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

    public function testEstimateSizeOutputs() {
        $this->assertEquals(34, Wallet::estimateSizeOutputs(1));
        $this->assertEquals(68, Wallet::estimateSizeOutputs(2));
        $this->assertEquals(102, Wallet::estimateSizeOutputs(3));
        $this->assertEquals(3366, Wallet::estimateSizeOutputs(99));
    }

    public function testEstimateSizeUTXOs() {
        $this->assertEquals(297, Wallet::estimateSizeUTXOs(1));
        $this->assertEquals(594, Wallet::estimateSizeUTXOs(2));
        $this->assertEquals(891, Wallet::estimateSizeUTXOs(3));
        $this->assertEquals(29403, Wallet::estimateSizeUTXOs(99));
    }

    public function testEstimateSize() {
        $this->assertEquals(347, Wallet::estimateSize(34, 297));
        $this->assertEquals(29453, Wallet::estimateSize(34, 29403));
        $this->assertEquals(3679, Wallet::estimateSize(3366, 297));
    }

    public function testSegwitBuildTx() {
        $client = $this->setupBlocktrailSDK();

        $wallet = $client->initWallet([
            "identifier" => "unittest-transaction",
            "passphrase" => "password"
        ]);

        $txid = "cafdeffb255ed7f8175f2bffc745e2dcc0ab0fa9abf9dad70a543c307614d374";
        $vout = 0;
        $address = "2MtLjsE6SyBoxXt3Xae2wTU8sPdN8JUkUZc";
        $value = 9900000;
        $outValue = 9899999;
        $expectfee = $value-$outValue;
        $scriptPubKey = "a9140c03259201742cb7476f10f70b2cf75fbfb8ab4087";
        $redeemScript = "0020cc7f3e23ec2a4cbba32d7e8f2e1aaabac38b88623d09f41dc2ee694fd33c6b14";
        $witnessScript = "5221021b3657937c54c616cbb519b447b4e50301c40759282901e04d81b5221cfcce992102381a4cf140c24080523b5e63082496b514e99657d3506444b7f77c1217663530210317e37c952644cf08b356671b4bb0308bd2468f548b31a308e8bacb682d55747253ae";

        $path = "M/9999'/2/0";

        $utxos = [
            $txid => $value,
        ];

        /** @var Transaction $tx */
        /** @var SignInfo[] $signInfo */
        list($tx, $signInfo) = $wallet->buildTx(
            $wallet->createTransaction()
                ->spendOutput(
                    $txid,
                    $vout,
                    $value,
                    $address,
                    $scriptPubKey,
                    $path,
                    $redeemScript,
                    $witnessScript
                )
                ->addRecipient("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", $outValue)
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
        $this->assertEquals($value, $inputTotal);
        $this->assertEquals($outValue, $outputTotal);
        $this->assertEquals($expectfee, $fee);

        $network = $client->getNetworkParams()->getNetwork();
        // assert the input(s)
        $this->assertEquals(1, count($tx->getInputs()));
        $this->assertEquals($txid, $tx->getInput(0)->getOutPoint()->getTxId()->getHex());
        $this->assertEquals(0, $tx->getInput(0)->getOutPoint()->getVout());
        $this->assertEquals($address, AddressFactory::fromOutputScript($signInfo[0]->output->getScript())->getAddress($network));
        $this->assertEquals($scriptPubKey, $signInfo[0]->output->getScript()->getHex());
        $this->assertEquals($value, $signInfo[0]->output->getValue());
        $this->assertEquals($path, $signInfo[0]->path);
        $this->assertEquals(
            $redeemScript,
            $signInfo[0]->redeemScript->getHex()
        );
        $this->assertEquals(
            $witnessScript,
            $signInfo[0]->witnessScript->getHex()
        );

        // assert the output(s)
        $this->assertEquals(1, count($tx->getOutputs()));
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(0)->getScript())->getAddress($network));
        $this->assertEquals($outValue, $tx->getOutput(0)->getValue());
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
            '0d8703ab259b03a757e37f3cdba7fc4543e8d47f7cc3556e46c0aeef6f5e832b' => BlocktrailSDK::toSatoshi(0.0001),
            'be837cd8f04911f3ee10d010823a26665980f7bb6c9ed307d798cb968ca00128' => BlocktrailSDK::toSatoshi(0.001),
        ];
        /** @var Transaction $tx */
        /** @var SignInfo[] $signInfo */
        list($tx, $signInfo) = $wallet->buildTx(
            $wallet->createTransaction()
                ->spendOutput(
                    "0d8703ab259b03a757e37f3cdba7fc4543e8d47f7cc3556e46c0aeef6f5e832b",
                    0,
                    BlocktrailSDK::toSatoshi(0.0001),
                    "2N9os1eAZXrWwKWgo7ppDRsY778PyxbScYH",
                    "a914b5ae3a9950fa66efa4aab2c21ce4a4275e7c95b487",
                    "M/9999'/0/5",
                    "5221032923eb97175038268cd320ffbb74bbef5a97ad58717026564431b5a131d47a3721036965ca88b87b25e1fb48df54ef6401eaa48383216f6725f6ec73009f84bfd79a2103cdea44d3fb80b36794fb393360279a54392785fbf05ff7f6af93d4d68448f53753ae"
                )
                ->spendOutput(
                    "be837cd8f04911f3ee10d010823a26665980f7bb6c9ed307d798cb968ca00128",
                    0,
                    BlocktrailSDK::toSatoshi(0.001),
                    "2NBV4sxQMYNyBbUeZkmPTZYtpdmKcuZ4Cyw",
                    "a914c8107bd24bae2c521a5a9f56c9b72e047eafa1f587",
                    "M/9999'/0/12",
                    "5221031a51c189641ff16a0afd9658b4864357e5ec4913ee5822103319adbd16d8fc262103628501430353863e2c3986372c251a562709e60238f129e494faf44aedf500dd2103ec9869b201d54cd1df80f49eafe7ff1a5a8a80b4b1e99b7a7fd2423e717e8d2753ae"
                )
                ->addRecipient("2N7C5Jn1LasbEK9mvHetBYXaDnQACXkarJe", BlocktrailSDK::toSatoshi(0.001))
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
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0011), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.001), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);

        $network = $client->getNetworkParams()->getNetwork();

        // assert the input(s)
        $this->assertEquals(2, count($tx->getInputs()));
        $this->assertEquals("0d8703ab259b03a757e37f3cdba7fc4543e8d47f7cc3556e46c0aeef6f5e832b", $tx->getInput(0)->getOutPoint()->getTxId()->getHex());
        $this->assertEquals(0, $tx->getInput(0)->getOutPoint()->getVout());
        $this->assertEquals("2N9os1eAZXrWwKWgo7ppDRsY778PyxbScYH", AddressFactory::fromOutputScript($signInfo[0]->output->getScript())->getAddress($network));
        $this->assertEquals("a914b5ae3a9950fa66efa4aab2c21ce4a4275e7c95b487", $signInfo[0]->output->getScript()->getHex());
        $this->assertEquals(10000, $signInfo[0]->output->getValue());
        $this->assertEquals("M/9999'/0/5", $signInfo[0]->path);
        $this->assertEquals(
            "5221032923eb97175038268cd320ffbb74bbef5a97ad58717026564431b5a131d47a3721036965ca88b87b25e1fb48df54ef6401eaa48383216f6725f6ec73009f84bfd79a2103cdea44d3fb80b36794fb393360279a54392785fbf05ff7f6af93d4d68448f53753ae",
            $signInfo[0]->redeemScript->getHex()
        );

        $this->assertEquals("be837cd8f04911f3ee10d010823a26665980f7bb6c9ed307d798cb968ca00128", $tx->getInput(1)->getOutPoint()->getTxId()->getHex());
        $this->assertEquals(0, $tx->getInput(1)->getOutPoint()->getVout());
        $this->assertEquals("2NBV4sxQMYNyBbUeZkmPTZYtpdmKcuZ4Cyw", AddressFactory::fromOutputScript($signInfo[1]->output->getScript())->getAddress($network));
        $this->assertEquals("a914c8107bd24bae2c521a5a9f56c9b72e047eafa1f587", $signInfo[1]->output->getScript()->getHex());
        $this->assertEquals(100000, $signInfo[1]->output->getValue());
        $this->assertEquals("M/9999'/0/12", $signInfo[1]->path);
        $this->assertEquals(
            "5221031a51c189641ff16a0afd9658b4864357e5ec4913ee5822103319adbd16d8fc262103628501430353863e2c3986372c251a562709e60238f129e494faf44aedf500dd2103ec9869b201d54cd1df80f49eafe7ff1a5a8a80b4b1e99b7a7fd2423e717e8d2753ae",
            $signInfo[1]->redeemScript->getHex()
        );

        // assert the output(s)
        $this->assertEquals(1, count($tx->getOutputs()));
        $this->assertEquals("2N7C5Jn1LasbEK9mvHetBYXaDnQACXkarJe", AddressFactory::fromOutputScript($tx->getOutput(0)->getScript())->getAddress($network));
        $this->assertEquals(100000, $tx->getOutput(0)->getValue());

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
                $wallet->createTransaction()
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
            $wallet->createTransaction()
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
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(13)->getScript())->getAddress($network));
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
            $wallet->createTransaction()
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
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(19)->getScript())->getAddress($network));
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
            $wallet->createTransaction()
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
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(20)->getScript())->getAddress($network));
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
            $wallet->createTransaction()
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
            $wallet->createTransaction()
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
            $wallet->createTransaction()
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
            $wallet->createTransaction()
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

        $network = $client->getNetworkParams()->getNetwork();
        $this->assertEquals("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", AddressFactory::fromOutputScript($tx->getOutput(0)->getScript())->getAddress($network));
        $this->assertEquals("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", AddressFactory::fromOutputScript($tx->getOutput(1)->getScript())->getAddress($network));
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
