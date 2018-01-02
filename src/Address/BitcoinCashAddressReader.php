<?php

namespace Blocktrail\SDK\Address;

use BitWasp\Bitcoin\Address\Base58AddressInterface;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Network\NetworkInterface;
use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Script\ScriptType;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\Network\BitcoinCashNetworkInterface;

class BitcoinCashAddressReader extends AddressReaderBase
{
    /**
     * @var bool
     */
    private $useNewCashAddress;

    /**
     * BitcoinCashAddressReader constructor.
     * @param bool $useNewCashAddress
     */
    public function __construct($useNewCashAddress) {
        $this->useNewCashAddress = (bool) $useNewCashAddress;
    }

    /**
     * @param string $strAddress
     * @param NetworkInterface|null $network
     * @return Base58AddressInterface|CashAddress
     * @throws BlocktrailSDKException
     */
    public function fromString($strAddress, NetworkInterface $network = null) {
        $network = $network ?: Bitcoin::getNetwork();

        if (($base58Address = $this->readBase58($strAddress, $network))) {
            return $base58Address;
        }

        if ($this->useNewCashAddress && $network instanceof BitcoinCashNetworkInterface) {
            if (($base32Address = $this->readBase32($strAddress, $network))) {
                return $base32Address;
            }
        }

        throw new BlocktrailSDKException("Address not recognized");
    }

    /**
     * @param ScriptInterface $script
     * @return Base58AddressInterface|CashAddress
     */
    public function fromOutputScript(ScriptInterface $script) {
        $decode = (new OutputClassifier())->decode($script);

        switch ($decode->getType()) {
            case ScriptType::P2PKH:
                /** @var BufferInterface $solution */
                if ($this->useNewCashAddress) {
                    return new CashAddress(ScriptType::P2PKH, $decode->getSolution());
                } else {
                    return new PayToPubKeyHashAddress($decode->getSolution());
                }
                break;
            case ScriptType::P2SH:
                /** @var BufferInterface $solution */
                if ($this->useNewCashAddress) {
                    return new CashAddress(ScriptType::P2SH, $decode->getSolution());
                } else {
                    return new ScriptHashAddress($decode->getSolution());
                }
                break;
            default:
                throw new \RuntimeException('Script type is not associated with an address');
        }
    }
}
