<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Script\ScriptType;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

class SignInfo {

    const MODE_DONTSIGN = 'dont_sign';
    const MODE_SIGN = 'sign';

    /**
     * @var string
     */
    public $mode;

    /**
     * @var string
     */
    public $path;

    /**
     * @var ScriptInterface
     */
    public $redeemScript;

    /**
     * @var ScriptInterface
     */
    public $witnessScript;

    /**
     * @var TransactionOutput
     */
    public $output;

    /**
     * SignInfo constructor.
     * @param TransactionOutput $output
     * @param string $mode
     * @param string|null $path
     * @param ScriptInterface|null $redeemScript
     * @param ScriptInterface|null $witnessScript
     * @throws BlocktrailSDKException
     */
    public function __construct(TransactionOutput $output, $mode, $path = null, ScriptInterface $redeemScript = null, ScriptInterface $witnessScript = null) {
        if ($mode === self::MODE_SIGN) {
            if (null === $path) {
                throw new BlocktrailSDKException("No path provided for {$mode} SignInfo");
            }
            if (!($redeemScript instanceof ScriptInterface)) {
                throw new BlocktrailSDKException("No redeemScript provided for {$mode} SignInfo");
            }
            $programHash = null;

            if ((new OutputClassifier())->classify($redeemScript, $programHash) === ScriptType::P2WSH) {
                if (!$witnessScript) {
                    throw new BlocktrailSDKException("No witnessScript provided for {$mode} SignInfo");
                }
                if (!$witnessScript->getWitnessScriptHash()->equals($programHash)) {
                    throw new BlocktrailSDKException("Invalid witnessScript provided for redeemScript");
                }
            }
        }

        $this->output = $output;
        $this->mode = $mode;
        $this->path = $path;
        $this->redeemScript = $redeemScript;
        $this->witnessScript = $witnessScript;
    }
}
