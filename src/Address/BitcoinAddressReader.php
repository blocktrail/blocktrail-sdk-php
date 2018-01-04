<?php

namespace Blocktrail\SDK\Address;

use BitWasp\Bitcoin\Address\AddressInterface;
use BitWasp\Bitcoin\Address\Base58AddressInterface;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Network\NetworkInterface;
use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Script\ScriptType;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\SegwitBech32;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

class BitcoinAddressReader extends AddressReaderBase
{

    /**
     * @param string $strAddress
     * @param NetworkInterface $network
     * @return SegwitAddress|null
     */
    protected function readSegwitAddress($strAddress, NetworkInterface $network) {
        try {
            return new SegwitAddress(SegwitBech32::decode($strAddress, $network));
        } catch (\Exception $e) {
            // continue on
        }

        return null;
    }

    /**
     * @param ScriptInterface $outputScript
     * @return AddressInterface|PayToPubKeyHashAddress|ScriptHashAddress|SegwitAddress
     */
    public function fromOutputScript(ScriptInterface $outputScript) {
        $wp = null;
        if ($outputScript->isWitness($wp)) {
            /** @var WitnessProgram $wp */
            return new SegwitAddress($wp);
        }

        $decode = (new OutputClassifier())->decode($outputScript);
        switch ($decode->getType()) {
            case ScriptType::P2PKH:
                /** @var BufferInterface $solution */
                return new PayToPubKeyHashAddress($decode->getSolution());
            case ScriptType::P2SH:
                /** @var BufferInterface $solution */
                return new ScriptHashAddress($decode->getSolution());
            default:
                throw new \RuntimeException('Script type is not associated with an address');
        }
    }

    /**
     * @param string $strAddress
     * @param NetworkInterface|null $network
     * @return Base58AddressInterface|SegwitAddress|null
     * @throws BlocktrailSDKException
     */
    public function fromString($strAddress, NetworkInterface $network = null) {
        $network = $network ?: Bitcoin::getNetwork();

        if (($base58Address = $this->readBase58($strAddress, $network))) {
            return $base58Address;
        }

        if (($bech32Address = $this->readSegwitAddress($strAddress, $network))) {
            return $bech32Address;
        }

        throw new BlocktrailSDKException("Address not recognized");
    }
}
