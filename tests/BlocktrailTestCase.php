<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\BlocktrailSDK;

abstract class BlocktrailTestCase extends \PHPUnit_Framework_TestCase {

    /**
     * stores arrays of data to be deleted on cleanup
     * @var array
     */
    protected $cleanupData = array();

    /**
     * setup an instance of BlocktrailSDK
     *
     * @return BlocktrailSDK
     */
    public function setupBlocktrailSDK() {
        $apiKey = getenv('BLOCKTRAIL_SDK_APIKEY') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APIKEY';
        $apiSecret = getenv('BLOCKTRAIL_SDK_APISECRET') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APISECRET';

        $client = new BlocktrailSDK($apiKey, $apiSecret);

        return $client;
    }

    protected function tearDown() {
        //called after each test
        $this->cleanUp();
    }
    protected function onNotSuccessfulTest(\Exception $e) {
        //called when a test fails
        $this->cleanUp();
        throw $e;
    }

    protected function cleanUp() {
        //cleanup any records that were created
        $client = $this->setupBlocktrailSDK();

        //webhooks
        if (isset($this->cleanupData['webhooks'])) {
            $count = 0;
            foreach ($this->cleanupData['webhooks'] as $webhook) {
                try {
                    $count += (int)$client->deleteWebhook($webhook);
                } catch (\Exception $e) {
                }
            }
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
