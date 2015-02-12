<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\BlocktrailSDKInterface;

/**
 * Class WalletRecoveryTest
 *
 * ! IMPORTANT NODE !
 * throughout the test cases we use key_index=9999 to force an insecure development key on the API side
 *  this insecure key is used instead of the normal one so that we can reproduce the result on our staging environment
 *  without our private keys having to leaving our safe production environment
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
        //...
    }

    public function testUnspentOutputFinder() {
        //some addresses with known unspent outputs
        $addresses = array(
            '2NG3QEhJc1xzN5qxPdNAZfGaTdGAv3ixMbH',
        );

        $this->assertTrue(true);
    }

    public function testWalletSweep() {
        /*
         * We have set up a testnet wallet with known unspent outputs in certain addresses for this test
         */
        $walletIdentifier = "unittest-wallet-recovery";
        $walletPass = "password";
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

        $this->assertTrue(true);
    }
}