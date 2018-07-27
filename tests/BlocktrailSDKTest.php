<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\Backend\ConverterInterface;
use Blocktrail\SDK\Connection\Response;
use Blocktrail\SDK\Connection\RestClientInterface;
use Blocktrail\SDK\BlocktrailSDK;
use Mockery\Mock;

class BlocktrailSDKTest extends \PHPUnit_Framework_TestCase
{

    public function tearDown() {
        parent::tearDown();

        \Mockery::close();
    }

    /**
     * @param string $network
     * @return MockBlocktrailSDK|Mock
     */
    protected function mockSDK($network = 'rBTC') {
        $apiKey = getenv('BLOCKTRAIL_SDK_APIKEY') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APIKEY';
        $apiSecret = getenv('BLOCKTRAIL_SDK_APISECRET') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APISECRET';
        $testnet = substr($network, 0, 1) === 'r' || substr($network, 0, 1) === 't';
        $apiVersion = 'v1';
        $apiEndpoint = null;

        $client = \Mockery::mock(MockBlocktrailSDK::class, [$apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint])->makePartial();

        return $client;
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

    public function testWalletBlockLatest()
    {
        $client = $this->mockSDK();
        $blocktrailClient = $client->setBlocktrailClient(\Mockery::mock(RestClientInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('{"hash":"000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f","height":0}'));

        $blocktrailClient->shouldReceive('get')
            ->withArgs(["block/latest"])
            ->andReturn($res)
            ->once();

        $walletTip = $client->getWalletBlockLatest();
        $this->assertArrayHasKey("hash", $walletTip);
        $this->assertArrayHasKey("height", $walletTip);
        $this->assertInternalType('string', $walletTip['hash']);
        $this->assertEquals(64, strlen($walletTip['hash']));
        $this->assertInternalType('int', $walletTip['height']);
    }
    public function testAddress() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('addrresponse'));

        $converter->shouldReceive('getUrlForAddress')
            ->withArgs(["3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief"])
            ->andReturn("address/3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["address/3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertAddress')
            ->withArgs(['addrresponse'])
            ->andReturn("addrresult")
            ->once();

        $this->assertEquals("addrresult", $client->address("3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief"));
    }

    public function testAddressTransactions() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('addrtxsresponse'));

        $converter->shouldReceive('getUrlForAddressTransactions')
            ->withArgs(["3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief"])
            ->andReturn("address/3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief/transactions")
            ->once();

        $converter->shouldReceive('paginationParams')
            ->withArgs([['page' => 1, 'limit' => 2, 'sort_dir' => 'asc']])
            ->andReturn("pagination")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["address/3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief/transactions", "pagination"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertAddressTxs')
            ->withArgs(['addrtxsresponse'])
            ->andReturn("addrtxsresult")
            ->once();

        $this->assertEquals("addrtxsresult", $client->addressTransactions("3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief", 1, 2));
    }

    public function testAddressUnspent() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('addrunspentresponse'));

        $converter->shouldReceive('getUrlForAddressUnspent')
            ->withArgs(["3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief"])
            ->andReturn("address/3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief/unspent")
            ->once();

        $converter->shouldReceive('paginationParams')
            ->withArgs([['page' => 1, 'limit' => 2, 'sort_dir' => 'asc']])
            ->andReturn("pagination")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["address/3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief/unspent", "pagination"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertAddressUnspentOutputs')
            ->withArgs(['addrunspentresponse', "3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief"])
            ->andReturn("addrunspentresult")
            ->once();

        $this->assertEquals("addrunspentresult", $client->addressUnspentOutputs("3EU8LRmo5PgcSwnkn6Msbqc8BKNoQ7Xief", 1, 2));
    }

    public function testVerifyAddress() {
        $client = $this->mockSDK('BTC');

        //address verification
        $response = $client->verifyAddress("16dwJmR4mX5RguGrocMfN9Q9FR2kZcLw2z", "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk=");
        $this->assertTrue(is_array($response), "Default response is not an array");
        $this->assertArrayHasKey('result', $response, "'result' key not in response");
    }

    public function testBlockByHash() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('blockresponse'));

        $converter->shouldReceive('getUrlForBlock')
            ->withArgs(["000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf"])
            ->andReturn("block/000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["block/000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertBlock')
            ->withArgs(['blockresponse'])
            ->andReturn("blockresult")
            ->once();

        $this->assertEquals("blockresult", $client->block("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf"));
    }

    public function testBlockByHeight() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('blockresponse'));

        $converter->shouldReceive('getUrlForBlock')
            ->withArgs(["123321"])
            ->andReturn("block/123321")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["block/123321"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertBlock')
            ->withArgs(['blockresponse'])
            ->andReturn("blockresult")
            ->once();

        $this->assertEquals("blockresult", $client->block("123321"));
    }

    public function testBlockLatest() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('blockresponse'));

        $converter->shouldReceive('getUrlForBlock')
            ->withArgs(["latest"])
            ->andReturn("block/latest")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["block/latest"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertBlock')
            ->withArgs(['blockresponse'])
            ->andReturn("blockresult")
            ->once();

        $this->assertEquals("blockresult", $client->block("latest"));
    }

    public function testBlockTransactions() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('blocktxsresponse'));

        $converter->shouldReceive('getUrlForBlockTransaction')
            ->withArgs(["000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf"])
            ->andReturn("block/000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf/transactions")
            ->once();

        $converter->shouldReceive('paginationParams')
            ->withArgs([['page' => 1, 'limit' => 2, 'sort_dir' => 'asc']])
            ->andReturn("pagination")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["block/000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf/transactions", "pagination"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertBlockTxs')
            ->withArgs(['blocktxsresponse'])
            ->andReturn("blocktxsresult")
            ->once();

        $this->assertEquals("blocktxsresult", $client->blockTransactions("000000000000034a7dedef4a161fa058a2d67a173a90155f3a2fe6fc132e0ebf", 1, 2));
    }

    public function testAllBlocks() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('allblocksresponse'));

        $converter->shouldReceive('getUrlForAllBlocks')
            ->withArgs([])
            ->andReturn("all-blocks")
            ->once();

        $converter->shouldReceive('paginationParams')
            ->withArgs([['page' => 1, 'limit' => 2, 'sort_dir' => 'asc']])
            ->andReturn("pagination")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["all-blocks", "pagination"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertBlocks')
            ->withArgs(['allblocksresponse'])
            ->andReturn("allblocksresult")
            ->once();

        $this->assertEquals("allblocksresult", $client->allBlocks(1, 2));
    }

    public function testTransaction() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('txresponse'));

        $converter->shouldReceive('getUrlForTransaction')
            ->withArgs(["95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615"])
            ->andReturn("tx/95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["tx/95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertTx')
            ->withArgs(['txresponse', null])
            ->andReturn("txresult")
            ->once();

        $this->assertEquals("txresult", $client->transaction("95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615"));
    }

    public function testTransactions() {
        $client = $this->mockSDK();
        $dataClient = $client->setDataClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('txsresponse'));

        $converter->shouldReceive('getUrlForTransactions')
            ->withArgs([["95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615", "0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098"]])
            ->andReturn("txs/95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615,0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098")
            ->once();

        $dataClient->shouldReceive('get')
            ->withArgs(["txs/95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615,0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098"])
            ->andReturn($res)
            ->once();

        $converter->shouldReceive('convertTxs')
            ->withArgs(['txsresponse'])
            ->andReturn("txsresult")
            ->once();

        $this->assertEquals("txsresult", $client->transactions([
            "95740451ac22f63c42c0d1b17392a0bf02983176d6de8dd05d6f06944d93e615",
            "0e3e2357e806b6cdb1f70b54c3a3a17b6714ee1f0e68bebb44a74b1efd512098"
        ]));
    }

    public function testPrice() {
        $client = $this->mockSDK();
        $blocktrailClient = $client->setBlocktrailClient(\Mockery::mock(RestClientInterface::class));
        $converter = $client->setConverter(\Mockery::mock(ConverterInterface::class));
        $res = new Response(200, \GuzzleHttp\Psr7\stream_for('{"USD": 1}'));

        $blocktrailClient->shouldReceive('get')
            ->withArgs(["price"])
            ->andReturn($res)
            ->once();

        $this->assertEquals(["USD" => 1], $client->price());
    }
}
