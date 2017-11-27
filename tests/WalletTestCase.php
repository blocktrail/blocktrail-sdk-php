<?php
/**
 * Created by PhpStorm.
 * User: tk
 * Date: 11/27/17
 * Time: 1:17 PM
 */

namespace Blocktrail\SDK\Tests;


use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use Blocktrail\CryptoJSAES\CryptoJSAES;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\BlocktrailSDKInterface;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletPath;
use Blocktrail\SDK\WalletV2;
use Blocktrail\SDK\WalletV3;

abstract class WalletTestCase extends BlocktrailTestCase
{
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
            false,
            $checksum
        );

        if (!$readOnly) {
            $wallet->unlock(['password' => $passphrase]);
        }

        return $wallet;
    }

}
