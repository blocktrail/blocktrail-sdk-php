<?php
/**
 * Created by PhpStorm.
 * User: tk
 * Date: 9/28/17
 * Time: 9:27 PM
 */

namespace Blocktrail\SDK\Tests;


use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Tests\RestClient\MockRestClient;
use GuzzleHttp\Psr7\Request;

class BlocktrailSdkUnitTest extends BlocktrailTestCase
{
    /**
     * @param string $network
     * @param bool $testnet
     * @param string $apiVersion
     * @param null $apiEndpoint
     * @return \Blocktrail\SDK\BlocktrailSDK
     */
    public function setupMockAdapter($network = 'BTC', $testnet = false, $apiVersion = 'v1', $apiEndpoint = null)
    {
        $apiKey = "41414141414141414141414141414141";
        $apiSecret = "42414141424141414241414142414141";

        $sdk = $this->setupBlocktrailSDK($network, $testnet, $apiVersion, $apiEndpoint);
        $sdk->setRestClient(new MockRestClient($apiEndpoint, $apiVersion, $apiKey, $apiSecret));

        return $sdk;
    }

    /**
     * @param array $result
     */
    public function checkGetAddressResult(array $result)
    {
        foreach ([
                     "address", "hash160", "balance", "received",
                     "sent", "transactions", "utxos", "unconfirmed_received",
                     "unconfirmed_sent", "unconfirmed_transactions",
                     "unconfirmed_utxos","total_transactions_in",
                     "total_transactions_out","first_seen","last_seen",
                 ] as $knownKey) {
            $this->assertTrue(array_key_exists($knownKey, $result));
        }
    }

    /**
     * @return \Closure
     */
    public function getSuccessfulGetBlockHandler($blockHash) {
        $params = ["block",$blockHash];
        $response = '{"hash":"00000000000000000009f3b8a14e543ec6690d4002a1beb78f6cc46b270d5398","version":"536870912","height":487404,"block_time":"2017-09-28T20:45:17+0000","arrival_time":"2017-09-28T20:46:04+0000","nonce":345019421,"difficulty":1000000000000,"merkleroot":"ec2e79b36e24e9147fccfa097039d9b19ebf3ae8e8df328496c8f24a305fb4d5","is_orphan":false,"byte_size":664353,"confirmations":1,"transactions":1093,"value":1079019267187,"miningpool_name":"AntPool","miningpool_url":"https:\/\/antpool.com","miningpool_slug":"antpool","prev_block":"000000000000000000037b297356bffa1837c50cf952caea3160d6efe9297c4c","next_block":null}';
        return $this->makeMockHandler($params, $response);
    }

    /**
     * @return \Closure
     */
    public function getSuccessfulLatestBlockHandler() {
        $params = ["block","latest"];
        $response = '{"hash":"00000000000000000009f3b8a14e543ec6690d4002a1beb78f6cc46b270d5398","version":"536870912","height":487404,"block_time":"2017-09-28T20:45:17+0000","arrival_time":"2017-09-28T20:46:04+0000","nonce":345019421,"difficulty":1000000000000,"merkleroot":"ec2e79b36e24e9147fccfa097039d9b19ebf3ae8e8df328496c8f24a305fb4d5","is_orphan":false,"byte_size":664353,"confirmations":1,"transactions":1093,"value":1079019267187,"miningpool_name":"AntPool","miningpool_url":"https:\/\/antpool.com","miningpool_slug":"antpool","prev_block":"000000000000000000037b297356bffa1837c50cf952caea3160d6efe9297c4c","next_block":null}';
        return $this->makeMockHandler($params, $response);
    }

    /**
     * @param $address
     * @return \Closure
     */
    public function getSuccessfulGetAddressHandler($address) {
        $params = ["address", $address];
        $response = '{"address":"18cBEMRxXHqzWWCxZNtU91F5sbUNKhL5PX","hash160":"536FFA992491508DCA0354E52F32A3A7A679A53A","balance":18747307594,"received":6254591973168,"sent":6235844665574,"transactions":5207,"utxos":14,"unconfirmed_received":0,"unconfirmed_sent":0,"unconfirmed_transactions":0,"unconfirmed_utxos":0,"total_transactions_in":4549,"total_transactions_out":660,"category":null,"tag":null,"first_seen":"2016-06-05T10:30:43+0000","last_seen":"2017-09-28T20:05:52+0000"}';
        return $this->makeMockHandler($params, $response);
    }

    /**
     * @param array $handlers
     * @return BlocktrailSDK
     */
    public function makeMockAdapterSdk(array $handlers) {
        $sdk = $this->setupMockAdapter();
        /** @var MockRestClient $adapter */
        $adapter = $sdk->getRestClient();

        foreach ($handlers as $handler) {
            $adapter->expectRequest($handler);
        }

        return $sdk;
    }

    /**
     * @param array $expectedParams
     * @param $response
     * @param int $statusCode
     * @param array $headers
     * @return \Closure
     */
    public function makeMockHandler(array $expectedParams, $response, $statusCode = 200, array $headers = [])
    {
        return function (Request $request) use ($expectedParams, $response, $statusCode, $headers) {
            $numExpected = count($expectedParams);
            $withoutQuery = explode("?", $request->getUri())[0];
            $params = explode("/", $withoutQuery);
            if (count($params) < $numExpected) {
                throw new \RuntimeException("Invalid url submitted");
            }

            $lastN = array_slice($params, 0 - $numExpected);
            for ($i = 0; $i < $numExpected; $i++) {
                $this->assertEquals($expectedParams[$i], $lastN[$i]);
            }

            return [$statusCode, $headers, $response];
        };
    }

    /**
     * @param array $handlers
     * @param $testArgs
     * @return array
     */
    public function makeSdkTest(array $handlers, $testArgs) {
        $sdks = [];
        if (getenv('TEST_LIVE_SDK')) {
            $sdks[] = array_merge([$this->setupBlocktrailSDK()], $testArgs);
        }

        $sdks[] = array_merge([$this->makeMockAdapterSdk($handlers)], $testArgs);
        return $sdks;
    }

    /**
     * @return array
     */
    public function getAddressSdkProvider() {
        $address = "18cBEMRxXHqzWWCxZNtU91F5sbUNKhL5PX";
        return $this->makeSdkTest([$this->getSuccessfulGetAddressHandler($address)], [$address]);
    }

    /**
     * @param BlocktrailSDK $sdk
     * @param $address
     * @dataProvider getAddressSdkProvider
     */
    public function testGetAddressFromApi(BlocktrailSDK $sdk, $address)
    {
        $result = $sdk->address("18cBEMRxXHqzWWCxZNtU91F5sbUNKhL5PX");
        $this->checkGetAddressResult($result);
        $this->assertEquals($address, $result['address']);
    }

    /**
     * @return array
     */
    public function latestBlockSdkProvider() {
        return $this->makeSdkTest([$this->getSuccessfulLatestBlockHandler()], []);
    }

    /**
     * @param BlocktrailSDK $sdk
     * @dataProvider latestBlockSdkProvider
     */
    public function testLatestBlockFromApi(BlocktrailSDK $sdk)
    {
        $result = $sdk->blockLatest();
        $this->assertEquals(64, strlen($result['hash']));
    }

    /**
     * @return array
     */
    public function getBlockSdkProvider() {
        $blockHash = '00000000000000000009f3b8a14e543ec6690d4002a1beb78f6cc46b270d5398';
        return $this->makeSdkTest([$this->getSuccessfulGetBlockHandler($blockHash)], [$blockHash]);
    }

    /**
     * @param BlocktrailSDK $sdk
     * @dataProvider getBlockSdkProvider
     */
    public function testGetBlockFromApi(BlocktrailSDK $sdk, $blockHash)
    {
        $result = $sdk->block($blockHash);
        $this->assertEquals($blockHash, $result['hash']);
        $this->assertEquals(64, strlen($result['hash']));
    }

}
