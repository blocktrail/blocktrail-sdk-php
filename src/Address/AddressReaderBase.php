<?php

namespace Blocktrail\SDK\Address;

use BitWasp\Bitcoin\Address\AddressInterface;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Base58;
use BitWasp\Bitcoin\Network\NetworkInterface;
use BitWasp\Bitcoin\Script\ScriptInterface;

abstract class AddressReaderBase
{
    /**
     * @param string $strAddress
     * @param NetworkInterface $network
     * @return PayToPubKeyHashAddress|ScriptHashAddress|null
     */
    protected function readBase58($strAddress, NetworkInterface $network) {
        try {
            $data = Base58::decodeCheck($strAddress);
            $prefixByte = $data->slice(0, 1)->getHex();

            if ($prefixByte === $network->getP2shByte()) {
                return new ScriptHashAddress($data->slice(1));
            } else if ($prefixByte === $network->getAddressByte()) {
                return new PayToPubKeyHashAddress($data->slice(1));
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    /**
     * @param $strAddress
     * @param NetworkInterface|null $network
     * @return AddressInterface
     */
    abstract public function fromString($strAddress, NetworkInterface $network = null);

    /**
     * @param ScriptInterface $script
     * @return AddressInterface
     */
    abstract public function fromOutputScript(ScriptInterface $script);
}
