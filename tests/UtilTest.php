<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\Util;

class UtilTest extends BlocktrailTestCase
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

            ['TBCC', false, 'tBCC', true],
            ['TBCC', true, 'tBCC', true],

            // garbage defaults to BTC
            [null, null, 'BTC', false],
        ];
    }

    private function checkTestnetNormalize($network, $name)
    {
        $res = Util::normalizeNetwork($network, true);
        $this->assertEquals($name, $res->getName());
        $this->assertEquals($network, $res->getShortCode());
        $this->assertTrue($res->isTestnet());

        $res = Util::normalizeNetwork($network, false);
        $this->assertEquals($name, $res->getName());
        $this->assertEquals($network, $res->getShortCode());
        $this->assertTrue($res->isTestnet());
    }

    private function checkTestnetToggle($network, $name)
    {
        $res = Util::normalizeNetwork($network, false);
        $this->assertEquals($name, $res->getName());
        $this->assertEquals($network, $res->getShortCode());
        $this->assertFalse($res->isTestnet());

        $res = Util::normalizeNetwork($network, true);
        $this->assertEquals($name, $res->getName());
        $this->assertEquals($network, $res->getShortCode());
        $this->assertTrue($res->isTestnet());
    }

    public function testNormalizeNetwork() {
        $this->checkTestnetNormalize("tbtc", "bitcoin");
        $this->checkTestnetNormalize("tbcc", "bitcoincash");
        $this->checkTestnetNormalize("rbtc", "bitcoin");

        $this->checkTestnetToggle("btc", "bitcoin");
        $this->checkTestnetToggle("bcc", "bitcoincash");
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
