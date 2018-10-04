<?php

namespace Blocktrail\SDK\Tests;

use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Script\ScriptFactory;

use BitWasp\Bitcoin\Script\ScriptInfo\Multisig;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\Script\WitnessScript;
use BitWasp\Bitcoin\Script\P2shScript;
use BitWasp\Bitcoin\Transaction\Factory\SignData;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use Blocktrail\SDK\SizeEstimation;
use \BitWasp\Bitcoin\Key\PrivateKeyFactory;
use Blocktrail\SDK\TransactionBuilder;
use Blocktrail\SDK\UTXO;
use Blocktrail\SDK\Bitcoin\BIP32Path;
use Blocktrail\SDK\Wallet;
use Mdanter\Ecc\Crypto\Key\PrivateKeyInterface;

class SizeEstimationTest extends \PHPUnit_Framework_TestCase
{
    public function setUp() {
        parent::setUp();

        Bitcoin::setNetwork(NetworkFactory::bitcoin());
    }

    /**
     * @return array
     */
    public function lengthOfVarIntProvider()
    {
        return [
            [0, 1],
            [1, 1],
            [252, 1],
            [253, 3],
            [254, 3],
            [65534, 3],
            [65535, 5],
            [65536, 5],
            [pow(2, 31), 5],
            [pow(2, 32)-2, 5],
            [pow(2, 32)-1, 9],
            [pow(2, 32), 9],

            // don't go above this, is signed int territory, PHP complains
            [0x7fffffffffffffff, 9],
        ];
    }

    /**
     * @param $integer
     * @param $expectSize
     * @dataProvider lengthOfVarIntProvider
     */
    public function testLengthOfVarInt($integer, $expectSize)
    {
        $this->assertEquals($expectSize, SizeEstimation::getLengthOfVarInt($integer));
    }

    /**
     * @return array
     */
    public function lengthOfScriptDataProvider()
    {
        return [
            [0, 1],
            [1, 1],
            [74, 1],
            [75, 2],
            [76, 2],
            [254, 2],
            [255, 2],
            [256, 3],
            [65534, 3],
            [65535, 3],
            [65536, 5],
        ];
    }

    /**
     * @param $dataLen
     * @param $expectSize
     * @dataProvider lengthOfScriptDataProvider
     */
    public function testLengthOfScriptData($dataLen, $expectSize)
    {
        $this->assertEquals($expectSize, SizeEstimation::getLengthOfScriptLengthElement($dataLen));
    }

    public function multisigProvider() {
        $u = ['5KW8Ymmu8gWManGggZZQJeX7U3pn5HtcqqsVrNUbc1SUmVPZbwp',
            '5KCV94YBsrJWTdk6cQWJxEd25sH8h1cGTpJnCN6kLMLe4c3QZVr',
            '5JUxGateMWVBsBQkAwSRQLxyaQXhsch4EStfC62cqdEf2zUheVT',
            ];
        $c = ['L1Tr4rPUi81XN1Dp48iuva5U9sWxU1eipgiAu8BhnB3xnSfGV5rd',
            'KwUZpCvpAkUe1SZj3k3P2acta1V1jY8Dpuj71bEAukEKVrg8NEym',
            'Kz2Lm2hzjPWhv3WW9Na5HUKi4qBxoTfv8fNYAU6KV6TZYVGdK5HW',
        ];

        /**
         * @var PrivateKeyInterface[] $uncompressed
         * @var PrivateKeyInterface[] $compressed
         */
        $uncompressed = array_map(function ($wif) {
            return PrivateKeyFactory::fromWif($wif, null);
        }, $u);
        $compressed = array_map(function ($wif) {
            return PrivateKeyFactory::fromWif($wif, null);
        }, $c);

        $fixtures = [];
        for ($i = 0; $i < 3; $i++) {
            $keys = [];
            for ($j = 0; $j < $i; $j++) {
                $keys[] = $uncompressed[$j]->getPublicKey();
            }
            for ($j = $i; $j < 3; $j++) {
                $keys[] = $compressed[$j]->getPublicKey();
            }

            $fixtures[] = [ScriptFactory::scriptPubKey()->multisig(2, $keys)];
        }
        return $fixtures;
    }

    /**
     * @param ScriptInterface $script
     * @dataProvider multisigProvider
     */
    public function testMultisigStackSizes(ScriptInterface $script) {
        $multisig = new Multisig($script);
        $result = SizeEstimation::estimateMultisigStackSize($multisig);
        $this->assertInternalType('array', $result);
        $this->assertEquals(2, count($result));

        list ($stackSizes, $scriptSize) = $result;
        $this->assertInternalType('array', $stackSizes);
        $this->assertInternalType('integer', $scriptSize);

        $this->assertEquals(1+$multisig->getRequiredSigCount(), count($stackSizes) > 0);
        $this->assertEquals(0, $stackSizes[0]);
        for ($i = 0; $i < $multisig->getRequiredSigCount(); $i++) {
            $this->assertEquals(SizeEstimation::SIZE_DER_SIGNATURE, $stackSizes[$i+1]);
        }
    }

    /**
     * @return array
     */
    public function multisigFormProvider() {
        $c = ['L1Tr4rPUi81XN1Dp48iuva5U9sWxU1eipgiAu8BhnB3xnSfGV5rd',
            'KwUZpCvpAkUe1SZj3k3P2acta1V1jY8Dpuj71bEAukEKVrg8NEym',
            'Kz2Lm2hzjPWhv3WW9Na5HUKi4qBxoTfv8fNYAU6KV6TZYVGdK5HW',
        ];

        $pubs = array_map(function ($wif) {
            return PrivateKeyFactory::fromWif($wif)->getPublicKey();
        }, $c);

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, $pubs);

        $bareSize = 1/*OP_0*/
            + 1/*push<75*/ + SizeEstimation::SIZE_DER_SIGNATURE
            + 1/*push<75*/ + SizeEstimation::SIZE_DER_SIGNATURE;
        $p2shScriptLen = 2/*push>75*/ + $multisig->getBuffer()->getSize();

        $bareSig = 1 + $bareSize;
        $pshSig = 3 + $bareSize + $p2shScriptLen;
        $p2wshScript = ScriptFactory::scriptPubKey()->p2wsh($multisig->getWitnessScriptHash());

        $nestedSig = 1 + 1 + $p2wshScript->getBuffer()->getSize();
        $witSize = 1*(1+0) + 2*(1+SizeEstimation::SIZE_DER_SIGNATURE);
        $nestedWit = $witSize + 1 + 1 + $multisig->getBuffer()->getSize();
        return [
            [$multisig, false, null, null, $bareSig, 0],
            [$multisig, false, $multisig, null, $pshSig, 0],
            [$multisig, true, null, $multisig, 1, $nestedWit],
            [$multisig, true, $p2wshScript, $multisig, $nestedSig, $nestedWit],
        ];
    }

    /**
     * @param ScriptInterface $script
     * @param bool $isWit
     * @param ScriptInterface|null $rs
     * @param ScriptInterface|null $ws
     * @param int $scriptSigSize
     * @param int $witSize
     * @dataProvider multisigFormProvider
     */
    public function testMultisigForms(ScriptInterface $script, $isWit, ScriptInterface $rs = null, ScriptInterface $ws = null, $scriptSigSize, $witSize) {
        $multisig = new Multisig($script);
        list ($stackSizes, ) = SizeEstimation::estimateMultisigStackSize($multisig);

        $est = SizeEstimation::estimateSizeForStack($stackSizes, $isWit, $rs, $ws);
        $this->assertInternalType('array', $est);
        $this->assertEquals(2, count($est));

        list ($foundSigSize, $foundWitSize) = $est;
        $this->assertEquals($scriptSigSize, $foundSigSize);
        $this->assertEquals($witSize, $foundWitSize);
    }

    /**
     * @return array
     */
    public function multisigUtxoProvider() {
        $c = ['L1Tr4rPUi81XN1Dp48iuva5U9sWxU1eipgiAu8BhnB3xnSfGV5rd',
            'KwUZpCvpAkUe1SZj3k3P2acta1V1jY8Dpuj71bEAukEKVrg8NEym',
            'Kz2Lm2hzjPWhv3WW9Na5HUKi4qBxoTfv8fNYAU6KV6TZYVGdK5HW',
        ];

        $pubs = array_map(function ($wif) {
            return PrivateKeyFactory::fromWif($wif)->getPublicKey();
        }, $c);

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, $pubs);

        $bareSize = 1/*OP_0*/
            + 1/*push<75*/ + SizeEstimation::SIZE_DER_SIGNATURE
            + 1/*push<75*/ + SizeEstimation::SIZE_DER_SIGNATURE;
        $p2shScriptLen = 2/*push>75*/ + $multisig->getBuffer()->getSize();

        $bareSig = 1 + $bareSize;
        $pshSig = 3 + $bareSize + $p2shScriptLen;
        $p2wshScript = ScriptFactory::scriptPubKey()->p2wsh($multisig->getWitnessScriptHash());

        $nestedSig = 1 + 1 + $p2wshScript->getBuffer()->getSize();
        $witSize = 1*(1+0) + 2*(1+SizeEstimation::SIZE_DER_SIGNATURE);
        $nestedWit = $witSize + 1 + 1 + $multisig->getBuffer()->getSize();

        $path = BIP32Path::path("M/9999'/0/1");

        $p2shRedeem = new P2shScript($multisig);
        $p2wshRedeem = new WitnessScript($multisig);
        $p2shp2wshRedeem = new P2shScript($p2wshRedeem);
        $bareUtxo = new UTXO(str_repeat('41', 32), 0, 100000000, null, $multisig, $path, null, null);
        $p2shUtxo = new UTXO(str_repeat('41', 32), 0, 100000000, null, $p2shRedeem->getOutputScript(), $path, $p2shRedeem, null);
        $p2wshUtxo = new UTXO(str_repeat('41', 32), 0, 100000000, null, $p2wshRedeem->getOutputScript(), $path, null, $p2wshRedeem);
        $p2shp2wshUtxo = new UTXO(str_repeat('41', 32), 0, 100000000, null, $p2shp2wshRedeem->getOutputScript(), $path, $p2shp2wshRedeem, $p2wshRedeem);

        return [
            [$bareUtxo, $bareSig, 0],
            [$p2shUtxo, $pshSig, 0],
            [$p2wshUtxo, 1, $nestedWit],
            [$p2shp2wshUtxo, $nestedSig, $nestedWit],
        ];
    }

    /**
     * @param UTXO $utxo
     * @param int $scriptSigSize
     * @param int $witSize
     * @dataProvider multisigUtxoProvider
     */
    public function testMultisigUtxoForms(UTXO $utxo, $scriptSigSize, $witSize) {
        $est = SizeEstimation::estimateUtxo($utxo);
        $this->assertInternalType('array', $est);
        $this->assertEquals(2, count($est));

        $this->assertEquals($scriptSigSize, $est['scriptSig']);
        $this->assertEquals($witSize, $est['witness']);
    }

    public function testEquivalentWithOld() {
        $c = [
            'L1Tr4rPUi81XN1Dp48iuva5U9sWxU1eipgiAu8BhnB3xnSfGV5rd',
            'KwUZpCvpAkUe1SZj3k3P2acta1V1jY8Dpuj71bEAukEKVrg8NEym',
            'Kz2Lm2hzjPWhv3WW9Na5HUKi4qBxoTfv8fNYAU6KV6TZYVGdK5HW',
        ];

        $pubs = array_map(function ($wif) {
            return PrivateKeyFactory::fromWif($wif)->getPublicKey();
        }, $c);

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, $pubs);
        $p2shRedeem = new P2shScript($multisig);

        $p2shUtxo = new UTXO(str_repeat('41', 32), 0, 100000000, null, $p2shRedeem->getOutputScript(), null, $p2shRedeem);

        $oldTxInEst = Wallet::estimateSizeUTXOs(1);
        $newScriptEst = SizeEstimation::estimateUtxo($p2shUtxo)['scriptSig'];
        $newTxInEst = 32 + 4 + 4 + $newScriptEst;

        $this->assertEquals($oldTxInEst, $newTxInEst);
    }

    public function testTxWeightP2WPKH() {
        $network = NetworkFactory::bitcoin();
        $wif = PrivateKeyFactory::fromWif("L59Kc7sjZPfKTrWCn4hvs5AUWQJXrn1hCMFkxx7YZGRhAC12k4Kr", null, $network);

        $hash160 = $wif->getPubKeyHash();
        $p2wpkh = ScriptFactory::scriptPubKey()->p2wkh($hash160);
        $value = 1234123;

        $utxo = new UTXO(
            "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
            0,
            $value,
            new SegwitAddress(WitnessProgram::v0($hash160)),
            $p2wpkh
        );

        $outputs1 = [[
            "scriptPubKey" => $p2wpkh->getHex(),
            "value" => $value,
        ]];

        $outputs2 = [[
            "scriptPubKey" => $p2wpkh,
            "value" => $value,
        ]];

        /** @var UTXO[] $utxos */
        $utxos = [$utxo];
        $builder = new TxBuilder();
        foreach ($utxos as $utxo) {
            $builder->input($utxo->hash, $utxo->index);
        }
        $builder->output($value - 6000, $p2wpkh);

        $signer = new Signer($builder->get());
        foreach ($utxos as $i => $utxo) {
            $txOut = new TransactionOutput($utxo->value, $utxo->scriptPubKey);
            $signer->input(0, $txOut)
                ->sign($wif);
        }

        $signed = $signer->get();

        $expectedWeight = 438;
        $expectedVSize = 110;

        $weight = SizeEstimation::estimateWeight($utxos, $signed->getOutputs());
        $this->assertEquals($expectedWeight, $weight, "weight (tx.outputs) should be the same");

        $weight = SizeEstimation::estimateWeight($utxos, $outputs1);
        $this->assertEquals($expectedWeight, $weight, "weight (outputs array w/ script string) should be the same");

        $weight = SizeEstimation::estimateWeight($utxos, $outputs2);
        $this->assertEquals($expectedWeight, $weight, "weight (outputs array w/ ScriptInterface) should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $signed->getOutputs());
        $this->assertEquals($expectedVSize, $vsize, "vsize should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $outputs1);
        $this->assertEquals($expectedVSize, $vsize, "vsize should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $outputs2);
        $this->assertEquals($expectedVSize, $vsize, "vsize should be the same");
    }


    public function testTxWeightP2WPKHSeveralInputs() {
        $network = NetworkFactory::bitcoin();
        $wif = PrivateKeyFactory::fromWif("L59Kc7sjZPfKTrWCn4hvs5AUWQJXrn1hCMFkxx7YZGRhAC12k4Kr", null, $network);

        $hash160 = $wif->getPubKeyHash();
        $p2wpkh = ScriptFactory::scriptPubKey()->p2wkh($hash160);

        $utxo1 = new UTXO(
            "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
            0,
            0,
            new SegwitAddress(WitnessProgram::v0($hash160)),
            $p2wpkh
        );

        $utxo2 = new UTXO(
            "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd123e",
            0,
            0,
            new SegwitAddress(WitnessProgram::v0($hash160)),
            $p2wpkh
        );
        $utxo3 = new UTXO(
            "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd123f",
            0,
            0,
            new SegwitAddress(WitnessProgram::v0($hash160)),
            $p2wpkh
        );

        $outputs1 = [[
            "scriptPubKey" => $p2wpkh->getHex(),
            "value" => 1234,
        ]];

        $outputs2 = [[
            "scriptPubKey" => $p2wpkh,
            "value" => 1234,
        ]];

        /** @var UTXO[] $utxos */
        $utxos = [$utxo1, $utxo2, $utxo3];
        $builder = new TxBuilder();
        foreach ($utxos as $utxo) {
            $builder->input($utxo->hash, $utxo->index);
        }
        $builder->output(1234, $p2wpkh);

        $signer = new Signer($builder->get());
        foreach ($utxos as $i => $utxo) {
            $txOut = new TransactionOutput($utxo->value, $utxo->scriptPubKey);
            $signer->input(0, $txOut)
                ->sign($wif);
        }

        $signed = $signer->get();

        $expectedWeight = 982;
        $expectedVSize = 246;

        $weight = SizeEstimation::estimateWeight($utxos, $signed->getOutputs());
        $this->assertEquals($expectedWeight, $weight, "weight (tx.outputs) should be the same");

        $weight = SizeEstimation::estimateWeight($utxos, $outputs1);
        $this->assertEquals($expectedWeight, $weight, "weight (outputs array w/ script string) should be the same");

        $weight = SizeEstimation::estimateWeight($utxos, $outputs2);
        $this->assertEquals($expectedWeight, $weight, "weight (outputs array w/ ScriptInterface) should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $signed->getOutputs());
        $this->assertEquals($expectedVSize, $vsize, "vsize should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $outputs1);
        $this->assertEquals($expectedVSize, $vsize, "vsize should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $outputs2);
        $this->assertEquals($expectedVSize, $vsize, "vsize should be the same");
    }

    public function testTxWeightP2SH_P2WPKH() {
        $network = NetworkFactory::bitcoin();
        $wif = PrivateKeyFactory::fromWif("L59Kc7sjZPfKTrWCn4hvs5AUWQJXrn1hCMFkxx7YZGRhAC12k4Kr", null, $network);

        $hash160 = $wif->getPubKeyHash();
        $wp = WitnessProgram::v0($hash160);
        $rs = new P2shScript($wp->getScript());
        $spk = $rs->getOutputScript();
        $dest = ScriptFactory::fromHex("0020916ff972855bf7589caf8c46a31f7f33b07d0100d953fde95a8354ac36e98165");

        $value = 1234123;

        $utxo = new UTXO(
            "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
            0,
            $value,
            $rs->getAddress(),
            $spk,
            null,
            $rs
        );

        $outputs1 = [[
            "scriptPubKey" => $dest->getHex(),
            "value" => $value,
        ]];

        $outputs2 = [[
            "scriptPubKey" => $dest,
            "value" => $value,
        ]];

        /** @var UTXO[] $utxos */
        $utxos = [$utxo];
        $builder = new TxBuilder();
        foreach ($utxos as $utxo) {
            $builder->input($utxo->hash, $utxo->index);
        }
        $builder->output($value - 6000, $dest);

        $signer = new Signer($builder->get());
        foreach ($utxos as $i => $utxo) {
            $txOut = new TransactionOutput($utxo->value, $utxo->scriptPubKey);
            $signData = (new SignData())
                ->p2sh($rs);

            $signer->input(0, $txOut, $signData)
                ->sign($wif);
        }

        $signed = $signer->get();

        $weight = SizeEstimation::estimateWeight($utxos, $signed->getOutputs());
        $this->assertEquals(578, $weight, "weight (tx.outputs) should be the same");

        $weight = SizeEstimation::estimateWeight($utxos, $outputs1);
        $this->assertEquals(578, $weight, "weight (outputs array w/ script string) should be the same");

        $weight = SizeEstimation::estimateWeight($utxos, $outputs2);
        $this->assertEquals(578, $weight, "weight (outputs array w/ ScriptInterface) should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $signed->getOutputs());
        $this->assertEquals(145, $vsize, "vsize should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $outputs1);
        $this->assertEquals(145, $vsize, "vsize should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $outputs2);
        $this->assertEquals(145, $vsize, "vsize should be the same");
    }

    public function testTxWeightMultisig1Of1() {
        $network = NetworkFactory::bitcoin();
        $wif = PrivateKeyFactory::fromWif("L58TinpdSF52WNbKx7Jnyj5UohzDWxYupeiBd4FrnuPmPvBkLUzW", null, $network);

        $multisig = ScriptFactory::scriptPubKey()->multisig(1, [$wif->getPublicKey()]);
        $ws = new WitnessScript($multisig);
        $spk = $ws->getOutputScript();
        $dest = ScriptFactory::fromHex("00145d6f02f47dc6c57093df246e3742cfe1e22ab410");

        $value = 100000;

        $utxo = new UTXO(
            "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
            0,
            $value,
            $ws->getAddress(),
            $spk,
            null,
            null,
            $ws
        );

        $outputs1 = [[
            "scriptPubKey" => $dest->getHex(),
            "value" => $value,
        ]];

        $outputs2 = [[
            "scriptPubKey" => $dest,
            "value" => $value,
        ]];

        /** @var UTXO[] $utxos */
        $utxos = [$utxo];
        $builder = new TxBuilder();
        foreach ($utxos as $utxo) {
            $builder->input($utxo->hash, $utxo->index);
        }
        $builder->output(73182, $dest);

        $signer = new Signer($builder->get());
        foreach ($utxos as $i => $utxo) {
            $txOut = new TransactionOutput($utxo->value, $utxo->scriptPubKey);
            $signData = (new SignData())
                ->p2wsh($ws);

            $signer->input(0, $txOut, $signData)
                ->sign($wif);
        }

        $signed = $signer->get();

        $expectedWeight = 443;
        $expectedVsize = 111;

        $weight = SizeEstimation::estimateWeight($utxos, $signed->getOutputs());
        $this->assertEquals($expectedWeight, $weight, "weight (tx.outputs) should be the same");

        $weight = SizeEstimation::estimateWeight($utxos, $outputs1);
        $this->assertEquals($expectedWeight, $weight, "weight (outputs array w/ script string) should be the same");

        $weight = SizeEstimation::estimateWeight($utxos, $outputs2);
        $this->assertEquals($expectedWeight, $weight, "weight (outputs array w/ ScriptInterface) should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $signed->getOutputs());
        $this->assertEquals($expectedVsize, $vsize, "vsize should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $outputs1);
        $this->assertEquals($expectedVsize, $vsize, "vsize should be the same");

        $vsize = SizeEstimation::estimateVsize($utxos, $outputs2);
        $this->assertEquals($expectedVsize, $vsize, "vsize should be the same");
    }

    public function test1P2shInput1P2shOutput()
    {

        $keys = [
            "4242424242424242424242424242424242424242424242424242424242424242",
            "44d42424242424242424242424242424242424242424242424242424242424242",
            "88d42424242424242424242424242424242424242424242424242424242424242",
        ];

        $privs = [];
        $pubs = [];
        foreach ($keys as $key) {
            $priv = PrivateKeyFactory::fromHex($key, true);
            $pubs[] = $priv->getPublicKey();
            $privs[] = $priv;
        }

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, $pubs);
        $redeemScript = new P2shScript($multisig);

        $value = 123123123;
        $path = "";
        $utxo = new UTXO(
            "1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd",
            0,
            $value,
            $redeemScript->getAddress(),
            $redeemScript->getOutputScript(),
            $path,
            $redeemScript,
            null
        );

        $outputs = [
            new TransactionOutput($value, $redeemScript->getOutputScript())
        ];

        $est = new SizeEstimation();
        $vsize = $est->estimateVSize([$utxo], $outputs);
        $weight = $est->estimateWeight([$utxo], $outputs);
        $this->assertEquals($vsize, 339);
        $this->assertEquals($weight, 1356);

    }


    public function test1P2shInput0P2shOutputs()
    {

        $keys = [
            "4242424242424242424242424242424242424242424242424242424242424242",
            "44d42424242424242424242424242424242424242424242424242424242424242",
            "88d42424242424242424242424242424242424242424242424242424242424242",
        ];

        $privs = [];
        $pubs = [];
        foreach ($keys as $key) {
            $priv = PrivateKeyFactory::fromHex($key, true);
            $pubs[] = $priv->getPublicKey();
            $privs[] = $priv;
        }

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, $pubs);
        $redeemScript = new P2shScript($multisig);

        $value = 123123123;
        $path = "";
        $utxo = new UTXO(
            "1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd",
            0,
            $value,
            $redeemScript->getAddress(),
            $redeemScript->getOutputScript(),
            $path,
            $redeemScript,
            null
        );

        $outputs = [];

        $est = new SizeEstimation();
        $vsize = $est->estimateVSize([$utxo], $outputs);
        $weight = $est->estimateWeight([$utxo], $outputs);
        $this->assertEquals($vsize, 307);
        $this->assertEquals($weight, 1228);
    }

    public function test1NestedSegwitInput1P2shOutput()
    {
        $keys = [
            "4242424242424242424242424242424242424242424242424242424242424242",
            "44d42424242424242424242424242424242424242424242424242424242424242",
            "88d42424242424242424242424242424242424242424242424242424242424242",
        ];

        $privs = [];
        $pubs = [];
        foreach ($keys as $key) {
            $priv = PrivateKeyFactory::fromHex($key, true);
            $pubs[] = $priv->getPublicKey();
            $privs[] = $priv;
        }

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, $pubs);
        $witnessScript = new WitnessScript($multisig);
        $redeemScript = new P2shScript($witnessScript);

        $value = 123123123;
        $path = "";
        $utxo = new UTXO(
            "1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd",
            0,
            $value,
            $redeemScript->getAddress(),
            $redeemScript->getOutputScript(),
            $path,
            $redeemScript,
            $witnessScript
        );

        $outputs = [
            new TransactionOutput($value, $redeemScript->getOutputScript())
        ];

        $est = new SizeEstimation();
        $vsize = $est->estimateVSize([$utxo], $outputs);

        $this->assertEquals($vsize, 182);
        $weight = $est->estimateWeight([$utxo], $outputs);
        $this->assertEquals($weight, 728);
    }


    public function test1NestedSegwitInput0Outputs()
    {
        $keys = [
            "4242424242424242424242424242424242424242424242424242424242424242",
            "44d42424242424242424242424242424242424242424242424242424242424242",
            "88d42424242424242424242424242424242424242424242424242424242424242",
        ];

        $privs = [];
        $pubs = [];
        foreach ($keys as $key) {
            $priv = PrivateKeyFactory::fromHex($key, true);
            $pubs[] = $priv->getPublicKey();
            $privs[] = $priv;
        }

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, $pubs);
        $witnessScript = new WitnessScript($multisig);
        $redeemScript = new P2shScript($witnessScript);

        $value = 123123123;
        $path = "";
        $utxo = new UTXO(
            "1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd",
            0,
            $value,
            $redeemScript->getAddress(),
            $redeemScript->getOutputScript(),
            $path,
            $redeemScript,
            $witnessScript
        );

        $outputs = [];
        $est = new SizeEstimation();
        $vsize = $est->estimateVSize([$utxo], $outputs);

        $this->assertEquals($vsize, 150);
        $weight = $est->estimateWeight([$utxo], $outputs);
        $this->assertEquals($weight, 600);
    }
}
