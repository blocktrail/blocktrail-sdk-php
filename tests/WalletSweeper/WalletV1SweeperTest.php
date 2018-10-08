<?php

namespace Blocktrail\SDK\Tests\WalletSweeper;

use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Bitcoin\Script\P2shScript;
use BitWasp\Bitcoin\Script\Parser\Operation;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\Address\BitcoinAddressReader;
use Blocktrail\SDK\Bitcoin\BIP32Path;
use Blocktrail\SDK\Tests\Wallet\CreateWalletTest;
use Blocktrail\SDK\Tests\Wallet\WalletTestBase;
use Blocktrail\SDK\UnspentOutputFinder;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletV1Sweeper;
use Btccom\JustEncrypt\KeyDerivation;

class WalletV1SweeperTest extends WalletTestBase
{
    public function testSweep()
    {
        $network = "btc";
        $client = $this->mockSDK();
        $batchSize = 5;

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;
        $primarySeed = new Buffer('primaryseed', 64);
        $backupSeed = new Buffer('backupseed', 64);

        $primaryMnemonic = MnemonicFactory::bip39()->entropyToMnemonic($primarySeed);
        $backupMnemonic = MnemonicFactory::bip39()->entropyToMnemonic($backupSeed);

        // primary
        $client->shouldReceive('generateNewMnemonic')->andReturn($primaryMnemonic)->once();
        // backup
        $client->shouldReceive('generateNewMnemonic')->andReturn($backupMnemonic)->once();

        $client->shouldReceive('storeNewWalletV1')
            ->withArgs([
                $identifier,
                ["tpubDA5AEZ2jW8afwPeKGwNGb52ydoNVtYRTiPsxtyw7i6eJ2qBwzhXrR9mRV1AkBPkYzPMApdiiGPtJ6CetcYzZkWAHVWxk4hsZU9vc1XWS8aa", "M/9999'"],
                ["tpubD6NzVbkrYhZ4Wg9K1EjgX5F9rCi3nMjpMsyMZGyketABh8Y2Cfn92dfHAWEffZB3VJAZS2rd5iYTGfyiW32dEzWCnxubrDbbLHig3JGcvEZ", "M"],
                "abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon achieve atom harvest help frame very curve razor muscle spawn",
                "n3yffrKj4xReaWjt5pdLoe7ZoBwe1UGTm8",
                9999,
                false
            ])
            ->andReturn([
                "blocktrail_public_keys" => [
                    9999 => ["tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", "M/9999'"],
                ],
                "segwit" => false,
                "key_index" => 9999,
                "upgrade_key_index" => null
            ])
            ->once();

        $client->createNewWallet([
            'identifier' => $identifier,
            'password' => $password,
            'key_index' => 9999,
            'wallet_version' => Wallet::WALLET_VERSION_V1,
        ]);

        $utxo = [  "hash" => "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
            "index" => 0,
            "value" => 100000000,
            'address' => '2NEcM4eKZVhdjjHzpf19wBYThdQxD4ehevG',
            'script_hex' => 'a914ea594860336ee7ec1e4b67e0ed59452dec088ba887',];

        $utxoFinder = \Mockery::mock(UnspentOutputFinder::class);
        $utxoFinder->shouldReceive("getUTXOs")
            ->withArgs(function ($addrs) use ($batchSize) {
                return count($addrs) === $batchSize;
            })
            ->andReturns(
                [$utxo],
                []
            );

        $sweeper = new WalletV1Sweeper($primaryMnemonic, $password, $backupMnemonic, [
            [
                "keyIndex" => 9999,
                "pubkey" => "tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ",
                "path" => "M/9999'"
            ]
        ], $utxoFinder, $network, true);

        $sweeper->discoverWalletFunds($batchSize);

        $tx = $sweeper->sweepWallet("mjpqMuKZA9gYnxXFNdkGLQEMKxokhESvMG", $batchSize);
        $addrReader = new BitcoinAddressReader();
        $script = $addrReader->fromString("mjpqMuKZA9gYnxXFNdkGLQEMKxokhESvMG")->getScriptPubKey();

        $decoded = TransactionFactory::fromHex($tx);
        $this->assertCount(1, $decoded->getInputs());
        $this->assertEquals($utxo['hash'], $decoded->getInput(0)->getOutPoint()->getTxId()->getHex());
        $this->assertEquals($utxo['index'], $decoded->getInput(0)->getOutPoint()->getVout());
        $this->assertCount(1, $decoded->getOutputs());
        $this->assertEquals($script->getHex(), $decoded->getOutput(0)->getScript()->getHex());

        /** @var Operation[] $decodedScript */
        $decodedScript = $decoded->getInput(0)->getScript()->getScriptParser()->decode();
        $rs = end($decodedScript);
        $this->assertTrue($rs->isPush());
        $this->assertEquals($utxo['script_hex'], (new P2shScript(ScriptFactory::fromHex($rs->getData())))->getOutputScript()->getHex());
    }
}
