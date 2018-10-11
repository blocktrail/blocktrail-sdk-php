<?php

namespace Blocktrail\SDK\Tests\Wallet;

use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\SignInfo;
use Blocktrail\SDK\TransactionBuilder;
use Blocktrail\SDK\Wallet;
use Mockery\Mock;

class SendTxTest extends WalletTestBase {
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage feeStrategy should be set to force_fee to set a forced fee
     */
    public function testForcedFeeInvalidStrategy() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $addr = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $value = BlocktrailSDK::toSatoshi(1.0);
        $pay = [$addr => $value];

        $wallet->pay($pay, $addr, false, false, Wallet::FEE_STRATEGY_OPTIMAL, 4096);
    }
    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Wallet needs to be unlocked to pay
     */
    public function test_sendTxRequiresUnlockedWallet() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $wallet->lock();

        $wallet->_sendTx(new Transaction(), [], true);
    }
    public function testSendTx() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $addr = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $value = BlocktrailSDK::toSatoshi(1.0);
        $pay = [$addr => $value];

        $this->shouldCoinSelect($client, $identifier, $value, $spk, [
            [
                'scriptpubkey_hex' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'path' => 'M/9999\'/0/0',
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ],
        ]);

        $partiallySignedTx = "0100000001ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e00000000b500483045022100a408d1845fe3b2980030cacfbcfb9cf0580d7e93ec3f048bf19c002c1066f74f022060b6001d631fc43c3e9a1764ff7c985226f10647d8aadb4bdce9cfc98d792f3b014c695221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153aeffffffff0200e1f5050000000017a9143a0fbfa2f446af8d76ac7f174618e74486746069876edaa4350000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698700000000";
        $baseTx = $partiallySignedTx;
        $coSignedTx = "ok";

        $client->shouldReceive('sendTransaction')
            ->withArgs([
                $identifier,
                ['signed_transaction' => $partiallySignedTx, 'base_transaction' => $baseTx],
                ["M/9999'/0/0"],
                true
            ])
            ->andReturn($coSignedTx)
            ->once();

        $this->assertEquals($coSignedTx, $wallet->pay($pay, $addr, false, false, Wallet::FEE_STRATEGY_OPTIMAL));
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Wallet needs to be unlocked to pay
     */
    public function testSendTxUnlockRequired() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, null, false, [
            'readonly' => true,
        ]);

        $addr = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $value = BlocktrailSDK::toSatoshi(1.0);
        $pay = [$addr => $value];

        $wallet->pay($pay, $addr, false, false, Wallet::FEE_STRATEGY_OPTIMAL);
    }

    public function testSendTxLateUnlock() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, null, false, [
            'readonly' => true,
        ]);

        $wallet->unlock([
            'password' => self::WALLET_PASSWORD,
        ]);

        $addr = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $value = BlocktrailSDK::toSatoshi(1.0);
        $pay = [$addr => $value];

        $this->shouldCoinSelect($client, $identifier, $value, $spk, [
            [
                'scriptpubkey_hex' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'path' => 'M/9999\'/0/0',
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ],
        ]);

        $partiallySignedTx = "0100000001ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e00000000b500483045022100a408d1845fe3b2980030cacfbcfb9cf0580d7e93ec3f048bf19c002c1066f74f022060b6001d631fc43c3e9a1764ff7c985226f10647d8aadb4bdce9cfc98d792f3b014c695221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153aeffffffff0200e1f5050000000017a9143a0fbfa2f446af8d76ac7f174618e74486746069876edaa4350000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698700000000";
        $baseTx = $partiallySignedTx;
        $coSignedTx = "ok";

        $client->shouldReceive('sendTransaction')
            ->withArgs([
                $identifier,
                ['signed_transaction' => $partiallySignedTx, 'base_transaction' => $baseTx],
                ["M/9999'/0/0"],
                true
            ])
            ->andReturn($coSignedTx)
            ->once();

        $this->assertEquals($coSignedTx, $wallet->pay($pay, $addr, false, false, Wallet::FEE_STRATEGY_OPTIMAL));
    }

    public function testSendLowPriorityFeeTxRandomizeChange() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $addr = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $value = BlocktrailSDK::toSatoshi(1.0);
        $pay = [$addr => $value];

        $this->shouldCoinSelect($client, $identifier, $value, $spk, [
            [
                'scriptpubkey_hex' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'path' => 'M/9999\'/0/0',
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ],
        ], true, true, Wallet::FEE_STRATEGY_LOW_PRIORITY);

        $partiallySignedTx = "0100000001ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e00000000b40047304402201b13751d27f736b27c771bd9b043ef8f976ef797ddcff38074c667bee5c8879f0220661f6fe754949d864228a9a495589a7e1ade9adccdbfc323d8f01d342f210b04014c695221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153aeffffffff0200e1f5050000000017a9143a0fbfa2f446af8d76ac7f174618e7448674606987b7e1a4350000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698700000000";
        $baseTx = $partiallySignedTx;
        $coSignedTx = "ok";

        // randomize change will shuffle
        $client->shouldReceive('shuffle')
            ->andReturns()
            ->once();

        $client->shouldReceive('sendTransaction')
            ->withArgs([
                $identifier,
                ['signed_transaction' => $partiallySignedTx, 'base_transaction' => $baseTx],
                ["M/9999'/0/0"],
                true
            ])
            ->andReturn($coSignedTx)
            ->once();

        $this->assertEquals($coSignedTx, $wallet->pay($pay, $addr, true, true, Wallet::FEE_STRATEGY_LOW_PRIORITY));
    }

    public function testSendForceFeeTx() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $addr = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $value = BlocktrailSDK::toSatoshi(1.0);
        $fee = BlocktrailSDK::toSatoshi(0.5);
        $pay = [$addr => $value];

        $this->shouldCoinSelect($client, $identifier, $value, $spk, [
            [
                'scriptpubkey_hex' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'path' => 'M/9999\'/0/0',
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ],
        ], true, true, Wallet::FEE_STRATEGY_FORCE_FEE, $fee);

        $partiallySignedTx = "0100000001ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e00000000b500483045022100e7201b73be42d0dfa2e9ad6190b6bd787ea4c8b1ed0282192f5b0c6c633c5d910220505a02281d4ac4c7f95a0cfc20e9b82ae9cff26b8bdb055cab689aba2adb34c2014c695221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153aeffffffff0200e1f5050000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698780f8a9320000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698700000000";
        $baseTx = $partiallySignedTx;
        $coSignedTx = "ok";

        // randomize change will shuffle
        $client->shouldReceive('shuffle')
            ->andReturns()
            ->once();

        $client->shouldReceive('sendTransaction')
            ->withArgs([
                $identifier,
                ['signed_transaction' => $partiallySignedTx, 'base_transaction' => $baseTx],
                ["M/9999'/0/0"],
                true
            ])
            ->andReturn($coSignedTx)
            ->once();

        $this->assertEquals($coSignedTx, $wallet->pay($pay, $addr, true, true, Wallet::FEE_STRATEGY_FORCE_FEE, $fee));
    }

    public function testSendToBech32() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $pubKeyHash = Buffer::hex('5d6f02f47dc6c57093df246e3742cfe1e22ab410');
        $wp = WitnessProgram::v0($pubKeyHash);
        $addr = new SegwitAddress($wp);

        $value = BlocktrailSDK::toSatoshi(1.0);

        $builder = (new TransactionBuilder($wallet->getAddressReader()))
            ->addRecipient($addr->getAddress(), $value)
            ->setFeeStrategy(Wallet::FEE_STRATEGY_OPTIMAL)
            ->setChangeAddress("2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa");

        $this->shouldCoinSelect($client, $identifier, $value, $wp->getScript()->getHex(), [
            [
                'scriptpubkey_hex' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'path' => 'M/9999\'/0/0',
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ],
        ]);

        $builder = $wallet->coinSelectionForTxBuilder($builder);
        $this->assertTrue(count($builder->getUtxos()) > 0);
        $this->assertTrue(count($builder->getOutputs()) <= 2);

        /** @var TransactionInterface $tx */
        /** @var SignInfo $signInfo */
        list ($tx, $signInfo) = $wallet->buildTx($builder);
        $this->assertEquals("0100000001ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e0000000000ffffffff0200e1f505000000001600145d6f02f47dc6c57093df246e3742cfe1e22ab41078daa4350000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698700000000",
            $tx->getHex());
    }

    public function testOpreturn() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $addr = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $value = BlocktrailSDK::toSatoshi(1.0);

        $moon = "MOOOOOOOOOOOOON!";
        $builder = new TransactionBuilder($wallet->getAddressReader());
        $builder->randomizeChangeOutput(false);
        $builder->addRecipient($addr, $value);
        $builder->addOpReturn($moon);
        $builder->setChangeAddress("2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa");

        $this->shouldCoinSelect($client, $identifier, [
            ['value' => $value, 'scriptPubKey' => $spk],
            ['value' => 0, 'scriptPubKey' => "6a104d4f4f4f4f4f4f4f4f4f4f4f4f4f4e21"],
        ], null, [
            [
                'scriptpubkey_hex' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'path' => 'M/9999\'/0/0',
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ],
        ]);

        $builder = $wallet->coinSelectionForTxBuilder($builder);
        $this->assertTrue(count($builder->getUtxos()) > 0);
        $this->assertTrue(count($builder->getOutputs()) <= 2);

        /** @var TransactionInterface $tx */
        /** @var SignInfo $signInfo */
        list ($tx, $signInfo) = $wallet->buildTx($builder);
        $this->assertEquals("0100000001ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e0000000000ffffffff0300e1f5050000000017a9143a0fbfa2f446af8d76ac7f174618e74486746069870000000000000000126a104d4f4f4f4f4f4f4f4f4f4f4f4f4f4e2160d9a4350000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698700000000",
            $tx->getHex());
    }

    public function testSendSegwitTx() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $addr = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $value = BlocktrailSDK::toSatoshi(1.0);
        $pay = [$addr => $value];

        $this->shouldCoinSelect($client, $identifier, $value, $spk, [
            [
                'scriptpubkey_hex' => 'a9140c84184d6d00096482c1359ce0194376ad248d2287',
                'path' => "M/9999'/2/0",
                'address' => '2MtPQLhhzFNtaY59QQ9wt28a5qyQ2t8rnvd',
                'redeem_script' => '00204679ecfef34eb556071e349442e74837d059cb42ef22c4946a2efbb0c9041e68',
                'witness_script' => '522102381a4cf140c24080523b5e63082496b514e99657d3506444b7f77c121766353021028e2ec167fbdd100fcb95e22ce4b49d4733b1f291eef6b5f5748852c96aa9522721038ba8bf95fc3449c3b745232eeca5493fd3277b801e659482cc868ca6e55ce23b53ae',
            ],
        ]);

        $partiallySignedTx = "01000000000101ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e00000000232200204679ecfef34eb556071e349442e74837d059cb42ef22c4946a2efbb0c9041e68ffffffff0200e1f5050000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698790e0a4350000000017a9143a0fbfa2f446af8d76ac7f174618e7448674606987030047304402206e4ce61afeb5cfa06a1d7dcc7840eba65d3d4f47337ba29c74e73ff9adff8b6402207ed98b1e09cda1517b885ae01f0e2edc2d5dff37ca204a0f170c45622fe919140169522102381a4cf140c24080523b5e63082496b514e99657d3506444b7f77c121766353021028e2ec167fbdd100fcb95e22ce4b49d4733b1f291eef6b5f5748852c96aa9522721038ba8bf95fc3449c3b745232eeca5493fd3277b801e659482cc868ca6e55ce23b53ae00000000";
        $baseTx = "0100000001ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e00000000232200204679ecfef34eb556071e349442e74837d059cb42ef22c4946a2efbb0c9041e68ffffffff0200e1f5050000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698790e0a4350000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698700000000";
        $coSignedTx = "ok";

        $client->shouldReceive('sendTransaction')
            ->withArgs([
                $identifier,
                ['signed_transaction' => $partiallySignedTx, 'base_transaction' => $baseTx],
                ["M/9999'/2/0"],
                true
            ])
            ->andReturn($coSignedTx)
            ->once();

        $this->assertEquals($coSignedTx, $wallet->pay($pay, $addr, false, false, Wallet::FEE_STRATEGY_OPTIMAL));
    }

    public function testSpendMixedUtxoTypes()
    {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $addr = "2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa";
        $spk = "a9143a0fbfa2f446af8d76ac7f174618e7448674606987";
        $value = BlocktrailSDK::toSatoshi(1.0);
        $pay = [$addr => $value];

        $this->shouldCoinSelect($client, $identifier, $value, $spk, [
            [
                'scriptpubkey_hex' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'path' => 'M/9999\'/0/0',
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ], [
                'scriptpubkey_hex' => 'a9140c84184d6d00096482c1359ce0194376ad248d2287',
                'path' => "M/9999'/2/0",
                'address' => '2MtPQLhhzFNtaY59QQ9wt28a5qyQ2t8rnvd',
                'redeem_script' => '00204679ecfef34eb556071e349442e74837d059cb42ef22c4946a2efbb0c9041e68',
                'witness_script' => '522102381a4cf140c24080523b5e63082496b514e99657d3506444b7f77c121766353021028e2ec167fbdd100fcb95e22ce4b49d4733b1f291eef6b5f5748852c96aa9522721038ba8bf95fc3449c3b745232eeca5493fd3277b801e659482cc868ca6e55ce23b53ae',
            ],
        ]);

        $partiallySignedTx = "01000000000102ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e00000000b40047304402204e0026b03617602050165d4f9420b5b057208338484ade85a129aa0643ce66af022026b6b0f84f03b62ba46223a38dfd771ea308069aeb1c61823010d098fe44f9bd014c695221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153aeffffffffec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e01000000232200204679ecfef34eb556071e349442e74837d059cb42ef22c4946a2efbb0c9041e68ffffffff0200e1f5050000000017a9143a0fbfa2f446af8d76ac7f174618e7448674606987f69e3f710000000017a9143a0fbfa2f446af8d76ac7f174618e7448674606987000300473044022038a11f1d423ccf708e474f4c8cbbd57982e7c24c966f1a5fb56b6696dc4f3b4c022019937754e55a622ac083ffd0f3d30f818d1481be3cebb1e94bbac859feb59bad0169522102381a4cf140c24080523b5e63082496b514e99657d3506444b7f77c121766353021028e2ec167fbdd100fcb95e22ce4b49d4733b1f291eef6b5f5748852c96aa9522721038ba8bf95fc3449c3b745232eeca5493fd3277b801e659482cc868ca6e55ce23b53ae00000000";
        $baseTx = "0100000002ec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e00000000b40047304402204e0026b03617602050165d4f9420b5b057208338484ade85a129aa0643ce66af022026b6b0f84f03b62ba46223a38dfd771ea308069aeb1c61823010d098fe44f9bd014c695221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153aeffffffffec7758e4eeacd0993baa03fec37d3b33b401cac7fd2680f79cb230d467e57e6e01000000232200204679ecfef34eb556071e349442e74837d059cb42ef22c4946a2efbb0c9041e68ffffffff0200e1f5050000000017a9143a0fbfa2f446af8d76ac7f174618e7448674606987f69e3f710000000017a9143a0fbfa2f446af8d76ac7f174618e744867460698700000000";
        $coSignedTx = "ok";

        $client->shouldReceive('sendTransaction')
            ->withArgs([
                $identifier,
                ['signed_transaction' => $partiallySignedTx, 'base_transaction' => $baseTx],
                ["M/9999'/0/0", "M/9999'/2/0"],
                true
            ])
            ->andReturn($coSignedTx)
            ->once();

        $this->assertEquals($coSignedTx, $wallet->pay($pay, $addr, false, false, Wallet::FEE_STRATEGY_OPTIMAL));

    }
}
