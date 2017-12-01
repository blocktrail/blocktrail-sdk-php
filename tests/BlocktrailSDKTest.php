<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\Connection\RestClientInterface;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\InvalidCredentials;

class BlocktrailSDKTest extends BlocktrailTestCase
{
    protected static $txExceptFields = [
        '.data.confirmations', '.data.estimated_value', '.data.estimated_change', '.data.estimated_change_address',
        '.data.time', '.data.block_time', '.data.block_hash',
        '.data.inputs.multisig', '.data.inputs.multisig_addresses',
        '.data.outputs.multisig', '.data.outputs.multisig_addresses', '.data.outputs.spent_index',
    ];

    public function testRestClient() {
        $client = $this->setupBlocktrailSDK();
        $this->assertTrue($client->getRestClient() instanceof RestClientInterface);
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
    }

    public function testAddress() {
        $client = $this->setupBlocktrailSDK();

        //address info
        $address = $client->address("3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief");
        $this->assertEqualsExceptKeys(\json_decode(\file_get_contents(__DIR__ . "/data/address.3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief.json"), true), $address,
            []);

        //address transactions
        $response = $client->addressTransactions("3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief", $page = 1, $limit = 20);
        $expectedResponse = \json_decode(\file_get_contents(__DIR__ . "/data/addressTxs.3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief.json"), true);
        self::sortByHash($expectedResponse['data']);
        self::sortByHash($response['data']);
        $this->assertEqualsExceptKeys($expectedResponse, $response,
            self::$txExceptFields);

        //address verification
        $response = $client->verifyAddress("16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z", "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('result', $response, "'result' key not in response");

        //address unconfirmed transactions
        $response = $client->addressUnspentOutputs("3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
    }

    public function testBlock() {
        $client = $this->setupBlocktrailSDK();

        //block info
        $response = $client->block("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
        $this->assertEquals("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf", $response['hash'], "Block hash returned does not match expected value");
//        file_put_contents(__DIR__ . "/data/block.000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf.json", \json_encode($response));
        $this->assertEqualsExceptKeys(\json_decode(file_get_contents(__DIR__ . "/data/block.000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf.json"), true), $response,
            ['confirmations', 'value']);

        //block info by height
        $response = $client->block(200000);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
        $this->assertEquals("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf", $response['hash'], "Block hash returned does not match expected value");
//        file_put_contents(__DIR__ . "/data/block.000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf.json", \json_encode($response));
        $this->assertEqualsExceptKeys(\json_decode(file_get_contents(__DIR__ . "/data/block.000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf.json"), true), $response,
            ['confirmations', 'value']);

        //all blocks
        $response = $client->allBlocks($page = 2, $limit = 23);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('total', $response, "'total' key not in response");
        $this->assertArrayHasKey('data', $response, "'data' key not in response");
        $this->assertEquals(23, count($response['data']), "Count of blocks returned is not equal to 23");

        $this->assertArrayHasKey('hash', $response['data'][0], "'hash' key not in first block of response");
        $this->assertArrayHasKey('hash', $response['data'][1], "'hash' key not in second block of response");

        //latest block
        $response = $client->blockLatest();
        $this->assertTrue(is_array($response), "Default response is not an array for latest block");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
    }

    public function testTransaction() {
        $client = $this->setupBlocktrailSDK();

        $response = $client->transaction("95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615");

        foreach ($response['outputs'] as &$output) {
            if ($output['spent_hash'] === null) {
                $output['spent_index'] = 0;
            }
        }

        $this->assertEqualsExceptKeys(\json_decode(file_get_contents(__DIR__ . "/data/tx.95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615.json"), true), $response,
            ['confirmations', 'value', 'first_seen_at', 'last_seen_at', 'estimated_value', 'estimated_change', 'estimated_change_address',
                'high_priority', 'enough_fee', 'contains_dust', 'double_spend_in']);

        //coinbase TX
        $response = $client->transaction("0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
        $this->assertArrayHasKey('confirmations', $response, "'confirmations' key not in response");
        $this->assertEquals("0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098", $response['hash'], "Transaction hash does not match expected value");

        //random TX 1
        $response = $client->transaction("c791b82ed9af681b73eadb7a05b67294c1c3003e52d01e03775bfb79d4ac58d1");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('hash', $response, "'hash' key not in response");
        $this->assertArrayHasKey('confirmations', $response, "'confirmations' key not in response");
        $this->assertEquals("c791b82ed9af681b73eadb7a05b67294c1c3003e52d01e03775bfb79d4ac58d1", $response['hash'], "Transaction hash does not match expected value");

        //coinbase TX
        $response = $client->transactions(["0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098", "c791b82ed9af681b73eadb7a05b67294c1c3003e52d01e03775bfb79d4ac58d1"]);
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertEquals("0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098", $response['data'][0]['hash']);
        $this->assertEquals("c791b82ed9af681b73eadb7a05b67294c1c3003e52d01e03775bfb79d4ac58d1", $response['data'][1]['hash']);
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

    private function assertEqualsExceptKeys($expected, $actual, $keys) {
        $expected1 = $expected;
        $actual1 = $actual;

        foreach (array_keys($actual1) as $key) {
            if (!array_key_exists($key, $expected1)) {
                unset($actual1[$key]);
            }
        }

        foreach ($keys as $key) {
            unset($expected1[$key]);
            unset($actual1[$key]);
        }

        if (isset($actual1['data'])) {
            $this->assertEquals(count($expected1['data']), count($actual1['data']));

            foreach ($actual1['data'] as $idx => $row) {
                foreach ($row as $key => $value) {
                    if (!array_key_exists($key, $expected1['data'][0])) {
                        unset($actual1['data'][$idx][$key]);
                    }
                }
            }

            if (isset($actual1['data'][0]['inputs'])) {
                foreach ($actual1['data'] as $idx => $row) {
                    foreach ($row['inputs'] as $inputIdx => $input) {
                        foreach ($input as $key => $value) {
                            if (!array_key_exists($key, $expected1['data'][$idx]['inputs'][$inputIdx])) {
                                unset($actual1['data'][$idx]['inputs'][$inputIdx][$key]);
                            }
                        }
                    }
                    foreach ($row['outputs'] as $outputIdx => $output) {
                        foreach ($output as $key => $value) {
                            if (!array_key_exists($key, $expected1['data'][$idx]['outputs'][$outputIdx])) {
                                unset($actual1['data'][$idx]['outputs'][$outputIdx][$key]);
                            }
                        }
                    }
                }
            }

            foreach ($keys as $key) {
                if (strpos($key, ".data.inputs.") === 0) {
                    $key1 = substr($key, strlen(".data.inputs."));

                    foreach ($expected1['data'] as &$expectedRow) {
                        foreach ($expectedRow['inputs'] as &$expectedInput) {
                            unset($expectedInput[$key1]);
                        }
                    }
                    foreach ($actual1['data'] as &$actualRow) {
                        foreach ($actualRow['inputs'] as &$actualInput) {
                            unset($actualInput[$key1]);
                        }
                    }
                } else if (strpos($key, ".data.outputs.") === 0) {
                    $key1 = substr($key, strlen(".data.outputs."));
                    foreach ($expected1['data'] as &$expectedRow) {
                        foreach ($expectedRow['outputs'] as &$expectedOutput) {
                            unset($expectedOutput[$key1]);
                        }
                    }
                    foreach ($actual1['data'] as &$actualRow) {
                        foreach ($actualRow['outputs'] as &$actualOutput) {
                            unset($actualOutput[$key1]);
                        }
                    }
                } else if (strpos($key, ".data.") === 0) {
                    $key1 = substr($key, strlen(".data."));
                    foreach ($expected1['data'] as &$expectedRow) {
                        unset($expectedRow[$key1]);
                    }
                    foreach ($actual1['data'] as &$actualRow) {
                        unset($actualRow[$key1]);
                    }
                }
            }
        }

        $this->assertEquals($expected1, $actual1);
    }

    public static function sortByHash(&$array) {
        \usort($array, function($a, $b) {
            return self::compareByHash($a, $b);
        });

        return $array;
    }

    public static function compareByHash($a, $b) {
        if ($a['hash'] == $b['hash']) {
            return 0;
        } else if ($a['hash'] > $b['hash']) {
            return -1;
        } else {
            return 1;
        }
    }
}
