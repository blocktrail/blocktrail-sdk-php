<?php

namespace Blocktrail\SDK;


use BitWasp\Bitcoin\Network\NetworkInterface;

class NetworkParams
{
    /**
     * @var NetworkInterface
     */
    private $network;

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $testnet;

    /**
     * @var string
     */
    private $shortCode;

    /**
     * NetworkParams constructor.
     * @param NetworkInterface $network
     * @param string $name
     * @param string $shortCode
     * @param bool $testnet
     */
    public function __construct($shortCode, $name, $testnet, NetworkInterface $network) {
        $this->network = $network;
        $this->name = $name;
        $this->testnet = $testnet;
        $this->shortCode = $shortCode;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getShortCode() {
        return $this->shortCode;
    }

    /**
     * @param $network
     * @return bool
     */
    public function isNetwork($network) {
        return $this->name === $network;
    }

    /**
     * @return bool
     */
    public function isTestnet() {
        return $this->testnet;
    }

    /**
     * @return NetworkInterface
     */
    public function getNetwork() {
        return $this->network;
    }
}
