<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PublicKeyInterface;
use \BitWasp\Bitcoin\Script\ScriptInfo\Multisig;
use \BitWasp\Bitcoin\Script\ScriptInfo\PayToPubKey;
use \BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\ScriptInterface;

class SizeEstimation
{
    const SIZE_DER_SIGNATURE = 72;
    const SIZE_V0_P2WSH = 36;

    /**
     * @param bool $compressed
     * @return int
     */
    public static function getPublicKeySize($compressed = true)
    {
        return $compressed ? PublicKeyInterface::LENGTH_COMPRESSED : PublicKeyInterface::LENGTH_UNCOMPRESSED;
    }

    /**
     * @param int $length
     * @return int
     */
    public static function getLengthOfScriptLengthElement($length)
    {
        if ($length < 75) {
            return 1;
        } else if ($length <= 0xff) {
            return 2;
        } else if ($length <= 0xffff) {
            return 3;
        } else if ($length <= 0xffffffff) {
            return 5;
        } else {
            throw new \RuntimeException('Size of pushdata too large');
        }
    }

    /**
     * @param int $length
     * @return int
     */
    public static function getLengthOfVarInt($length)
    {
        if ($length < 253) {
            return 1;
        } else if ($length < 65535) {
            $num_bytes = 2;
        } else if ($length < 4294967295) {
            $num_bytes = 4;
        } else if ($length < 18446744073709551615) {
            $num_bytes = 8;
        } else {
            throw new \InvalidArgumentException("Invalid decimal");
        }
        return 1 + $num_bytes;
    }

    /**
     * @param Multisig $multisig
     * @return array - first is array of stack sizes, second is script len
     */
    public static function estimateMultisigStackSize(Multisig $multisig)
    {
        $stackSizes = [0];
        for ($i = 0; $i < $multisig->getRequiredSigCount(); $i++) {
            $stackSizes[] = self::SIZE_DER_SIGNATURE;
        }

        $scriptSize = 1; // OP_$m
        $keys = $multisig->getKeyBuffers();
        for ($i = 0, $n = $multisig->getKeyCount(); $i < $n; $i++) {
            $scriptSize += 1 + $keys[$i]->getSize();
        }

        $scriptSize += 1 + 1; // OP_$n OP_CHECKMULTISIG
        return [$stackSizes, $scriptSize];
    }

    /**
     * @param PayToPubKey $info
     * @return array - first is array of stack sizes, second is script len
     */
    public static function estimateP2PKStackSize(PayToPubKey $info)
    {
        $stackSizes = [self::SIZE_DER_SIGNATURE];

        $scriptSize = 1 + $info->getKeyBuffer()->getSize(); // PUSHDATA[<75] PUBLICKEY
        $scriptSize += 1; // OP_CHECKSIG
        return [$stackSizes, $scriptSize];
    }

    /**
     * @param bool $isCompressed
     * @return array - first is array of stack sizes, second is script len
     */
    public static function estimateP2PKHStackSize($isCompressed = true)
    {
        $pubKeySize = self::getPublicKeySize($isCompressed);
        $stackSizes = [self::SIZE_DER_SIGNATURE, $pubKeySize];

        $scriptSize = 1 + 1 + 1 + 20 + 1 + 1;
        return [$stackSizes, $scriptSize];
    }

    /**
     * @param array $stackSizes - array of integer size of a value (for scriptSig or witness)
     * @param bool $isWitness
     * @param ScriptInterface $redeemScript
     * @param ScriptInterface $witnessScript
     * @return array
     */
    public static function estimateSizeForStack(array $stackSizes, $isWitness, ScriptInterface $redeemScript = null, ScriptInterface $witnessScript = null)
    {
        assert(($witnessScript === null) || $isWitness);

        if ($isWitness) {
            $scriptSigSizes = [];
            $witnessSizes = $stackSizes;
            if ($witnessScript instanceof ScriptInterface) {
                $scriptSigSizes[] = $witnessScript->getBuffer()->getSize();
            }
        } else {
            $scriptSigSizes = $stackSizes;
            $witnessSizes = [];
        }

        if ($redeemScript instanceof ScriptInterface) {
            $scriptSigSizes[] = $redeemScript->getBuffer()->getSize();
        }

        $scriptSigSize = 0;
        foreach ($scriptSigSizes as $size) {
            $len = self::getLengthOfScriptLengthElement($size);
            $scriptSigSize += $len;
            $scriptSigSize += $size;
        }

        // Make sure to count the CScriptBase length prefix..
        $scriptVarIntLen = self::getLengthOfVarInt($scriptSigSize);
        $scriptSigSize += $scriptVarIntLen;

        // make sure this is set to 0 when not used.
        // Summing WitnessSize is used in addition to $withWitness
        // because a tx without witnesses _cannot_ use witness serialization
        // total witnessSize = 0 means don't use that format
        $witnessSize = 0;
        if (count($witnessSizes) > 0) {
            // witness has a prefix indicating `n` elements in vector
            $witnessSize = self::getLengthOfVarInt(count($witnessSizes));
            foreach ($witnessSizes as $size) {
                $witnessSize += self::getLengthOfScriptLengthElement($size);
                $witnessSize += $size;
            }
        }

        return [$scriptSigSize, $witnessSize];
    }

    /**
     * @param ScriptInterface $script - not the scriptPubKey, might be SPK,RS,WS
     * @param ScriptInterface|null $redeemScript
     * @param ScriptInterface $witnessScript
     * @return array
     */
    public static function estimateInputSize(ScriptInterface $script, ScriptInterface $redeemScript = null, ScriptInterface $witnessScript = null, $isWitness)
    {
        $classifier = new OutputClassifier();
        if ($classifier->isMultisig($script)) {
            list ($stackSizes, ) = SizeEstimation::estimateMultisigStackSize(new Multisig($script));
        } else if ($classifier->isPayToPublicKey($script)) {
            list ($stackSizes, ) = SizeEstimation::estimateP2PKStackSize(new PayToPubKey($script));
        } else if ($classifier->isPayToPublicKeyHash($script)) {
            // defaults to compressed, tough luck
            list ($stackSizes, ) = SizeEstimation::estimateP2PKHStackSize();
        } else {
            throw new \InvalidArgumentException("Unsupported script type");
        }

        return self::estimateSizeForStack($stackSizes, $isWitness, $redeemScript, $witnessScript);
    }
}
