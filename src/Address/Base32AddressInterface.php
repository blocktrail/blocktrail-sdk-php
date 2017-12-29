<?php

namespace Blocktrail\SDK\Address;

use BitWasp\Bitcoin\Address\AddressInterface;
use Blocktrail\SDK\Network\BitcoinCashNetworkInterface;

interface Base32AddressInterface extends AddressInterface
{
    /**
     * @param BitcoinCashNetworkInterface $network
     * @return string
     */
    public function getPrefix(BitcoinCashNetworkInterface $network);
}
