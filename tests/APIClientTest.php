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
        $client = new APIClient("MYKEY", "MYSECRET", null, null, null, "http://api.blocktrail.ngrok.com/v1/BTC/");
        // $client->setCurlDebugging();
        return $client;
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

    public function testHMAC() {
        $client = $this->setupBadAPIClient();

        try {
            $client->verifyAddress("16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z", "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=");
            $this->fail("Bad keys still succeeded");
        } catch (InvalidCredentials $e) {}

        $client = $this->setupAPIClient();

        try {
            $client->verifyAddress("16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z", "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=");
        } catch (InvalidCredentials $e) {
            $this->fail("Good keys failed");
        } catch (\Exception $e) {
            // ignore other exceptions
        }
    }

    public function testAddress() {
        $client = $this->setupAPIClient();

        $address = $client->address("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp");
        $this->assertTrue(is_array($address), "Default response is not an array");
        $this->assertEquals("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp", $address['address'], "Address in response doesn't match");
    }
}