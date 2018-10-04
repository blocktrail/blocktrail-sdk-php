<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\Util;

class UtilTest extends \PHPUnit_Framework_TestCase
{
    public function parseApiNetworkProvider() {
        return [
            ['BTC', false, 'BTC', false],
            ['BTC', true, 'tBTC', true],
            ['BCC', false, 'BCC', false],
            ['BCC', true, 'tBCC', true],

            // these are always testnet
            ['TBTC', false, 'tBTC', true],
            ['TBTC', true, 'tBTC', true],

            ['RBTC', false, 'rBTC', true],
            ['RBTC', true, 'rBTC', true],
            ['RBCH', true, 'rBCH', true],

            ['TBCC', false, 'tBCC', true],
            ['TBCC', true, 'tBCC', true],

            // garbage defaults to BTC
            [null, null, 'BTC', false],
        ];
    }

    /**
     * @param $network
     * @param $testnet
     * @param $expectNetwork
     * @param $expectTestnet
     * @dataProvider parseApiNetworkProvider
     */
    public function testParseApiNetwork($network, $testnet, $expectNetwork, $expectTestnet)
    {
        list ($apiNetwork, $apiTestnet) = Util::parseApiNetwork($network, $testnet);

        $this->assertEquals($expectNetwork, $apiNetwork);
        $this->assertEquals($expectTestnet, $apiTestnet);

        $sawException = false;
        try {
            Util::normalizeNetwork($apiNetwork, $apiTestnet);
        } catch (\Exception $e) {
            $sawException = true;
        }

        $this->assertFalse($sawException, "shouldn't produce data that cannot be used by normalizeNetwork");
    }
}
