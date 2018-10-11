<?php

namespace Blocktrail\SDK\Tests\Wallet;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Wallet;
use Mockery\Mock;

class GetBalanceTest extends WalletTestBase
{
    public function testInvalidKeyIndex()
    {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $expectConfirmed = 100000000;
        $expectUnconfirmed = 2100000000;

        $client->shouldReceive('getWalletBalance')
            ->withArgs(function($walletIdentifier) use ($identifier) {
                return $walletIdentifier === $identifier;
            })
            ->andReturn([
                'confirmed' => $expectConfirmed,
                'unconfirmed' => $expectUnconfirmed,
            ]);

        list ($confirmed, $unconfirmed) = $wallet->getBalance();
        $this->assertEquals($expectConfirmed, $confirmed);
        $this->assertEquals($expectUnconfirmed, $unconfirmed);
    }
}
