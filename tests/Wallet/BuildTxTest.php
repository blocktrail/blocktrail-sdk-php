<?php

namespace Blocktrail\SDK\Tests\Wallet;

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\SignInfo;
use Blocktrail\SDK\TransactionBuilder;
use Blocktrail\SDK\Wallet;
use Mockery\Mock;

class BuildTxTest extends WalletTestBase {

    public function testSegwitBuildTx() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $txid = "cafdeffb255ed7f8175f2bffc745e2dcc0ab0fa9abf9dad70a543c307614d374";
        $vout = 0;
        $address = "2MtLjsE6SyBoxXt3Xae2wTU8sPdN8JUkUZc";
        $value = 9900000;
        $outValue = 9899999;
        $expectfee = $value-$outValue;
        $scriptPubKey = "a9140c03259201742cb7476f10f70b2cf75fbfb8ab4087";
        $redeemScript = "0020cc7f3e23ec2a4cbba32d7e8f2e1aaabac38b88623d09f41dc2ee694fd33c6b14";
        $witnessScript = "5221021b3657937c54c616cbb519b447b4e50301c40759282901e04d81b5221cfcce992102381a4cf140c24080523b5e63082496b514e99657d3506444b7f77c1217663530210317e37c952644cf08b356671b4bb0308bd2468f548b31a308e8bacb682d55747253ae";

        $path = "M/9999'/2/0";

        $utxos = [
            $txid => $value,
        ];

        /** @var Transaction $tx */
        /** @var SignInfo[] $signInfo */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder($wallet->getAddressReader()))
                ->spendOutput(
                    $txid,
                    $vout,
                    $value,
                    $address,
                    $scriptPubKey,
                    $path,
                    $redeemScript,
                    $witnessScript
                )
                ->addRecipient("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", $outValue)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals($value, $inputTotal);
        $this->assertEquals($outValue, $outputTotal);
        $this->assertEquals($expectfee, $fee);

        // assert the input(s)
        $this->assertEquals(1, count($tx->getInputs()));
        $this->assertEquals($txid, $tx->getInput(0)->getOutPoint()->getTxId()->getHex());
        $this->assertEquals(0, $tx->getInput(0)->getOutPoint()->getVout());
        $this->assertEquals($address, AddressFactory::fromOutputScript($signInfo[0]->output->getScript())->getAddress());
        $this->assertEquals($scriptPubKey, $signInfo[0]->output->getScript()->getHex());
        $this->assertEquals($value, $signInfo[0]->output->getValue());
        $this->assertEquals($path, $signInfo[0]->path);
        $this->assertEquals(
            $redeemScript,
            $signInfo[0]->redeemScript->getHex()
        );
        $this->assertEquals(
            $witnessScript,
            $signInfo[0]->witnessScript->getHex()
        );

        // assert the output(s)
        $this->assertEquals(1, count($tx->getOutputs()));
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(0)->getScript())->getAddress());
        $this->assertEquals($outValue, $tx->getOutput(0)->getValue());
    }

    public function testBuildTx1() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        /*
         * test simple (real world TX) scenario
         */
        $utxos = [
            '0d8703ab259b03a757e37f3cdba7fc4543e8d47f7cc3556e46c0aeef6f5e832b' => BlocktrailSDK::toSatoshi(0.0001),
            'be837cd8f04911f3ee10d010823a26665980f7bb6c9ed307d798cb968ca00128' => BlocktrailSDK::toSatoshi(0.001),
        ];

        /** @var Transaction $tx */
        /** @var SignInfo[] $signInfo */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder($wallet->getAddressReader()))
                ->randomizeChangeOutput(false)
                ->spendOutput(
                    "0d8703ab259b03a757e37f3cdba7fc4543e8d47f7cc3556e46c0aeef6f5e832b",
                    0,
                    BlocktrailSDK::toSatoshi(0.0001),
                    "2N4pMW5nyKG7Ni5N4CiUijEosxM9kS3eVQJ",
                    "a9147eed61eeecc72e4aaac9d9ff75d8c7171beb03a987",
                    "M/9999'/0/5",
                    "52210216a925b43b7f5f0ddcb2d68fa07ab19bfdb3af1eba7190f64b2d18c4a0f11d2a210216d3dbf7f135bed8fb0748798e6253c5ef748959dd317cbddea2cfec514d332121032923eb97175038268cd320ffbb74bbef5a97ad58717026564431b5a131d47a3753ae"
                )
                ->spendOutput(
                    "be837cd8f04911f3ee10d010823a26665980f7bb6c9ed307d798cb968ca00128",
                    0,
                    BlocktrailSDK::toSatoshi(0.001),
                    "2N3zY6LL4WxdVAbT11Tuf1QwX4ACD6FKFkH",
                    "a91475e241c516ed913b5b62c46cd95dffea0b4fc0fc87",
                    "M/9999'/0/12",
                    "5221020b9e77826a4dc47d681dbe15d5e7bc41746f1fcd142e955a4a56c144e1a3d3d52103628501430353863e2c3986372c251a562709e60238f129e494faf44aedf500dd2103f66d9bea4c46cbde0a3f0efddb2c5dc52ed5b2cd2c59cd11a35560ec9319081253ae"
                )
                ->addRecipient("2N7C5Jn1LasbEK9mvHetBYXaDnQACXkarJe", BlocktrailSDK::toSatoshi(0.001))
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0011), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.001), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);

        // assert the input(s)
        $this->assertEquals(2, count($tx->getInputs()));
        $this->assertEquals("0d8703ab259b03a757e37f3cdba7fc4543e8d47f7cc3556e46c0aeef6f5e832b", $tx->getInput(0)->getOutPoint()->getTxId()->getHex());
        $this->assertEquals(0, $tx->getInput(0)->getOutPoint()->getVout());
        $this->assertEquals(10000, $signInfo[0]->output->getValue());
        $this->assertEquals("M/9999'/0/5", $signInfo[0]->path);
        $this->assertEquals(
            "52210216a925b43b7f5f0ddcb2d68fa07ab19bfdb3af1eba7190f64b2d18c4a0f11d2a210216d3dbf7f135bed8fb0748798e6253c5ef748959dd317cbddea2cfec514d332121032923eb97175038268cd320ffbb74bbef5a97ad58717026564431b5a131d47a3753ae",
            $signInfo[0]->redeemScript->getHex()
        );

        $this->assertEquals("be837cd8f04911f3ee10d010823a26665980f7bb6c9ed307d798cb968ca00128", $tx->getInput(1)->getOutPoint()->getTxId()->getHex());
        $this->assertEquals(0, $tx->getInput(1)->getOutPoint()->getVout());
        $this->assertEquals(100000, $signInfo[1]->output->getValue());
        $this->assertEquals("M/9999'/0/12", $signInfo[1]->path);
        $this->assertEquals(
            "5221020b9e77826a4dc47d681dbe15d5e7bc41746f1fcd142e955a4a56c144e1a3d3d52103628501430353863e2c3986372c251a562709e60238f129e494faf44aedf500dd2103f66d9bea4c46cbde0a3f0efddb2c5dc52ed5b2cd2c59cd11a35560ec9319081253ae",
            $signInfo[1]->redeemScript->getHex()
        );

        // assert the output(s)
        $this->assertEquals(1, count($tx->getOutputs()));
        $this->assertEquals("2N7C5Jn1LasbEK9mvHetBYXaDnQACXkarJe", AddressFactory::fromOutputScript($tx->getOutput(0)->getScript())->getAddress());
        $this->assertEquals(100000, $tx->getOutput(0)->getValue());


    }

    public function testBuildTx2() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);
        /*
         * test trying to spend too much
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.0001)
        ];
        $e = null;
        try {
            /** @var Transaction $tx */
            list($tx, $signInfo) = $wallet->buildTx(
                (new TransactionBuilder($wallet->getAddressReader()))
                    ->spendOutput(
                        "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                        0,
                        BlocktrailSDK::toSatoshi(0.0001),
                        "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                        "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                        "M/9999'/0/1537",
                        "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                    )
                    ->addRecipient("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", BlocktrailSDK::toSatoshi(0.0002))
                    ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
            );
        } catch (\Exception $e) {
        }
        $this->assertTrue(!!$e);
        $this->assertEquals("Atempting to spend more than sum of UTXOs", $e->getMessage());
    }


    public function testBuildTx3() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        /*
         * test change
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(1)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder($wallet->getAddressReader()))
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(1),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NE2uSqCktMXfe512kTPrKPhQck7vMNvaGK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(1), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9999), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);
        $this->assertEquals(14, count($tx->getOutputs()));
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(13)->getScript())->getAddress());
        $this->assertEquals(99860000, $tx->getOutput(13)->getValue());
    }

    public function testBuildTx4() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        /*
         * 1 input (1 * 294b) = 294b
         * 19 recipients (19 * 34b) = 646b
         *
         * size = 8b + 294b + 646b = 948b
         * + change output (34b) = 982b
         *
         * fee = 0.0001
         *
         * 1 - (19 * 0.0001) = 0.9981
         * change = 0.9980
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(1)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder($wallet->getAddressReader()))
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(1),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mw34qYu3rCmFkzZeNsDJ9aQri8HjmUZ6wY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MsTtsupHuqy6JvWUscn5HQ54EscLiXaPSF", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtR3Qa9eeYEpBmw3kLNywWGVmUuGjwRGXk", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N6GmkegBNA1D8wbHMLZFwxMPoNRjVnZgvv", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NBCPVQ6xX3KAVPKmGENH1eHhPwJzmN1Bpf", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NAHY321fSVz4wKnE4eWjyLfRmoauCrQpBD", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N2anz2GmZdrKNNeEZD7Xym8djepwnTqPXY", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(1), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9999), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);
        $this->assertEquals(20, count($tx->getOutputs()));
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($tx->getOutput(19)->getScript())->getAddress());
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9980), $tx->getOutput(19)->getValue());
    }

    public function testBuildTx5() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        /*
         * test change output bumps size over 1kb, fee += 0.0001
         *
         * 1 input (1 * 298b) = 298b
         * 20 recipients (19 * 33b) = 693b
         *
         * size = 8b + 298b + 693b = 999b
         * + change output (34b) = 1019b
         *
         * fee = 0.0002
         * input = 1.0000
         * 1.0000 - (20 * 0.0001) = 0.9977
         * change = 0.9977
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(1)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder($wallet->getAddressReader()))
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(1),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mw34qYu3rCmFkzZeNsDJ9aQri8HjmUZ6wY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MsTtsupHuqy6JvWUscn5HQ54EscLiXaPSF", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtR3Qa9eeYEpBmw3kLNywWGVmUuGjwRGXk", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N6GmkegBNA1D8wbHMLZFwxMPoNRjVnZgvv", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NBCPVQ6xX3KAVPKmGENH1eHhPwJzmN1Bpf", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NAHY321fSVz4wKnE4eWjyLfRmoauCrQpBD", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N2anz2GmZdrKNNeEZD7Xym8djepwnTqPXY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mvs5ik3nC9RBho2kPcgi5Q62xxAE2Aryse", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mvs5ik3nC9RBho2kPcgi5Q62xxAE2Aryse", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(1), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9998), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0002), $fee);
        $this->assertEquals(22, count($tx->getOutputs()));
        $change = $tx->getOutput(21);
        $this->assertEquals("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT", AddressFactory::fromOutputScript($change->getScript())->getAddress());
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.9977), $change->getValue());
    }

    public function testBuildTx6() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);
        /*
         * test change
         *
         * 1 input (1 * 294b) = 294b
         * 20 recipients (19 * 34b) = 680b
         *
         * size = 8b + 294b + 680b = 982b
         * + change output (34b) = 1006b
         *
         * fee = 0.0001
         * input = 0.0021
         * 0.0021 - (20 * 0.0001) = 0.0001
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.0021)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder($wallet->getAddressReader()))
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(0.0021),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mw34qYu3rCmFkzZeNsDJ9aQri8HjmUZ6wY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MsTtsupHuqy6JvWUscn5HQ54EscLiXaPSF", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtR3Qa9eeYEpBmw3kLNywWGVmUuGjwRGXk", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N6GmkegBNA1D8wbHMLZFwxMPoNRjVnZgvv", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NBCPVQ6xX3KAVPKmGENH1eHhPwJzmN1Bpf", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NAHY321fSVz4wKnE4eWjyLfRmoauCrQpBD", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N2anz2GmZdrKNNeEZD7Xym8djepwnTqPXY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mvs5ik3nC9RBho2kPcgi5Q62xxAE2Aryse", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0021), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0020), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);
        $this->assertEquals(20, count($tx->getOutputs()));
    }

    public function testBuildTx7() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);
        /*
         * test change output bumps size over 1kb, fee += 0.0001
         *  but change was < 0.0001 so better to just fee it all
         *
         * 1 input (1 * 294b) = 298b
         * 20 recipients (20 * 34b) = 660b
         *
         * input = 0.00212
         *
         * size = 8b + 298b + 660b = 986b
         * fee = 0.0001
         * 0.00212 - (20 * 0.0001) = 0.00012
         *
         * + change output (0.00009) (34b) = 1006b
         * fee = 0.0002
         *
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.00212)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder($wallet->getAddressReader()))
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(0.00212),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NFK27bVrNfDHSrcykALm29DTi85TLuNm1A", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3y477rv4TwAwW1t8rDxGQzrWcSqkzheNr", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N3SEVZ8cpT8zm6yiQphgxCL3wLfQ75f7wK", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MyCfumM2MwnCfMAqLFQeRzaovetvpV63Pt", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7ZNbgb6kEPuok2L8KwAEGnHq7Y8k6XR8B", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtamUPcc8U12sUQ2zhgoXg34c31XRd9h2E", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N1jJJxEHnQfdKMh2wor7HQ9aHp6KfpeSgw", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mx5ZJEdJus7TekzM8Jr9H2xaKH1iy4775y", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MzzvxQNZtE4NP5U2HGgLhFzsRQJaRQouKY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N8inYmbUT9wM1ewvtEy6RW4zBQstgPkfCQ", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N7TZBUcr7dTJaDFPN6aWtWmRsh5MErv4nu", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mw34qYu3rCmFkzZeNsDJ9aQri8HjmUZ6wY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MsTtsupHuqy6JvWUscn5HQ54EscLiXaPSF", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2MtR3Qa9eeYEpBmw3kLNywWGVmUuGjwRGXk", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N6GmkegBNA1D8wbHMLZFwxMPoNRjVnZgvv", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NBCPVQ6xX3KAVPKmGENH1eHhPwJzmN1Bpf", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2NAHY321fSVz4wKnE4eWjyLfRmoauCrQpBD", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2N2anz2GmZdrKNNeEZD7Xym8djepwnTqPXY", BlocktrailSDK::toSatoshi(0.0001))
                ->addRecipient("2Mvs5ik3nC9RBho2kPcgi5Q62xxAE2Aryse", BlocktrailSDK::toSatoshi(0.0001))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.00212), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0020), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.00012), $fee);
        $this->assertEquals(20, count($tx->getOutputs()));
    }

    public function testBuildTx8() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        /*
         * custom fee
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.002001)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder($wallet->getAddressReader()))
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(0.002001),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.002))
                ->setFee(BlocktrailSDK::toSatoshi(0.000001))
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.002001), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.002), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.000001), $fee);

        /*
         * multiple outputs same address
         */
        $utxos = [
            'ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396' => BlocktrailSDK::toSatoshi(0.002)
        ];
        /** @var Transaction $tx */
        list($tx, $signInfo) = $wallet->buildTx(
            (new TransactionBuilder($wallet->getAddressReader()))
                ->spendOutput(
                    "ed6458f2567c3a6847e96ca5244c8eb097efaf19fd8da2d25ec33d54a49b4396",
                    0,
                    BlocktrailSDK::toSatoshi(0.002),
                    "2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT",
                    "a9148e3c73aaf758dc4f4186cd49c3d523954992a46a87",
                    "M/9999'/0/1537",
                    "5221025a341fad401c73eaa1ee40ba850cc7368c41f7a29b3c6e1bbb537be51b398c4d210331801794a117dac34b72d61262aa0fcec7990d72a82ddde674cf583b4c6a5cdf21033247488e521170da034e4d8d0251530df0e0d807419792492af3e54f6226441053ae"
                )
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0005))
                ->addRecipient("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", BlocktrailSDK::toSatoshi(0.0005))
                ->setChangeAddress("2N6DJMnoS3xaxpCSDRMULgneCghA1dKJBmT")
                ->randomizeChangeOutput(false)
                ->setFeeStrategy(Wallet::FEE_STRATEGY_BASE_FEE)
        );

        $inputTotal = array_sum(array_map(function (TransactionInput $txin) use ($utxos) {
            return $utxos[$txin->getOutPoint()->getTxId()->getHex()];
        }, $tx->getInputs()));
        $outputTotal = array_sum(array_map(function (TransactionOutput $txout) {
            return $txout->getValue();
        }, $tx->getOutputs()));

        $fee = $inputTotal - $outputTotal;

        // assert the output(s)
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.002), $inputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0019), $outputTotal);
        $this->assertEquals(BlocktrailSDK::toSatoshi(0.0001), $fee);

        $this->assertEquals("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", AddressFactory::fromOutputScript($tx->getOutput(0)->getScript())->getAddress());
        $this->assertEquals("2NAUFsSps9S2mEnhaWZoaufwyuCaVPUv8op", AddressFactory::fromOutputScript($tx->getOutput(1)->getScript())->getAddress());
    }
}
