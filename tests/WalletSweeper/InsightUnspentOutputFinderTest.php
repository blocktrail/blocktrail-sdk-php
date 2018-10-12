<?php

namespace Blocktrail\SDK\Tests\WalletSweeper;

use Blocktrail\SDK\Services\InsightUnspentOutputFinder;
use Blocktrail\SDK\Tests\Wallet\WalletTestBase;
use Psr\Http\Message\ResponseInterface;

class InsightUnspentOutputFinderTest extends WalletTestBase
{
    /**
     * needs this because we overload \GuzzleHttp\Client
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetUnspentOutputs() {
        $address1 = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk1 = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";

        $address2 = "2N7T4CD6CEuNHJoGKpoJH3YexqektXjyy6L";
        $spk2 = "a9149bce9399f9eeddad66635d4be171ec8f14decc5987";

        $testnet = true;

        $responseMock = \Mockery::mock(ResponseInterface::class)->makePartial();
        $responseMock->shouldReceive('getBody')
            ->once()
            ->andReturn(json_encode([
                [
                    'txid'       => "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
                    'vout'      => 0,
                    'amount'      => 99999999,
                    'scriptPubKey' => $spk1,
                    'address' => $address1,
                ],
                [
                    'txid'       => "4242424242424242424242424242424242424242424242424242424242424242",
                    'vout'      => 0,
                    'amount'      => 1111111,
                    'scriptPubKey' => $spk2,
                    'address' => $address2,
                ]
            ]));

        $clientMock = \Mockery::mock('overload:' . "\GuzzleHttp\Client");
        $clientMock->shouldReceive('post')
            ->times(1)
            ->withArgs(function ($url, array $options) use ($testnet, $address1, $address2) {
                $ok = true;
                $ok = $ok && $url == 'https://' . ($testnet ? 'test-' : '') . 'insight.bitpay.com/api/addrs/utxo';
                $ok = $ok && $options == [
                        'json' => ['addrs' => "$address1,$address2"],
                        'timeout' => 30,
                    ];
                return $ok;
            })->andReturn($responseMock);

        $finder = new InsightUnspentOutputFinder($testnet);
        $finder->getUTXOs([$address1, $address2]);
    }

    public function testGetUtxos()
    {
        $testnet = true;
        $address1 = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk1 = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $address2 = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk2 = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";

        $utxo1 = [
            'hash'       => "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
            'index'      => 0,
            'value'      => 99999999,
            'script_hex' => $spk1,
            'address' => $address1,
        ];
        $finder = $this->createMockFinder($testnet);
        $finder->shouldAllowMockingProtectedMethods();

        $finder->shouldReceive('getUnspentOutputs')
            ->once()
            ->withArgs(function (array $addresses) use ($address1, $address2) {
                return count($addresses) === 2
                    && $address1 == $addresses[0]
                    && $address2 == $addresses[1];
            })
            ->andReturn([
                $utxo1,
                [
                    'hash'       => "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
                    'index'      => 1,
                    'value'      => 10000000,
                    'script_hex' => $spk1,
                    'address' => $address1,
                ],
                [
                    'hash'       => "4242424242424242424242424242424242424242424242424242424242424242",
                    'index'      => 0,
                    'value'      => 1111111,
                    'script_hex' => $spk2,
                    'address' => $address2,
                ]
            ]);

        $result = $finder->getUTXOs([$address1, $address2]);
        $this->assertCount(3, $result);
        $this->assertEquals($utxo1, $result[0]);

    }

    /**
     * @param bool $bcash
     * @return \Mockery\Mock|InsightUnspentOutputFinder
     */
    protected function createMockFinder($testnet)
    {
        $client = \Mockery::mock(InsightUnspentOutputFinder::class, [$testnet])->makePartial();

        return $client;
    }
}
