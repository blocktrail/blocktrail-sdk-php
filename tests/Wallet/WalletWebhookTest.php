<?php

namespace Blocktrail\SDK\Tests\Wallet;

use BitWasp\Bitcoin\Address\SegwitAddress;
use BitWasp\Bitcoin\Script\WitnessProgram;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\SignInfo;
use Blocktrail\SDK\TransactionBuilder;
use Blocktrail\SDK\Wallet;
use Mockery\Mock;

class WalletWebhookTest extends WalletTestBase {

    public function testSetupWalletWebhookDefault() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $client->shouldReceive('setupWalletWebhook')
            ->withArgs([$identifier, 'WALLET-mywallet', 'http://example.com'])
            ->andReturn([])
            ->once();

        $wallet->setupWebhook('http://example.com');
    }

    public function testSetupWalletWebhookCustom() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $client->shouldReceive('setupWalletWebhook')
            ->withArgs([$identifier, 'gg', 'http://example.com'])
            ->andReturn([])
            ->once();

        $wallet->setupWebhook('http://example.com', 'gg');
    }
    public function testDeleteWalletWebhookDefault() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $client->shouldReceive('deleteWalletWebhook')
            ->withArgs([$identifier, 'WALLET-mywallet'])
            ->andReturn([])
            ->once();

        $wallet->deleteWebhook();
    }

    public function testDeleteWalletWebhookCustom() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $client->shouldReceive('deleteWalletWebhook')
            ->withArgs([$identifier, 'gg'])
            ->andReturn([])
            ->once();

        $wallet->deleteWebhook('gg');
    }
}
