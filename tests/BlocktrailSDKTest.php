<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\Connection\RestClientInterface;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\InvalidCredentials;

class BlocktrailSDKTest extends BlocktrailTestCase
{
    public function testRestClient() {
        $client = $this->setupBlocktrailSDK();
        $this->assertTrue($client->getRestClient() instanceof RestClientInterface);
    }

    public function testUpgradeKeyIndex() {
        $this->markTestIncomplete("@TODO: test upgrade key index");
    }

    public function testSatoshiConversion() {
        $toSatoshi = [
            ["0.00000001",          "1",                    1],
            [0.00000001,            "1",                    1],
            ["0.29560000",          "29560000",             29560000],
            [0.29560000,            "29560000",             29560000],
            ["1.0000009",           "100000090",            100000090],
            [1.0000009,             "100000090",            100000090],
            ["1.00000009",          "100000009",            100000009],
            [1.00000009,            "100000009",            100000009],
            ["21000000.00000001",   "2100000000000001",     2100000000000001],
            [21000000.00000001,     "2100000000000001",     2100000000000001],
            ["21000000.0000009",    "2100000000000090",     2100000000000090],
            [21000000.0000009,      "2100000000000090",     2100000000000090],
            ["21000000.00000009",   "2100000000000009",     2100000000000009],
            [21000000.00000009,     "2100000000000009",     2100000000000009], // this is the max possible amount of BTC (atm)
            ["210000000.00000009",  "21000000000000009",    21000000000000009],
            [210000000.00000009,    "21000000000000009",    21000000000000009],
            // thee fail because when the BTC value is converted to a float it looses precision
            // ["2100000000.00000009", "210000000000000009", 210000000000000009],
            // [2100000000.00000009,   "210000000000000009", 210000000000000009],
        ];

        $toBTC = [
            ["1",                   "0.00000001"],
            [1,                     "0.00000001"],
            ["29560000",            "0.29560000"],
            [29560000,              "0.29560000"],
            ["100000090",           "1.00000090"],
            [100000090,             "1.00000090"],
            ["100000009",           "1.00000009"],
            [100000009,             "1.00000009"],
            ["2100000000000001",    "21000000.00000001"],
            [2100000000000001,      "21000000.00000001"],
            ["2100000000000090",    "21000000.00000090"],
            [2100000000000090,      "21000000.00000090"],
            ["2100000000000009",    "21000000.00000009"], // this is the max possible amount of BTC (atm)
            [2100000000000009,      "21000000.00000009"],
            ["21000000000000009",   "210000000.00000009"],
            [21000000000000009,     "210000000.00000009"],
            ["210000000000000009",  "2100000000.00000009"],
            [210000000000000009,    "2100000000.00000009"],
            ["2100000000000000009", "21000000000.00000009"],
            [2100000000000000009,   "21000000000.00000009"],
            // these fail because they're > PHP_INT_MAX
            // ["21000000000000000009", "210000000000.00000009"],
            // [21000000000000000009,   "210000000000.00000009"],
        ];

        foreach ($toSatoshi as $i => $test) {
            $btc = $test[0];
            $satoshiString = $test[1];
            $satoshiInt = $test[2];

            $string = BlocktrailSDK::toSatoshiString($btc);
            $this->assertEquals($satoshiString, $string, "[{$i}] {$btc} => {$satoshiString} =? {$string}");
            $this->assertTrue($satoshiString === $string, "[{$i}] {$btc} => {$satoshiString} ==? {$string}");

            $int = BlocktrailSDK::toSatoshi($btc);
            $this->assertEquals($satoshiInt, $int, "[{$i}] {$btc} => {$satoshiInt} =? {$int}");
            $this->assertTrue($satoshiInt === $int, "[{$i}] {$btc} => {$satoshiInt} ==? {$int}");
        }
        foreach ($toBTC as $i => $test) {
            $satoshi = $test[0];
            $btc = $test[1];

            $this->assertEquals($btc, BlocktrailSDK::toBTC($satoshi), "[{$i}] {$satoshi} => {$btc}");
            $this->assertTrue($btc === BlocktrailSDK::toBTC($satoshi), "[{$i}] {$satoshi} => {$btc}");
        }

        $this->markTestIncomplete("@TODO: test toBTCString");
    }

    public function testSigning() {
        $client = $this->setupBadBlocktrailSDK();

        $e = null;
        try {
            $client->verifyAddress("16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z", "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=");
        } catch (InvalidCredentials $e) {
        }
        $this->assertTrue(!!$e, "Bad keys still succeeded");

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
        $response = $client->addressTransactions("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp", $page = 1, $limit = 23);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertEquals(23, count($response['data']), "Count of address transactions returned is not equal to 23");

        //address unconfirmed transactions
        $response = $client->addressUnconfirmedTransactions("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp", $page = 1, $limit = 23);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        // $this->assertGreaterThanOrEqual(count($response['data']), $response['total'], "Total records found on server is less than the count of records returned");

        //address unspent outputs
        $response = $client->addressUnspentOutputs("1dice8EMZmqKvrGE4Qc9bUFf9PX3xaYDp", $page = 1, $limit = 23);
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
        $response = $client->blockTransactions("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf", $page = 1, $limit = 23);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertEquals(23, count($response['data']), "Count of transactions returned is not equal to 23");

        //all blocks
        $response = $client->allBlocks($page = 2, $limit = 23);
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
        $response = $client->transaction("0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
        $this->assertArrayHasKey('confirmations', $response, "'confirmations' key not in response");
        $this->assertEquals("0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098", $response['hash'], "Transaction hash does not match expected value");

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

    public function testPrice() {
        $price = $this->setupBlocktrailSDK()->price();

        $this->assertTrue(is_float($price['USD']) || is_int($price['USD']), "is float or int [{$price['USD']}]");
        $this->assertTrue($price['USD'] > 0, "is above 0 [{$price['USD']}]");
    }

    public function testVerifyMessage() {
        $client = $this->setupBlocktrailSDK();


        $address = "1F26pNMrywyZJdr22jErtKcjF8R3Ttt55G";
        $message = $address;
        $signature = "H85WKpqtNZDrajOnYDgUY+abh0KCAcOsAIOQwx2PftAbLEPRA7mzXA/CjXRxzz0MC225pR/hx02Vf2Ag2x33kU4=";

        // test locally
        $this->assertTrue($client->verifyMessage($message, $address, $signature));

        // test using the API for it
        $response = $client->getRestClient()->post("verify_message", null, ['message' => $message, 'address' => $address, 'signature' => $signature]);
        $this->assertTrue(json_decode($response->body(), true)['result']);
    }
}
