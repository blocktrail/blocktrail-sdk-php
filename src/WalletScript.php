<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Address\AddressInterface;
use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\P2shScript;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Script\ScriptType;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\Script\WitnessScript;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\Bitcoin\BIP32Path;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

class WalletScript
{
    const DEFAULT_SHOULD_CHECK = true;

    /**
     * @var null|bool
     */
    protected static $checkScripts;

    /**
     * @var \BitWasp\Bitcoin\Address\AddressInterface|null
     */
    private $address;

    /**
     * @var ScriptInterface
     */
    private $spk;

    /**
     * @var P2shScript|null
     */
    private $redeemScript;

    /**
     * @var WitnessScript|null
     */
    private $witnessScript;

    /**
     * @var BIP32Path
     */
    private $path;

    /**
     * Disables script checking (procedure includes some hashing)
     * @param bool $setting
     */
    public static function enforceScriptChecks($setting) {
        static::$checkScripts = (bool) $setting;
    }

    /**
     * WalletScript constructor.
     * @param BIP32Path $path
     * @param ScriptInterface $spk
     * @param P2shScript|null $redeemScript
     * @param WitnessScript|null $witnessScript
     */
    public function __construct(
        BIP32Path $path,
        ScriptInterface $spk,
        P2shScript $redeemScript = null,
        WitnessScript $witnessScript = null,
        AddressInterface $address = null
    ) {
        if (static::$checkScripts || null === static::$checkScripts && self::DEFAULT_SHOULD_CHECK) {
            $this->checkScript($path[2], $spk, $redeemScript, $witnessScript);
        }

        $this->path = $path;
        $this->spk = $spk;
        $this->redeemScript = $redeemScript;
        $this->witnessScript = $witnessScript;

        if ($address) {
            if (!$address->getScriptPubKey()->equals($this->getScriptPubKey())) {
                throw new BlocktrailSDKException("Mismatch between scriptPubKey and address");
            }
            $this->address = $address;
        }
    }

    /**
     * @param OutputClassifier $classifier
     * @param ScriptInterface $script
     * @param P2shScript|null $redeemScript
     */
    protected function checkP2shScript(OutputClassifier $classifier, ScriptInterface $script, P2shScript $redeemScript = null) {
        $scriptHash = null;
        if (!($classifier->classify($script, $scriptHash) === ScriptType::P2SH)) {
            throw new \RuntimeException("scriptPubKey should be script-hash");
        }
        /** @var BufferInterface $scriptHash */
        if (null === $redeemScript) {
            throw new \RuntimeException("Missing redeemScript");
        }

        if (!$redeemScript->getScriptHash()->equals($scriptHash)) {
            throw new \RuntimeException("Invalid redeemScript for scriptPubKey");
        }
    }

    /**
     * @param ScriptInterface $script
     * @param WitnessScript|null $witnessScript
     */
    protected function checkWitnessScript(ScriptInterface $script, WitnessScript $witnessScript = null) {
        if (!$script->isWitness($wp)) {
            $errSuffix = $script instanceof P2shScript ? "P2SH script" : "Script";
            throw new \RuntimeException("$errSuffix should be v0_scripthash");
        }

        /** @var WitnessProgram $wp */
        if ($wp->getProgram()->getSize() !== 32) {
            $errSuffix = $script instanceof P2shScript ? "P2SH script" : "Script";
            throw new \RuntimeException("$errSuffix should be v0_scripthash");
        }

        if (null === $witnessScript) {
            $errSuffix = $script instanceof P2shScript ? "P2SH script" : "script";
            throw new \RuntimeException("Missing witnessScript for $errSuffix");
        }

        if (!$wp->getProgram()->equals($witnessScript->getWitnessScriptHash())) {
            throw new \RuntimeException("Invalid witnessScript for p2sh v0_scripthash");
        }
    }

    /**
     * @param int $scriptId
     * @param ScriptInterface $script
     * @param P2shScript|null $redeemScript
     * @param WitnessScript|null $witnessScript
     */
    protected function checkScript($scriptId, ScriptInterface $script, P2shScript $redeemScript = null, WitnessScript $witnessScript = null) {
        $classifier = new OutputClassifier();
        switch ($scriptId) {
            case 2:
                $this->checkP2shScript($classifier, $script, $redeemScript);
                $this->checkWitnessScript($redeemScript, $witnessScript);
                if (!$classifier->isMultisig($witnessScript)) {
                    throw new \RuntimeException("Expected multisig as witnessScript");
                }
                break;
            default:
                $this->checkP2shScript($classifier, $script, $redeemScript);
                if (!$classifier->isMultisig($redeemScript)) {
                    throw new \RuntimeException("Expected multisig as redeemScript");
                }
                break;
        }
    }

    /**
     * @return ScriptInterface
     */
    public function getScriptPubKey() {
        return $this->spk;
    }

    /**
     * @return bool
     */
    public function isP2SH() {
        return $this->redeemScript instanceof P2shScript;
    }

    /**
     * @return bool
     */
    public function isP2WSH() {
        return $this->witnessScript instanceof WitnessScript;
    }

    /**
     * @param bool $allowNull
     * @return WitnessScript|null
     */
    public function getWitnessScript($allowNull = false) {
        if (!$allowNull && null === $this->witnessScript) {
            throw new \RuntimeException("WitnessScript not set");
        }
        return $this->witnessScript;
    }

    /**
     * @return P2shScript|null
     */
    public function getRedeemScript() {
        return $this->redeemScript;
    }

    /**
     * @return AddressInterface|null
     */
    public function getAddress() {
        if (null === $this->address) {
            throw new \RuntimeException("This script has no address");
        }
        return $this->address;
    }
}
