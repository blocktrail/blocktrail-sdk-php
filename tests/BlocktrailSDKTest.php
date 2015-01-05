<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\InvalidCredentials;

class BlocktrailSDKTest extends \PHPUnit_Framework_TestCase {

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
        $client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET");
        // $client->setCurlDebugging();
        // $client->setCurlDefaultOption('verify', false); //just for local testing when cURL can't verify ssl certs
        return $client;
    }

    protected function tearDown()
    {
        //called after each test
        $this->cleanUp();
    }
    protected function onNotSuccessfulTest(\Exception $e)
    {
        //called when a test fails
        $this->cleanUp();
        throw $e;
    }
    protected function cleanUp()
    {
        //cleanup any records that were created
        $client = $this->setupBlocktrailSDK();

        //webhooks
        if(isset($this->cleanupData['webhooks'])) {
            $count = 0;
            foreach($this->cleanupData['webhooks'] as $webhook) {
                try{
                    $count += (int)$client->deleteWebhook($webhook);
                } catch (\Exception $e){}
            }
            echo "\ncleanup - {$count} webhooks deleted\n";
        }
    }

    /**
     * setup an instance of BlocktrailSDK
     *
     * @return BlocktrailSDK
     */
    public function setupBadBlocktrailSDK() {
        return new BlocktrailSDK("TESTKEY-FAIL", "TESTSECRET-FAIL");
    }

    public function testSatoshiConversion() {
        $toSatoshi = [
            ["0.00000001",         "1"],
            [0.00000001,           "1"],
            ["0.29560000",         "29560000"],
            [0.29560000,           "29560000"],
            ["1.0000009",          "100000090"],
            [1.0000009,            "100000090"],
            ["1.00000009",         "100000009"],
            [1.00000009,           "100000009"],
            ["21000000.00000001",  "2100000000000001"],
            [21000000.00000001,    "2100000000000001"],
            ["21000000.0000009",   "2100000000000090"],
            [21000000.0000009,     "2100000000000090"],
            ["21000000.00000009",  "2100000000000009"],
            [21000000.00000009,    "2100000000000009"],
            ["210000000.00000009", "21000000000000009"],
            [210000000.00000009,   "21000000000000009"],

            // thee fail because when the BTC value is converted to a float it looses precision
            // ["2100000000.00000009", "210000000000000009"],
            // [2100000000.00000009,   "210000000000000009"],
        ];

        $toBTC = [
            ["1",                  "0.00000001"],
            [1,                    "0.00000001"],
            ["29560000",           "0.29560000"],
            [29560000,             "0.29560000"],
            ["100000090",          "1.00000090"],
            [100000090,            "1.00000090"],
            ["100000009",          "1.00000009"],
            [100000009,            "1.00000009"],
            ["2100000000000001",   "21000000.00000001"],
            [2100000000000001,     "21000000.00000001"],
            ["2100000000000090",   "21000000.00000090"],
            [2100000000000090,     "21000000.00000090"],
            ["2100000000000009",   "21000000.00000009"],
            [2100000000000009,     "21000000.00000009"],
            ["21000000000000009",  "210000000.00000009"],
            [21000000000000009,    "210000000.00000009"],
            ["210000000000000009",  "2100000000.00000009"],
            [210000000000000009,    "2100000000.00000009"],
            ["2100000000000000009",  "21000000000.00000009"],
            [2100000000000000009,    "21000000000.00000009"],
            // these fail because they're > PHP_INT_MAX
            // ["21000000000000000009",  "210000000000.00000009"],
            // [21000000000000000009,    "210000000000.00000009"],
        ];

        foreach ($toSatoshi as $i => $test) {
            $btc = $test[0];
            $satoshi = $test[1];

            $this->assertEquals($satoshi, BlocktrailSDK::toSatoshi($btc), "[{$i}] {$btc} => {$satoshi}");
            $this->assertTrue($satoshi === BlocktrailSDK::toSatoshi($btc), "[{$i}] {$btc} => {$satoshi}");
        }

        foreach ($toBTC as $i => $test) {
            $satoshi = $test[0];
            $btc = $test[1];

            $this->assertEquals($btc, BlocktrailSDK::toBTC($satoshi), "[{$i}] {$satoshi} => {$btc}");
            $this->assertTrue($btc === BlocktrailSDK::toBTC($satoshi), "[{$i}] {$satoshi} => {$btc}");
        }
    }

    public function testSigning() {
        $client = $this->setupBadBlocktrailSDK();

        try {
            $client->verifyAddress("16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z", "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=");
            $this->fail("Bad keys still succeeded");
        } catch (InvalidCredentials $e) {}

        $client = $this->setupBlocktrailSDK();

        try {
            $client->verifyAddress("16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z", "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=");
        } catch (InvalidCredentials $e) {
            $this->fail("Good keys failed");
        } catch (\Exception $e) {
            // ignore other exceptions
        }
    }

    public function testAddress() {
        $client = $this->setupBlocktrailSDK();

        //address info
        $address = $client->address("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp");
        $this->assertTrue(is_array($address), "Default response is not an array");
        $this->assertEquals("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp", $address['address'], "Address in response doesn't match");

        //address transactions
        $response = $client->addressTransactions("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp", $page=1, $limit=23);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertEquals(23, count($response['data']), "Count of address transactions returned is not equal to 23");

        //address unconfirmed transactions
        $response = $client->addressUnconfirmedTransactions("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp", $page=1, $limit=23);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        // $this->assertGreaterThanOrEqual(count($response['data']), $response['total'], "Total records found on server is less than the count of records returned");

        //address unspent outputs
        $response = $client->addressUnspentOutputs("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp", $page=1, $limit=23);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertGreaterThanOrEqual(count($response['data']), $response['total'], "Total records found on server is less than the count of records returned");

        //address verification
        $response = $client->verifyAddress("16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z", "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('result', $response, "'result' key not in response");
    }

    public function testBlock() {
        $client = $this->setupBlocktrailSDK();

        //block info
        $response = $client->block("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
        $this->assertEquals("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf", $response['hash'], "Block hash returned does not match expected value");

        //block info by height
        $response = $client->block(200000);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
        $this->assertEquals("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf", $response['hash'], "Block hash returned does not match expected value");

        //block transactions
        $response = $client->blockTransactions("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf", $page=1, $limit=23);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertEquals(23, count($response['data']), "Count of transactions returned is not equal to 23");

        //all blocks
        $response = $client->allBlocks($page=2, $limit=23);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertEquals(23, count($response['data']), "Count of blocks returned is not equal to 23");

        $this->assertArrayHasKey('hash', $response['data'][0], "'hash' key not in first block of response");
        $this->assertArrayHasKey('hash', $response['data'][1], "'hash' key not in second block of response");
        $this->assertEquals("000000000cd339982e556dfffa9de94744a4135c53eeef15b7bcc9bdeb9c2182", $response['data'][0]['hash'], "First block hash does not match expected value");
        $this->assertEquals("00000000fc051fbbce89a487e811a5d4319d209785ea4f4b27fc83770d1e415f", $response['data'][1]['hash'], "Second block hash does not match expected value");

        //latest block
        $response = $client->blockLatest();
        $this->assertTrue(is_array($response), "Default response is not an array for latest block");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
    }

    public function testTransaction() {
        $client = $this->setupBlocktrailSDK();

        //coinbase TX
        $response = $client->transaction("4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
        $this->assertArrayHasKey('confirmations', $response, "'confirmations' key not in response");
        $this->assertEquals("4a5e1e4baab89f3a32518a88c31bc87f618f76673e2cc77ab2127b7afdeda33b", $response['hash'], "Transaction hash does not match expected value");

        $this->assertNull($response['enough_fee'], "'enough_fee' is not null in coinbase transaction");

        //random TX 1
        $response = $client->transaction("c791b82ed9af681b73eadb7a05b67294c1c3003e52d01e03775bfb79d4ac58d1");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
        $this->assertArrayHasKey('confirmations', $response, "'confirmations' key not in response");
        $this->assertEquals("c791b82ed9af681b73eadb7a05b67294c1c3003e52d01e03775bfb79d4ac58d1", $response['hash'], "Transaction hash does not match expected value");
        $this->assertTrue($response['enough_fee']);
        $this->assertFalse($response['high_priority']);
    }

    public function testWebhooks() {
        $client = $this->setupBlocktrailSDK();

        //keep track of all webhooks created for cleanup
        $this->cleanupData['webhooks'] = array();

        //create a webhook with custom identifier (randomly generated)
        $bytes = openssl_random_pseudo_bytes(10);
        $identifier1 = bin2hex($bytes);
        $response = $client->setupWebhook("https://www.blocktrail.com/webhook-test", $identifier1);

        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $response['url'], "Webhook url does not match expected value");
        $this->assertEquals($identifier1, $response['identifier'], "identifier does not match expected value");
        $this->cleanupData['webhooks'][] = $webhookID1 = $response['identifier'];

        //create a webhook without custom identifier
        $response = $client->setupWebhook("https://www.blocktrail.com/webhook-test");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $response['url'], "Webhook url does not match expected value");
        $this->assertNotEquals("", $response['identifier'], "identifier does not match expected value");
        $this->cleanupData['webhooks'][] = $webhookID2 = $response['identifier'];

        //get all webhooks
        $response = $client->allWebhooks();
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertGreaterThanOrEqual(2, $response['total'], "'total' is not greater than expected value");
        $this->assertGreaterThanOrEqual(2, count($response['data']), "Count of webhooks returned is not greater than or equal to 2");

        $this->assertArrayHasKey('url', $response['data'][0], "'url' key not in first webhook of response");
        $this->assertArrayHasKey('url', $response['data'][1], "'url' key not in second webhook of response");
        
        //get a single webhook
        $response = $client->getWebhook($webhookID1);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $response['url'], "Webhook url does not match expected value");
        $this->assertEquals($identifier1, $response['identifier'], "identifier does not match expected value");

        //delete a webhook
        $response = $client->deleteWebhook($webhookID1);
        $this->assertTrue(!!$response);

        //update a webhook
        $bytes = openssl_random_pseudo_bytes(10);
        $newIdentifier = bin2hex($bytes);
        $newUrl = "https://www.blocktrail.com/new-webhook-url";
        $response = $client->updateWebhook($webhookID2, $newUrl, $newIdentifier);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals($newUrl, $response['url'], "Webhook url does not match expected value");
        $this->assertEquals($newIdentifier, $response['identifier'], "identifier does not match expected value");
        $this->cleanupData['webhooks'][] = $webhookID2 = $response['identifier'];

        //add webhook event subscription (address-transactions)
        $address = "1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp";
        $response = $client->subscribeAddressTransactions($webhookID2, $address, 2);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('event_type', $response, "'event_type' key not in response");
        $this->assertArrayHasKey('address', $response, "'address' key not in response");
        $this->assertArrayHasKey('confirmations', $response, "'confirmations' key not in response");
        $this->assertEquals("address-transactions", $response['event_type'], "event type does not match expected value");
        $this->assertEquals($address, $response['address'], "address does not match expected value");
        $this->assertEquals(2, $response['confirmations'], "confirmations does not match expected value");

        //add webhook event subscription (block)
        $response = $client->subscribeNewBlocks($webhookID2);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('event_type', $response, "'event_type' key not in response");
        $this->assertArrayHasKey('address', $response, "'address' key not in response");
        $this->assertArrayHasKey('confirmations', $response, "'confirmations' key not in response");
        $this->assertEquals("block", $response['event_type'], "event type does not match expected value");
        $this->assertNull($response['address'], "address does not match expected value");
        $this->assertNull($response['confirmations'], "confirmations does not match expected value");

        //get webhook's event subscriptions
        $response = $client->getWebhookEvents($webhookID2);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertEquals(2, $response['total'], "'total' does not match expected value");
        $this->assertEquals(2, count($response['data']), "Count of events returned is not equal to 2");

        $this->assertArrayHasKey('event_type', $response['data'][0], "'event_type' key not in first webhook of response");
        $this->assertArrayHasKey('event_type', $response['data'][1], "'event_type' key not in second webhook of response");
        $this->assertEquals("address-transactions", $response['data'][0]['event_type'], "First subscription event type does not match expected value");
        $this->assertEquals("block", $response['data'][1]['event_type'], "Second subscription event type does not match expected value");

        //unsubscribe webhook event (address-transaction)
        $address = "1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp";
        $response = $client->unsubscribeAddressTransactions($webhookID2, $address);
        $this->assertTrue($response === true, "response does not match expected value");

        //unsubscribe webhook event (block)
        $response = $client->unsubscribeNewBlocks($webhookID2);
        $this->assertTrue($response === true, "response does not match expected value");
    }
}
