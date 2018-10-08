<?php

namespace Blocktrail\SDK\Tests\WalletSweeper;

use BitWasp\Bitcoin\Script\P2shScript;
use BitWasp\Bitcoin\Script\Parser\Operation;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\Address\BitcoinAddressReader;
use Blocktrail\SDK\Tests\Wallet\CreateWalletTest;
use Blocktrail\SDK\Tests\Wallet\WalletTestBase;
use Blocktrail\SDK\UnspentOutputFinder;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletV2Sweeper;
use Blocktrail\SDK\WalletV3Sweeper;
use Btccom\JustEncrypt\KeyDerivation;

class WalletV3SweeperTest extends WalletTestBase
{
    public function testSweep()
    {
        $network = "btc";
        $client = $this->mockSDK();
        $batchSize = 5;

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;
        $primarySeed = new Buffer('primaryseed', 32);
        $backupSeed = new Buffer('backupseed', 32);
        $secret = new Buffer('secret', 32);
        $recoverySecret = new Buffer('recoverysecret', 32);

        $encryptedSecret = CreateWalletTest::mockV3Encrypt($secret, new Buffer($password), KeyDerivation::DEFAULT_ITERATIONS)->getBuffer();
        $encryptedPrimarySeed = CreateWalletTest::mockV3Encrypt($primarySeed, $secret, KeyDerivation::SUBKEY_ITERATIONS)->getBuffer();
        $recoveryEncryptedSecret = CreateWalletTest::mockV3Encrypt($secret, $recoverySecret, KeyDerivation::DEFAULT_ITERATIONS)->getBuffer();

        $client->shouldReceive('newV3PrimarySeed')->andReturn($primarySeed)->once();
        $client->shouldReceive('newV3BackupSeed')->andReturn($backupSeed)->once();
        $client->shouldReceive('newV3Secret')->withArgs([$password])->andReturn([$secret, $encryptedSecret])->once();
        $client->shouldReceive('newV3EncryptedPrimarySeed')->andReturn($encryptedPrimarySeed)->once();
        $client->shouldReceive('newV3RecoverySecret')->andReturn([$recoverySecret, $recoveryEncryptedSecret])->once();
        $client->shouldReceive('storeNewWalletV3')
            ->withArgs([
                $identifier,
                ["tpubD8Ke9MXfpW2zvymTPJAxETZE3KnBdsTwjNB3sHRj2iUJMPpGTMbZHyJPuMASFzJC4FbEeCg5r7N7HN36oPwibL9mWTRx9d2J6VPhsH9NEAS", "M/9999'"],
                ["tpubD6NzVbkrYhZ4WTekiCroqzjETeQbxvQy8azRH4MT3tKhzZvf8G2QWWFdrTaiTHdYJVNPJJ85nvHjhP9dP7CZAkKsBEJ95DuY7bNZH1Gvw4w", "M"],
                "CgAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAHp305+tX2NFSGsQcEiJFpHFXg8L6GJyBt0Zzr7M+KsD5P1zH0PgehzhxdIJZzmHlA==",
                "CgAAAAAAAAAAAAC4iAAAAAAAAAAAAAAAAAAAAAAAADOe7reb5jZH+CmNxEtRtYQzMp/cZPAigKzZWo9yYxOqD4smZbROEj5zn8aeek5ZfA==",
                "0000000000000000000000000000000000007265636f76657279736563726574",
                "mgGXvgCUxJMt1mf9vdzdyPaJjmaKVHAtkZ",
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

        $res = $client->createNewWallet([
            'identifier' => $identifier,
            'password' => $password,
            'key_index' => 9999,
        ]);
        /** @var Wallet $wallet */
        $wallet = $res[0];

        $backupData = $res[1];

        $utxo = [  "hash" => "abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234",
            "index" => 0,
            "value" => 100000000,
            'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
            'script_hex' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',];

        $utxoFinder = \Mockery::mock(UnspentOutputFinder::class);
        $utxoFinder->shouldReceive("getUTXOs")
            ->withArgs(function ($addrs) use ($batchSize) {
                return count($addrs) === $batchSize;
            })
            ->andReturns(
                [$utxo],
                []
            );

        $sweeper = new WalletV3Sweeper($backupData['encrypted_primary_seed'], $backupData['encrypted_secret'], $backupData['backup_seed'], new Buffer($password), [
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
