<?php

namespace BlockTrail\SDK\Tests;

use BlockTrail\SDK\APIClient;
use BlockTrail\SDK\Connection\Exceptions\InvalidCredentials;

/**
 * Class APIClientTest
 *
 * @package BlockTrail\SDK\Tests
 */
class APIClientTest extends \PHPUnit_Framework_TestCase {

    /**
     * setup an instance of APIClient
     *
     * @return APIClient
     * @TODO: think of keys to use
     * @TODO: use live env
     */
    public function setupAPIClient() {
        return new APIClient("MYKEY", "MYSECRET", null, null, null, "http://api.blocktrail.ngrok.com/v1/BTC/");
    }

    /**
     * setup an instance of APIClient
     *
     * @return APIClient
     * @TODO: use live env
     */
    public function setupBadAPIClient() {
        return new APIClient("TESTKEY-FAIL", "TESTSECRET-FAIL", null, null, null, "http://api.blocktrail.ngrok.com/v1/BTC/");
    }

    public function testSigning() {
        $client = $this->setupBadAPIClient();

        try {
            $client->address("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp");
            $this->fail("Bad keys still succeeded");
        } catch (InvalidCredentials $e) {}

        $client = $this->setupAPIClient();

        $this->assertTrue(!!$client->address("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp"), "Good keys failed");
    }

    public function testAddress() {
        $client = $this->setupAPIClient();

        $address = $client->address("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp");
        $this->assertTrue(is_array($address), "Default response is not an array");
        $this->assertEquals("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp", $address['address'], "Address in response doesn't match");
    }
}