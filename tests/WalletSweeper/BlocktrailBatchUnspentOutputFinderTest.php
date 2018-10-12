<?php

namespace Blocktrail\SDK\Tests\WalletSweeper;

use Blocktrail\SDK\Services\BlocktrailBatchUnspentOutputFinder;
use Blocktrail\SDK\Services\BlocktrailUnspentOutputFinder;
use Blocktrail\SDK\Tests\Wallet\WalletTestBase;

class BlocktrailBatchUnspentOutputFinderTest extends WalletTestBase
{
    /**
     * first 2 are with page=50, next 2 with 49
     * needs this because we overload BlocktrailSDK
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSetsPagination() {

        $address2 = "2N7T4CD6CEuNHJoGKpoJH3YexqektXjyy6L";
        $spk2 = "a9149bce9399f9eeddad66635d4be171ec8f14decc5987";
        $utxo3 = [
            'hash'       => "4242424242424242424242424242424242424242424242424242424242424242",
            'index'      => 0,
            'value'      => 1111111,
            'script_hex' => $spk2,
            'address' => $address2,
        ];

        $expectLim = 200;
        $externalMock = \Mockery::mock('overload:\Blocktrail\SDK\BlocktrailSDK');
        $externalMock->shouldReceive('batchAddressUnspentOutputs')
            ->times(4)
            ->withArgs(function ($addresses, $page, $limit) use ($address2, &$expectLim) {
                return count($addresses) === 1 &&
                    $address2 === $addresses[0] &&
                    $limit === $expectLim
                    ;
            })->andReturns(
                [
                    'data' => [$utxo3],
                    "current_page" => 1,
                    "per_page" => 20,
                    "total" => 1,
                ],
                [
                    'data' => [],
                    "current_page" => 2,
                    "per_page" => 20,
                    "total" => 1,
                ],
                [
                    'data' => [$utxo3],
                    "current_page" => 1,
                    "per_page" => 20,
                    "total" => 1,
                ],
                [
                    'data' => [],
                    "current_page" => 2,
                    "per_page" => 20,
                    "total" => 1,
                ]
            );

        $apiKey = getenv('BLOCKTRAIL_SDK_APIKEY') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APIKEY';
        $apiSecret = getenv('BLOCKTRAIL_SDK_APISECRET') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APISECRET';
        $network = 'rBTC';
        $testnet = true;
        $apiVersion = 'v1';
        $apiEndpoint = null;

        $finder = new BlocktrailBatchUnspentOutputFinder($apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint);
        $utxos = $finder->getUTXOs([$address2]);
        $this->assertCount(1, $utxos);
        $this->assertEquals($utxo3, $utxos[0]);

        $expectLim = 49;
        $finder->setPaginationLimit($expectLim);

        $finder->getUTXOs([$address2]);
        $this->assertCount(1, $utxos);
        $this->assertEquals($utxo3, $utxos[0]);
    }

    /**
     * needs this because we overload BlocktrailSDK
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function atestGetUnspentOutputs() {
        $address1 = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk1 = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";

        $address2 = "2N7T4CD6CEuNHJoGKpoJH3YexqektXjyy6L";
        $spk2 = "a9149bce9399f9eeddad66635d4be171ec8f14decc5987";
        $utxo3 = [
            'hash'       => "4242424242424242424242424242424242424242424242424242424242424242",
            'index'      => 0,
            'value'      => 1111111,
            'script_hex' => $spk2,
        ];

        $externalMock = \Mockery::mock('overload:\Blocktrail\SDK\BlocktrailSDK');
        $externalMock->shouldReceive('addressUnspentOutputs')
            ->times(2)
            ->withArgs(function ($address) use ($address1) {
                return $address1 === $address;
            })->andReturns(
                [
                    'data' => [
                        [
                            'hash'       => "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
                            'index'      => 0,
                            'value'      => 99999999,
                            'script_hex' => $spk1,
                        ]
                    ],
                    "current_page" => 1,
                    "per_page" => 20,
                    "total" => 1,
                ],
                [
                    'data' => [],
                    "current_page" => 2,
                    "per_page" => 20,
                    "total" => 1,
                ]
            );
        $externalMock->shouldReceive('addressUnspentOutputs')
            ->times(2)
            ->withArgs(function ($address) use ($address2) {
                return $address2 === $address;
            })->andReturns(
                [
                    'data' => [$utxo3],
                    "current_page" => 1,
                    "per_page" => 20,
                    "total" => 1,
                ],
                [
                    'data' => [],
                    "current_page" => 2,
                    "per_page" => 20,
                    "total" => 1,
                ]
            );

        $apiKey = getenv('BLOCKTRAIL_SDK_APIKEY') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APIKEY';
        $apiSecret = getenv('BLOCKTRAIL_SDK_APISECRET') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APISECRET';
        $network = 'rBTC';
        $testnet = true;
        $apiVersion = 'v1';
        $apiEndpoint = null;

        $finder = new BlocktrailUnspentOutputFinder($apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint);
        $finder->getUTXOs([$address1, $address2]);
    }

    public function atestGetUtxos()
    {
        $address1 = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk1 = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $utxo1 = [
            'hash'       => "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
            'index'      => 0,
            'value'      => 99999999,
            'script_hex' => $spk1,
        ];
        $utxo2 = [
            'hash'       => "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
            'index'      => 1,
            'value'      => 10000000,
            'script_hex' => $spk1,
        ];

        $address2 = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk2 = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";

        $utxo3 = [
            'hash'       => "4242424242424242424242424242424242424242424242424242424242424242",
            'index'      => 0,
            'value'      => 1111111,
            'script_hex' => $spk2,
        ];

        $finder = $this->createMockFinder();
        $finder->shouldAllowMockingProtectedMethods();

        $finder->shouldReceive('getUnspentOutputs')
            ->once()
            ->withArgs(function ($lookupAddress) use ($address1) {
                return $lookupAddress === $address1;
            })
            ->andReturns([
                $utxo1, $utxo2,
            ]);

        $finder->shouldReceive('getUnspentOutputs')
            ->once()
            ->withArgs(function ($lookupAddress) use ($address2) {
                return $lookupAddress === $address2;
            })
            ->andReturns([
                $utxo3,
            ]);

        $result = $finder->getUTXOs([$address1, $address2]);
        $this->assertCount(3, $result);

    }

    /**
     * @param bool $bcash
     * @return \Mockery\Mock|BlocktrailUnspentOutputFinder
     */
    protected function createMockFinder($bcash = false)
    {
        $apiKey = getenv('BLOCKTRAIL_SDK_APIKEY') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APIKEY';
        $apiSecret = getenv('BLOCKTRAIL_SDK_APISECRET') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APISECRET';
        if ($bcash) {
            $network = 'rBCH';
        } else {
            $network = 'rBTC';
        }
        $testnet = true;
        $apiVersion = 'v1';
        $apiEndpoint = null;

        $client = \Mockery::mock(BlocktrailUnspentOutputFinder::class, [$apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint])->makePartial();

        return $client;
    }
}
