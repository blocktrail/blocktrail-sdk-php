<?php

namespace Blocktrail\SDK\Network;

use BitWasp\Bitcoin\Network\Network;
use BitWasp\Bitcoin\Network\NetworkFactory;

class BitcoinCash extends Network implements BitcoinCashNetworkInterface
{
    /**
     * @var string
     */
    private $cashAddressPrefix;

    /**
     * BitcoinCash constructor.
     * @param bool $testnet
     * @throws \Exception
     */
    public function __construct(bool $testnet = false) {
        if ($testnet) {
            $base = NetworkFactory::bitcoinTestnet();
            $cashAddressPrefix = "bchtest";
        } else {
            $base = NetworkFactory::bitcoin();
            $cashAddressPrefix = "bitcoincash";
        }

        parent::__construct(
            $base->getAddressByte(),
            $base->getP2shByte(),
            $base->getPrivByte(),
            $base->isTestnet()
        );

        $this->setHDPrivByte($base->getHDPrivByte());
        $this->setHDPubByte($base->getHDPubByte());
        $this->setNetMagicBytes($base->getNetMagicBytes());
        $this->cashAddressPrefix = $cashAddressPrefix;
    }

    /**
     * @return string
     */
    public function getCashAddressPrefix() {
        return $this->cashAddressPrefix;
    }
}
