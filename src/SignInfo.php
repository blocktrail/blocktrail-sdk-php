<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Script\ScriptInterface;
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
     * @var TransactionOutput
     */
    public $output;

    /**
     * SignInfo constructor.
     * @param TransactionOutput $output
     * @param string $mode
     * @param string|null $path
     * @param ScriptInterface|null $redeemScript
     * @throws BlocktrailSDKException
     */
    public function __construct(TransactionOutput $output, $mode, $path = null, ScriptInterface $redeemScript = null) {
        if ($mode === self::MODE_SIGN) {
            if (null === $path) {
                throw new BlocktrailSDKException("No path provided for {$mode} SignInfo");
            }
            if (!($redeemScript instanceof ScriptInterface)) {
                throw new BlocktrailSDKException("No redeemScript provided for {$mode} SignInfo");
            }
        }

        $this->output = $output;
        $this->mode = $mode;
        $this->path = $path;
        $this->redeemScript = $redeemScript;
    }
}
