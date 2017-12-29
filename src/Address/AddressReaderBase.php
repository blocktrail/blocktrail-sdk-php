<?php

namespace Blocktrail\SDK\Address;

use BitWasp\Bitcoin\Address\AddressInterface;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Base58;
use BitWasp\Bitcoin\Network\NetworkInterface;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\SegwitBech32;
use Blocktrail\SDK\Network\BitcoinCashNetworkInterface;

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
     * @param string $strAddress
     * @param NetworkInterface $network
     * @return SegwitAddress|null
     */
    protected function readBech32($strAddress, NetworkInterface $network) {
        try {
            return new SegwitAddress(SegwitBech32::decode($strAddress, $network));
        } catch (\Exception $e) {
            // continue on
        }

        return null;
    }

    /**
     * @param string $strAddress
     * @param BitcoinCashNetworkInterface $network
     * @return CashAddress|null
     */
    protected function readBase32($strAddress, BitcoinCashNetworkInterface $network) {
        try {
            list ($prefix, $scriptType, $hash) = \CashAddr\CashAddress::decode($strAddress);
            if ($prefix !== $network->getCashAddressPrefix()) {
                return null;
            }
            return new CashAddress($scriptType, $hash);
        } catch (\Exception $e) {
            // continue on
        }

        return null;
    }

    /**
     * @param string $strAddress
     * @return AddressInterface
     */
    abstract public function fromString($strAddress);

    /**
     * @param ScriptInterface $script
     * @return AddressInterface
     */
    abstract public function fromOutputScript(ScriptInterface $script);
}
