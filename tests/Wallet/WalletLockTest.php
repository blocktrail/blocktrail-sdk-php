<?php

namespace Blocktrail\SDK\Tests\Wallet;

use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\Wallet;
use Btccom\JustEncrypt\KeyDerivation;

class WalletLockTest extends WalletTestBase
{
    public function testV3Wallet() {
        $client = $this->mockSDK();

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

        list ($wallet) = $client->createNewWallet([
            'identifier' => $identifier,
            'password' => $password,
            'key_index' => 9999,
        ]);
        return $wallet;
    }

    public function testV2Wallet() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;
        $primarySeed = (new Buffer('primaryseed', 32))->getBinary();
        $backupSeed = (new Buffer('backupseed', 32))->getBinary();
        $secret = (new Buffer('secret', 32))->getBinary();
        $recoverySecret = 'recoverysecretrecoverysecretreco';

        $encryptedSecret = CreateWalletTest::mockV2Encrypt($secret, $password);
        $encryptedPrimarySeed = CreateWalletTest::mockV2Encrypt($primarySeed, $secret);
        $recoveryEncryptedSecret = CreateWalletTest::mockV2Encrypt($secret, $recoverySecret);

        $client->shouldReceive('newV2PrimarySeed')->andReturn($primarySeed)->once();
        $client->shouldReceive('newV2BackupSeed')->andReturn($backupSeed)->once();
        $client->shouldReceive('newV2Secret')->withArgs([$password])->andReturn([$secret, $encryptedSecret])->once();
        $client->shouldReceive('newV2EncryptedPrimarySeed')->andReturn($encryptedPrimarySeed)->once();
        $client->shouldReceive('newV2RecoverySecret')->andReturn([$recoverySecret, $recoveryEncryptedSecret])->once();

        $client->shouldReceive('storeNewWalletV2')
            ->withArgs([
                $identifier,
                ["tpubD8Ke9MXfpW2zvymTPJAxETZE3KnBdsTwjNB3sHRj2iUJMPpGTMbZHyJPuMASFzJC4FbEeCg5r7N7HN36oPwibL9mWTRx9d2J6VPhsH9NEAS", "M/9999'"],
                ["tpubD6NzVbkrYhZ4WTekiCroqzjETeQbxvQy8azRH4MT3tKhzZvf8G2QWWFdrTaiTHdYJVNPJJ85nvHjhP9dP7CZAkKsBEJ95DuY7bNZH1Gvw4w", "M"],
                "U2FsdGVkX18AAAAAAAAAAIgSvbD8v2Jln/Psv74dZRFwMaQtESkRg3Qwu+vgQjN2h+Dh+LfP2Y0qIf04o2IUIA==",
                "U2FsdGVkX18AAAAAAAAAAMBAD4ly9jVWNEGJZ39HZ33es71t3TUtIVIqwDqQ6XdDcmRHmm6GBqZYZAoNgfHbyw==",
                "recoverysecretrecoverysecretreco",
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

        list ($wallet) = $client->createNewWallet([
            'identifier' => $identifier,
            'password' => $password,
            'key_index' => 9999,
            'wallet_version' => Wallet::WALLET_VERSION_V2,
        ]);
        return $wallet;
    }

    public function testV1Wallet() {
        $client = $this->mockSDK();

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

        list ($wallet) = $client->createNewWallet([
            'identifier' => $identifier,
            'password' => $password,
            'key_index' => 9999,
            'wallet_version' => Wallet::WALLET_VERSION_V1,
        ]);
        return $wallet;
    }

    /**
     * @param Wallet $wallet
     * @depends testV1Wallet
     */
    public function testV1WalletLock(Wallet $wallet) {
        $this->assertFalse($wallet->isLocked());

        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
        ]);
        $this->assertFalse($wallet->isLocked());

        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
        ]);
        $this->assertFalse($wallet->isLocked());
    }

    /**
     * @param Wallet $wallet
     * @depends testV1Wallet
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Can't specify Primary Mnemonic and Primary PrivateKey
     */
    public function testV1WalletCantProvidePrivateKeyWhenMnemonicKnown(Wallet $wallet) {
        $this->assertFalse($wallet->isLocked());
        $wallet->lock();

        try {
            $wallet->unlock([
                "password" => self::WALLET_PASSWORD,
                "primary_private_key" => "xpub"
            ]);
        } catch (\Exception $e) {
            // need to unlock for further tests to continue
            $wallet->unlock([
                "password" => self::WALLET_PASSWORD,
            ]);
            throw $e;
        }

        $this->fail("exception should be thrown");
    }

    /**
     * @param Wallet $wallet
     * @depends testV1Wallet
     */
    public function testV1WalletUnlockWithCallback(Wallet $wallet) {
        $this->assertFalse($wallet->isLocked());
        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
        ], function () {

        });
        $this->assertTrue($wallet->isLocked());
    }

    /**
     * @param Wallet $wallet
     * @depends testV1Wallet
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Can't init wallet with Primary Mnemonic without a passphrase
     */
    public function testV1WalletUnlockWithoutPrimarySeedRequiresPassword(Wallet $wallet) {
        $wallet->lock();

        $wallet->unlock([]);
    }

    /**
     * @param Wallet $wallet
     * @depends testV1Wallet
     * @expectedException \Exception
     * @expectedExceptionMessage Checksum [mgk849xjromxGQdJfHSohGUdhMtNUp49ct] does not match [n3yffrKj4xReaWjt5pdLoe7ZoBwe1UGTm8], most likely due to incorrect password
     */
    public function testV1WalletUnlockWithoutPrimarySeedInvalidPassword(Wallet $wallet) {
        $wallet->lock();

        $wallet->unlock([
            "password" => "not the password",
        ]);
    }

    /**
     * @param Wallet $wallet
     * @depends testV2Wallet
     */
    public function testV2WalletLock(Wallet $wallet) {
        $this->assertFalse($wallet->isLocked());

        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
        ]);
        $this->assertFalse($wallet->isLocked());

        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
        ]);
        $this->assertFalse($wallet->isLocked());
    }

    /**
     * @param Wallet $wallet
     * @depends testV2Wallet
     */
    public function testV2WalletUnlockWithCallback(Wallet $wallet) {
        $this->assertFalse($wallet->isLocked());
        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
        ], function () {

        });
        $this->assertTrue($wallet->isLocked());
    }

    /**
     * @param Wallet $wallet
     * @depends testV2Wallet
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Can't init wallet with Primary Seed without a passphrase
     */
    public function testV2WalletUnlockWithoutPrimarySeedRequiresPassword(Wallet $wallet) {
        $wallet->lock();

        $wallet->unlock([]);
    }

    /**
     * @param Wallet $wallet
     * @depends testV2Wallet
     * @expectedException \Blocktrail\SDK\Exceptions\WalletDecryptException
     * @expectedExceptionMessage Failed to decrypt secret with password
     */
    public function testV2WalletUnlockWithoutPrimarySeedInvalidPassword(Wallet $wallet) {
        $wallet->lock();

        $wallet->unlock([
            "password" => "not the password",
        ]);
    }

    /**
     * @param Wallet $wallet
     * @depends testV3Wallet
     */
    public function testV3WalletLock(Wallet $wallet) {
        $this->assertFalse($wallet->isLocked());

        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
        ]);
        $this->assertFalse($wallet->isLocked());

        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
        ]);
        $this->assertFalse($wallet->isLocked());
    }

    /**
     * @param Wallet $wallet
     * @depends testV3Wallet
     */
    public function testV3WalletUnlockWithCallback(Wallet $wallet) {
        $this->assertFalse($wallet->isLocked());
        $wallet->lock();
        $this->assertTrue($wallet->isLocked());

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
        ], function () {

        });
        $this->assertTrue($wallet->isLocked());
    }

    /**
     * @param Wallet $wallet
     * @depends testV3Wallet
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Primary Seed must be a BufferInterface
     */
    public function testV3WalletUnlockWithPrimarySeedMustBeBuffer(Wallet $wallet) {
        $wallet->lock();

        $wallet->unlock([
            "password" => self::WALLET_PASSWORD,
            "primary_seed" => "abc..",
        ]);
    }

    /**
     * @param Wallet $wallet
     * @depends testV3Wallet
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Can't init wallet with Primary Seed without a passphrase
     */
    public function testV3WalletUnlockWithoutPrimarySeedRequiresPassword(Wallet $wallet) {
        $wallet->lock();

        $wallet->unlock([]);
    }

    /**
     * @param Wallet $wallet
     * @depends testV3Wallet
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Failed to decrypt secret with password
     */
    public function testV3WalletUnlockWithoutPrimarySeedInvalidPassword(Wallet $wallet) {
        $wallet->lock();

        $wallet->unlock([
            "password" => "not the password",
        ]);
    }

    /**
     * @param Wallet $wallet
     * @depends testV3Wallet
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Can't init wallet with Primary Seed without a passphrase
     */
    public function testV3WalletUnlock(Wallet $wallet) {
        $wallet->lock();

        $wallet->unlock([]);
    }
}
