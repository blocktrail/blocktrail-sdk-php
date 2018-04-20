<?php
/**
 * Created by PhpStorm.
 * User: tk
 * Date: 7/13/17
 * Time: 3:13 PM
 */

namespace Blocktrail\SDK\Tests\IntegrationTests;

class WebhookTest extends IntegrationTestBase
{
    public function testWebhooks() {
        $client = $this->setupBlocktrailSDK('BTC', false);

        //keep track of all webhooks created for cleanup

        //create a webhook with custom identifier (randomly generated)
        $bytes = openssl_random_pseudo_bytes(10);
        $identifier1 = bin2hex($bytes);
        $response = $client->setupWebhook("https://www.blocktrail.com/webhook-test", $identifier1);

        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $response['url'], "Webhook url does not match expected value");
        $this->assertEquals($identifier1, $response['identifier'], "identifier does not match expected value");
        $webhookID1 = $response['identifier'];
        $this->cleanupData['webhooks'][] = $webhookID1;

        //create a webhook without custom identifier
        $response = $client->setupWebhook("https://www.blocktrail.com/webhook-test");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('url', $response, "'url' key not in response");
        $this->assertArrayHasKey('identifier', $response, "'identifier' key not in response");
        $this->assertEquals("https://www.blocktrail.com/webhook-test", $response['url'], "Webhook url does not match expected value");
        $this->assertNotEquals("", $response['identifier'], "identifier does not match expected value");
        $webhookID2 = $response['identifier'];
        $this->cleanupData['webhooks'][] = $webhookID2;

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
        $this->assertTrue($response);
        $webhookID2 = $newIdentifier;
        $this->cleanupData['webhooks'][] = $webhookID2;

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

        //add webhook event subscription (transaction)
        $transaction = "a0a87b1577d606b349cfded85c842bdc53b99bcd49614229a71804b46b1c27cc";
        $response = $client->subscribeTransaction($webhookID2, $transaction, 2);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('event_type', $response, "'event_type' key not in response");
        $this->assertArrayHasKey('address', $response, "'address' key not in response");
        $this->assertArrayHasKey('confirmations', $response, "'confirmations' key not in response");
        $this->assertEquals("transaction", $response['event_type'], "event type does not match expected value");
        $this->assertEquals($transaction, $response['transaction'], "address does not match expected value");
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
        $this->assertEquals(3, $response['total'], "'total' does not match expected value");
        $this->assertEquals(3, count($response['data']), "Count of events returned is not equal to 2");

        // because the order is not guarenteed we check if each of the events is present
        // by removing one entry from the response for each and then checking if the result is empty afterwards
        foreach (['address-transactions', 'transaction', 'block'] as $eventType) {
            foreach ($response['data'] as $k => $event) {
                if ($event['event_type'] === $eventType) {
                    unset($response['data'][$k]);
                    break;
                }
            }
        }
        $this->assertEquals(0, count($response['data']));

        //unsubscribe webhook event (address-transaction)
        $address = "1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp";
        $response = $client->unsubscribeAddressTransactions($webhookID2, $address);
        $this->assertTrue($response === true, "response does not match expected value");

        //unsubscribe webhook event (address-transaction)
        $transaction = "a0a87b1577d606b349cfded85c842bdc53b99bcd49614229a71804b46b1c27cc";
        $response = $client->unsubscribeTransaction($webhookID2, $transaction);
        $this->assertTrue($response === true, "response does not match expected value");

        //unsubscribe webhook event (block)
        $response = $client->unsubscribeNewBlocks($webhookID2);
        $this->assertTrue($response === true, "response does not match expected value");

        //batch create webhook events
        $batchData = array(
            array(
                'event_type'    => 'address-transactions',
                'address'       => '18FA8Tn54Hu8fjn7kkfAygPoGEJLHMbHzo',
                'confirmations' => 1
            ),
            array(
                'event_type'    => 'address-transactions',
                'address'       => '1LUCKYwD6V9JHVXAFEEjyQSD4Dj5GLXmte',
                'confirmations' => 1
            ),
            array(
                'event_type'    => 'address-transactions',
                'address'       => '1qMBuZnrmGoAc2MWyTnSgoLuWReDHNYyF',
                'confirmations' => 1
            )
        );
        $response = $client->batchSubscribeAddressTransactions($webhookID2, $batchData);
        $this->assertTrue($response);
        $response = $client->getWebhookEvents($webhookID2);
        $this->assertEquals(3, $response['total'], "'total' does not match expected value");
        $this->assertEquals(3, count($response['data']), "Count of events returned is not equal to 3");
        $this->assertEquals($batchData[2]['address'], $response['data'][2]['address'], "Batch created even not as expected");
    }

}
