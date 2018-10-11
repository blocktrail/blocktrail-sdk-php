<?php

namespace Blocktrail\SDK\Tests\Wallet;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Wallet;
use Mockery\Mock;

class UpgradeKeyIndexWalletTest extends WalletTestBase {

    public function testUpgradeKeyIndex() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $client->shouldReceive('upgradeKeyIndex')
            ->withArgs([$identifier, 10000, ["tpubD8Ke9MXfpW2zy79D86PwryY3rt6FdHmFg1V3T666ieNSaLrwXXAG8VwrTqR8oX553FoQMH9WdY3Q4qCMP9Uc23GXrJV9tRLySTAt4WVEox5", "M/10000'"]])
            ->andReturn([
                'blocktrail_public_keys' => [
                    9999 => ['tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ', "M/9999'"],
                    10000 => ['tpubD9m9hziKhYQExWgzMUNXdYMNUtourv96sjTUS9jJKdo3EDJAnCBJooMPm6vGSmkNTNAmVt988dzNfNY12YYzk9E6PkA7JbxYeZBFy4XAaCp', "M/10000'"],
                ],
            ])
            ->once();

        $wallet->upgradeKeyIndex(10000);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Wallet needs to be unlocked to upgrade key index
     */
    public function testUpgradeKeyIndexRequiresUnlock() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, null, false, [
            'readonly' => true,
        ]);

        $wallet->upgradeKeyIndex(10000);
    }
}
