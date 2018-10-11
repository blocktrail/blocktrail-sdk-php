<?php

namespace Blocktrail\SDK\Tests\Wallet;

use Blocktrail\SDK\Bitcoin\BIP32Path;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Wallet;
use Mockery\Mock;

class GetBlocktrailKeysTest extends WalletTestBase
{
    /**
     * @expectedExceptionMessage No blocktrail publickey for key index [123123]
     * @expectedException \Exception
     */
    public function testInvalidKeyIndex()
    {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier);

        $wallet->getBlocktrailPublicKey(BIP32Path::path("M/123123'/0/0"));
    }
}
