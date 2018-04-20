<?php

namespace Blocktrail\SDK\Tests\IntegrationTests;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\WalletInterface;

abstract class IntegrationTestBase extends \PHPUnit_Framework_TestCase {

    /**
     * stores arrays of data to be deleted on cleanup
     * @var array
     */
    protected $cleanupData = array();

    /**
     * setup an instance of BlocktrailSDK
     * params are same defaults as BlocktrailSDK
     *
     * @return BlocktrailSDK
     */
    public function setupBlocktrailSDK($network = 'BTC', $testnet = false, $apiVersion = 'v1', $apiEndpoint = null) {
        $apiKey = getenv('BLOCKTRAIL_SDK_APIKEY') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APIKEY';
        $apiSecret = getenv('BLOCKTRAIL_SDK_APISECRET') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APISECRET';

        $client = new BlocktrailSDK($apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint);
        $client->setVerboseErrors(true);
//        $client->setCurlDebugging(true);

        return $client;
    }

    protected function setUp() {
        parent::setUp();

        // stupid sleep to avoid rate limits
        usleep(0.4 * 1000 * 1000);
    }

    protected function tearDown() {
        //called after each test
        $this->cleanUp();
    }
    protected function onNotSuccessfulTest($e) {
        //called when a test fails
        $this->cleanUp();
        throw $e;
    }

    protected function cleanUp() {
        //cleanup any records that were created
        $client = $this->setupBlocktrailSDK();

        if (array_key_exists('wallets', $this->cleanupData)) {
            $count = 0;
            foreach ($this->cleanupData['wallets'] as $wallet) {
                /** @var WalletInterface $wallet */
                try {
                    if ($wallet->isLocked()) {
                        $wallet->unlock(['passphrase' => 'password']);
                    }
                    $wallet->deleteWallet(true);
                    $count++;
                } catch (ObjectNotFound $e) {
                    // that's ok
                }
            }

            $this->cleanupData['wallets'] = [];

        }

        //webhooks
        if (array_key_exists('webhooks', $this->cleanupData)) {
            $count = 0;
            foreach ($this->cleanupData['webhooks'] as $webhook) {
                try {
                    $count += (int)$client->deleteWebhook($webhook);
                } catch (\Exception $e) {
                }
            }
            $this->cleanupData['webhooks'] = [];
        }
    }

    /**
     * setup an instance of BlocktrailSDK
     *
     * @return BlocktrailSDK
     */
    protected function setupBadBlocktrailSDK() {
        return new BlocktrailSDK("TESTKEY-FAIL", "TESTSECRET-FAIL");
    }

}
