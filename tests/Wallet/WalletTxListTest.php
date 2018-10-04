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

class WalletTxListTest extends WalletTestBase {

    public function testWalletTxList() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $client->shouldReceive('walletTransactions')
                ->withArgs([$identifier, 5, 36, 'desc'])
                ->andReturn([])
                ->once();

        $wallet->transactions(5, 36, 'desc');
    }
}
