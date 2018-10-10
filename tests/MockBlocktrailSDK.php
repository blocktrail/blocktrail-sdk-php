<?php

namespace Blocktrail\SDK\Tests;

use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\Buffertools;
use Blocktrail\SDK\Backend\ConverterInterface;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\BlocktrailSDKInterface;
use Blocktrail\SDK\Connection\RestClientInterface;
use Mockery\Mock;

class MockBlocktrailSDK extends BlocktrailSDK implements BlocktrailSDKInterface {

    public function __construct($apiKey, $apiSecret, $network = 'BTC', $testnet = false, $apiVersion = 'v1', $apiEndpoint = null) {
        parent::__construct($apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint);

        // unset data clients to make sure tests don't excidently use them
        $this->dataClient = null;
        $this->blocktrailClient = null;
    }

    /**
     * @param ConverterInterface|Mock $converter
     * @return ConverterInterface|Mock
     */
    public function setConverter($converter) {
        $this->converter = $converter;

        return $converter;
    }

    /**
     * @param RestClientInterface|Mock $client
     * @return RestClientInterface|Mock
     */
    public function setDataClient($client) {
        $this->dataClient = $client;

        return $client;
    }

    /**
     * @param RestClientInterface|Mock $client
     * @return RestClientInterface|Mock
     */
    public function setBlocktrailClient($client) {
        $this->blocktrailClient = $client;

        return $client;
    }
}
