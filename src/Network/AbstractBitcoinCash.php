<?php

namespace Blocktrail\SDK\Network;

use BitWasp\Bitcoin\Network\Network;
use BitWasp\Bitcoin\Network\NetworkInterface;

abstract class AbstractBitcoinCash extends Network implements BitcoinCashNetworkInterface
{
    /**
     * @var string
     */
    private $cashAddressPrefix;

    /**
     * BitcoinCash constructor.
     * @param NetworkInterface $base
     * @param string $cashAddressPrefix
     * @throws \Exception
     */
    public function __construct(NetworkInterface $base, $cashAddressPrefix) {
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
