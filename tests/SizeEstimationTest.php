<?php

namespace Blocktrail\SDK\Tests;

use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Script\ScriptFactory;

use BitWasp\Bitcoin\Script\ScriptInfo\Multisig;
use BitWasp\Bitcoin\Script\WitnessScript;
use BitWasp\Bitcoin\Script\P2shScript;
use Blocktrail\SDK\SizeEstimation;
use \BitWasp\Bitcoin\Key\PrivateKeyFactory;
use Blocktrail\SDK\UTXO;
use Blocktrail\SDK\Bitcoin\BIP32Path;
use Blocktrail\SDK\Wallet;
use Mdanter\Ecc\Crypto\Key\PrivateKeyInterface;

class SizeEstimationTest extends BlocktrailTestCase
{
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
        $c = ['L1Tr4rPUi81XN1Dp48iuva5U9sWxU1eipgiAu8BhnB3xnSfGV5rd',
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
}
