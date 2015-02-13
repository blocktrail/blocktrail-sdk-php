<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\BlocktrailSDKInterface;
use Blocktrail\SDK\Services\BlocktrailBitcoinService;
use Blocktrail\SDK\UnspentOutputFinder;
use Blocktrail\SDK\WalletSweeper;

/**
 * Class WalletRecoveryTest
 * We have set up a testnet wallet with known unspent outputs in certain addresses for these test
 *
 *
 * @package Blocktrail\SDK\Tests
 */
class WalletRecoveryTest extends \PHPUnit_Framework_TestCase {

    protected $wallets = [];

    /**
     * setup an instance of BlocktrailSDK
     *
     * @return BlocktrailSDKInterface
     */
    public function setupBlocktrailSDK() {
        $client = new BlocktrailSDK("MY_APIKEY", "MY_APISECRET", "BTC", true, 'v1');
        // $client->setCurlDebugging();
        return $client;
    }

    protected function tearDown() {
        $this->cleanUp();
    }

    protected function onNotSuccessfulTest(\Exception $e) {
        //called when a test fails
        $this->cleanUp();
        throw $e;
    }

    protected function cleanUp() {
        //No cleanup to do
    }

    public function testBlocktrailBicoinService() {
        //test the blocktrail bitcoin data service provider
        $blockchainDataService = new BlocktrailBitcoinService("MY_APIKEY", "MY_APISECRET", "BTC", true, 'v1');

        //get unspent outputs for a single address
        $address = '2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA';   //has 0.2 tbtc in 2 utxos
        $result = $blockchainDataService->getUnspentOutputs($address);
        $this->assertEquals(2, count($result));
        $this->assertArrayHasKey('hash', $result[0]);
        $this->assertArrayHasKey('index', $result[0]);
        $this->assertArrayHasKey('value', $result[0]);
        $this->assertArrayHasKey('script_hex', $result[0]);
        $this->assertEquals(10000000, $result[0]['value']);
        $this->assertEquals(10000000, $result[1]['value']);

        //ensure all outputs are found when dealing with paginated results (pagination forced by limiting to 1 result per page)
        $blockchainDataService->setPaginationLimit(1);
        $result = $blockchainDataService->getUnspentOutputs($address);
        $this->assertEquals(2, count($result), "failed to find all utxos when results are paginated");

        //attempt to get unspent outputs for an empty address
        $address = '2Mu1xrQAEd8LsiRHNvgXDaU8kQU5WKqzCq7';   //has 0 tbtc in 0 utxos
        $result = $blockchainDataService->getUnspentOutputs($address);
        $this->assertEquals(0, count($result));
    }

    public function testUnspentOutputFinder() {
        //some addresses with known unspent outputs, and some without any
        $addresses = array(
            '2NG3QEhJc1xzN5qxPdNAZfGaTdGAv3ixMbH',      //has 0.1 tbtc in 1 utxo
            '2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA',      //has 0.2 tbtc in 2 utxos
            '2Mu1xrQAEd8LsiRHNvgXDaU8kQU5WKqzCq7',      //has 0 tbtc
            '2N9ijhGSX3kGbe16RMCQ2hviH8RLAVdaqZg'       //has 0.1 tbtc in 1 utxo
        );

        $blockchainDataService = new BlocktrailBitcoinService("MY_APIKEY", "MY_APISECRET", "BTC", true, 'v1');
        $unspenOutputFinder = new UnspentOutputFinder($blockchainDataService);

        //get unspent outputs for an array of addresses
        $result = $unspenOutputFinder->getUTXOs($addresses);
        $this->assertEquals(3, count($result), "expected results for 3 of the 4 given addresses");
        $this->assertArrayHasKey('2NG3QEhJc1xzN5qxPdNAZfGaTdGAv3ixMbH', $result);
        $this->assertArrayHasKey('2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA', $result);
        $this->assertArrayHasKey('2N9ijhGSX3kGbe16RMCQ2hviH8RLAVdaqZg', $result);
        $this->assertEquals(2, count($result['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA']), "expected address to have 2 unspent outputs");
        $this->assertArrayHasKey('hash', $result['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA'][0]);
        $this->assertArrayHasKey('index', $result['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA'][0]);
        $this->assertArrayHasKey('value', $result['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA'][0]);
        $this->assertArrayHasKey('script_hex', $result['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA'][0]);
        $this->assertEquals(10000000, $result['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA'][0]['value']);
        $this->assertEquals(10000000, $result['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA'][1]['value']);

        $total = $result['2NG3QEhJc1xzN5qxPdNAZfGaTdGAv3ixMbH'][0]['value'] + $result['2N9ijhGSX3kGbe16RMCQ2hviH8RLAVdaqZg'][0]['value']
                + $result['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA'][0]['value'] + $result['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA'][1]['value'];
        $this->assertEquals(40000000, $total);
    }

    public function testWalletSweep() {
        /*
         * We have set up a testnet wallet with known unspent outputs in certain addresses for this test
         */
        $increment = 10;            //using a search increment of 10 for speed, as we know our funds are no more than 10 addresses apart from each other
        $primaryPassphrase = "password";
        $primaryMnemonic = "olive six drill desk jealous nice chronic draw reveal super already stick wear hurt aunt crazy step mechanic derive already kangaroo render tenant honey large cabin better guitar biology metal angry tide boat father slam title maple notice salmon shy mass shock dog cream twelve strong marble sudden";
        $backupMnemonic = "adapt finger below junk slam power opinion finish vapor measure code know stove mom confirm design chaos goat cradle mansion target fuel empty fox pill recycle brisk flush swap chimney dance mind brass moral stay shoulder slide shove march wise animal frame shed require alien moral onion auto";
        $blocktrailKeys = array(
            [
                'keyIndex'=> 0,
                'path' => "M/0'",
                'pubkey' => 'tpubD8UrAbbGkiJUmxDS9UxC6bvSGVd1vAEDMMkMBHTJ7xMMnkNuvBsVMQv6fXxAQgV3aaETetdaBBNQgULBzebM86MyYP526Ggqu8K8jPwBdP4',
            ],
            [
                'keyIndex'=> 9999,
                'path' => "M/9999'",
                'pubkey' => 'tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ',
            ]
        );

        $bitcoinClient = new BlocktrailBitcoinService("MY_APIKEY", "MY_APISECRET", "BTC", true, 'v1');

        //create the wallet sweeper and do fund discovery
        $walletSweeper = new WalletSweeper($primaryMnemonic, $primaryPassphrase, $backupMnemonic, $blocktrailKeys, $bitcoinClient, 'btc', true);
        //$walletSweeper->enableLogging();    //can enable logging if test is taking too long or something seems to be wrong. NB: this test will take a long time - be patient

        $results = $walletSweeper->discoverWalletFunds($increment);
        $this->assertEquals(4, $results['count'], "expected utxo count to be 4");
        $this->assertEquals(40000000, $results['balance'], "unexpected balance amount");
        $this->assertGreaterThanOrEqual(50, $results['addressesSearched'], "expected at least 50 addresses to be searched");
        $this->assertEquals(3, count($results['utxos']), "expected 3 addresses to be found to have unspent outputs");
        $this->assertEquals(2, count($results['utxos']['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA']['utxos']), "expected particular address to have 2 unspent outputs");
        $this->assertArrayHasKey('hash', $results['utxos']['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA']['utxos'][0]);
        $this->assertArrayHasKey('index', $results['utxos']['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA']['utxos'][0]);
        $this->assertArrayHasKey('value', $results['utxos']['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA']['utxos'][0]);
        $this->assertArrayHasKey('script_hex', $results['utxos']['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA']['utxos'][0]);
        $this->assertArrayHasKey('path', $results['utxos']['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA']);
        $this->assertArrayHasKey('redeem', $results['utxos']['2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA']);

        //do fund sweeping - will carry out fund discovery if needed (already completed above) and then create and sign a transaction
        $destination = '2NA7zpiq5PcYUx6oraEwz8zPzn6HefSvdLA';
        $results = $walletSweeper->sweepWallet($destination, $increment);
        $this->assertArrayHasKey('hex', $results);
        $this->assertEquals('true', $results['complete']);
        $this->assertEquals(8, $results['req_sigs']);
        $this->assertEquals(8, $results['sign_count']);
    }
}
