<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;

class SignInfo {

    public $path;

    /**
     * @var ScriptInterface
     */
    public $redeemScript;

    /**
     * @var TransactionOutput
     */
    public $output;

    public $signMode;


    public function __construct($path, ScriptInterface $redeemScript, TransactionOutput $output, $signMode) {
        $this->path = $path;
        $this->redeemScript = $redeemScript;
        $this->output = $output;
        $this->signMode = $signMode;
    }
}
