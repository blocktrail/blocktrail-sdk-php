<?php

namespace Blocktrail\SDK\Network;

interface BitcoinCashNetworkInterface
{
    /**
     * @return string
     */
    public function getCashAddressPrefix();
}
