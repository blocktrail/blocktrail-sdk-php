<?php

namespace BlockTrail\SDK\Tests;

use BlockTrail\SDK\APIClient;
use BlockTrail\SDK\BlockTrail;
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
     */
    public function setupAPIClient() {
        $client = new APIClient("MY_APIKEY", "MY_APISECRET");
        // $client->setCurlDebugging();
        return $client;
    }

    /**
     * setup an instance of APIClient
     *
     * @return APIClient
     */
    public function setupBadAPIClient() {
        return new APIClient("TESTKEY-FAIL", "TESTSECRET-FAIL");
    }

    public function testCoinValue() {
        $this->assertEquals(1, BlockTrail::toSatoshi(0.00000001));
        $this->assertEquals(1, BlockTrail::toSatoshi("0.00000001"));
        $this->assertEquals(1.0, BlockTrail::toBTC(100000000));
        $this->assertEquals(1.0, BlockTrail::toBTC("100000000"));

        $this->assertEquals(123456789, BlockTrail::toSatoshi(1.23456789));
        $this->assertEquals(123456789, BlockTrail::toSatoshi("1.23456789"));
        $this->assertEquals(1.23456789, BlockTrail::toBTC(123456789));
        $this->assertEquals(1.23456789, BlockTrail::toBTC("123456789"));
    }

    public function testSigning() {
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
        $client = $this->setupAPIClient();

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
        $client = $this->setupAPIClient();

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
        $client = $this->setupAPIClient();

        //create a webhook with custom identity
        $response = $client->setupWebhook("https://www.blocktrail.com/webhook-test", 'my-webhook-id');
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $response['url'], "Webhook url does not match expected value");
        $this->assertEquals("my-webhook-id", $response['identifier'], "identifier does not match expected value");
        $webhookID1 = $response['identifier'];

        //create a webhook without custom identity
        $response = $client->setupWebhook("https://www.blocktrail.com/webhook-test");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $response['url'], "Webhook url does not match expected value");
        $this->assertNotEquals("", $response['identifier'], "identifier does not match expected value");
        $webhookID2 = $response['identifier'];

        //get all webhooks
        $response = $client->allWebhooks();
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertEquals(2, $response['total'], "'total' does not match expected value");
        $this->assertEquals(2, count($response['data']), "Count of webhooks returned is not equal to 2");

        $this->assertArrayHasKey('url', $response['data'][0], "'url' key not in first webhook of response");
        $this->assertArrayHasKey('url', $response['data'][1], "'url' key not in second webhook of response");
        $this->assertEquals($webhookID1, $response['data'][0]['identifier'], "First webhook identifier does not match expected value");
        $this->assertEquals($webhookID2, $response['data'][1]['identifier'], "Second webhook identifier does not match expected value");

        //get a single webhook
        $response = $client->getWebhook($webhookID1);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $response['url'], "Webhook url does not match expected value");
        $this->assertEquals("my-webhook-id", $response['identifier'], "identifier does not match expected value");

        //delete a webhook
        $response = $client->deleteWebhook($webhookID1);
        $this->assertTrue($response);

        //update a webhook
        $newIdentity = "a-new-identity";
        $newUrl = "https://www.blocktrail.com/new-webhook-url";
        $response = $client->updateWebhook($webhookID2, $newUrl, $newIdentity);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals($newUrl, $response['url'], "Webhook url does not match expected value");
        $this->assertEquals($newIdentity, $response['identifier'], "identifier does not match expected value");

        //add webhook event subscription (address-transactions)
        $webhookID2 = $newIdentity;
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
        $response = $client->subscribeBlock($webhookID2);
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
        $response = $client->unsubscribeBlock($webhookID2);
        $this->assertTrue($response === true, "response does not match expected value");

        //cleanup
        $response = $client->deleteWebhook($newIdentity);
        $this->assertTrue($response);
    }
}