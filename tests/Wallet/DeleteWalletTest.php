<?php

namespace Blocktrail\SDK\Tests\Wallet;


use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Wallet;
use Mockery\Mock;

class DeleteWalletTest extends WalletTestBase
{
    public function testDeleteWallet() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $client->shouldReceive('deleteWallet')
            ->withArgs(function ($walletIdent, $checksumAddr, $signature, $force) use ($identifier) {
                return $walletIdent === $identifier &&
                    $checksumAddr === "mgGXvgCUxJMt1mf9vdzdyPaJjmaKVHAtkZ" &&
                    !$force
                ;
            })
            ->andReturn([
                'deleted' => true,
            ])
            ->once();

        $wallet->deleteWallet();
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Wallet needs to be unlocked to delete wallet
     */
    public function testNeedsUnlockedWallet() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $wallet->lock();
        $wallet->deleteWallet();
    }
}
