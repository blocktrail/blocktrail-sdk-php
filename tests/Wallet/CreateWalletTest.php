<?php

namespace Blocktrail\SDK\Tests\Wallet;

use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Buffertools\Buffer;
use Blocktrail\CryptoJSAES\CryptoJSAES;
use Blocktrail\SDK\Address\BitcoinAddressReader;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletV3;
use Btccom\JustEncrypt\Encryption;
use Btccom\JustEncrypt\KeyDerivation;

class CreateWalletTest extends WalletTestBase {

    /**
     * helper to mock encryption without randomness for V3
     *
     * @param Buffer $plainText
     * @param        $passphrase
     * @param        $iterations
     * @return \Btccom\JustEncrypt\EncryptedBlob
     */
    public static function mockV3Encrypt(Buffer $plainText, $passphrase, $iterations) {
        $salt = new Buffer('', KeyDerivation::DEFAULT_SALTLEN);
        $iv = new Buffer('', Encryption::IVLEN_BYTES);
        return Encryption::encryptWithSaltAndIV($plainText, $passphrase, $salt, $iv, $iterations);
    }

    /**
     * helper to mock encryption without randomness
     *
     * @param        $data
     * @param        $passphrase
     * @return string
     */
    public static function mockV2Encrypt($data, $passphrase) {
        $salt = (new Buffer('', 8))->getBinary();
        list($key, $iv) = CryptoJSAES::evpkdf($passphrase, $salt);

        $ct = openssl_encrypt($data, 'aes-256-cbc', $key, true, $iv);

        return CryptoJSAES::encode($ct, $salt);
    }

    protected function shouldStoreNewWalletV3($client, $identifier) {
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
    }

    public function testCreateWalletV3() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;
        $primarySeed = new Buffer('primaryseed', 32);
        $backupSeed = new Buffer('backupseed', 32);
        $secret = new Buffer('secret', 32);
        $recoverySecret = new Buffer('recoverysecret', 32);

        $encryptedSecret = self::mockV3Encrypt($secret, new Buffer($password), KeyDerivation::DEFAULT_ITERATIONS)->getBuffer();
        $encryptedPrimarySeed = self::mockV3Encrypt($primarySeed, $secret, KeyDerivation::SUBKEY_ITERATIONS)->getBuffer();
        $recoveryEncryptedSecret = self::mockV3Encrypt($secret, $recoverySecret, KeyDerivation::DEFAULT_ITERATIONS)->getBuffer();

        $client->shouldReceive('newV3PrimarySeed')->andReturn($primarySeed)->once();
        $client->shouldReceive('newV3BackupSeed')->andReturn($backupSeed)->once();
        $client->shouldReceive('newV3Secret')->withArgs([$password])->andReturn([$secret, $encryptedSecret])->once();
        $client->shouldReceive('newV3EncryptedPrimarySeed')->andReturn($encryptedPrimarySeed)->once();
        $client->shouldReceive('newV3RecoverySecret')->andReturn([$recoverySecret, $recoveryEncryptedSecret])->once();

        $this->shouldStoreNewWalletV3($client, $identifier);

        $res = $client->createNewWallet([
            'identifier' => $identifier,
            'password' => $password,
            'key_index' => 9999,
        ]);
        /** @var Wallet $wallet */
        $wallet = $res[0];

        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M", $wallet->getBackupKey()[1]);
        $this->assertEquals("tpubD6NzVbkrYhZ4WTekiCroqzjETeQbxvQy8azRH4MT3tKhzZvf8G2QWWFdrTaiTHdYJVNPJJ85nvHjhP9dP7CZAkKsBEJ95DuY7bNZH1Gvw4w", $wallet->getBackupKey()[0]);
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key()->toExtendedKey());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key()->toExtendedKey());

        $client->shouldReceive('_getNewDerivation')
            ->withArgs([$identifier, "m/9999'/0/*"])
            ->andReturn([
                'path' => "M/9999'/0/0",
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'scriptpubkey' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ])
            ->once();

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa", $address);

        $client->shouldReceive('_getNewDerivation')
            ->withArgs([$identifier, "m/9999'/0/*"])
            ->andReturn([
                'path' => "M/9999'/0/1",
                'address' => '2N38FErCcqDq1sqKAoQPkFtBKEosjTe4uaz',
                'scriptpubkey' => 'a9146c5f63f72c26725c100bc6ab0156e0dd36ffc8a087',
                'redeem_script' => '522102382362e09b4f9e15ba16397fc3b4c4caaf82da29819c5decf1f4d650bf088adf2102453a9ebff02859cb285987a2b14da82a05f87250bca5ba994e05dec4a6c7a6df21033dcb8536d7e0c6a3a556f07accd55262dd4f452d4ca702e100557be6c26b096453ae',
                'witness_script' => null,
            ])
            ->once();

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2N38FErCcqDq1sqKAoQPkFtBKEosjTe4uaz", $address);

        // get the 2nd address again
        $this->assertEquals("2N38FErCcqDq1sqKAoQPkFtBKEosjTe4uaz", $wallet->getAddressByPath("M/9999'/0/1"));

        // get some more addresses
        $this->assertEquals("2NDeL5p8sX89QE2FAxTvuiZdNbk6Jv2vRVs", $wallet->getAddressByPath("M/9999'/0/6"));
        $this->assertEquals("2NBP1aarake1UfiTU6aPrtdyooSQY7Dgm4T", $wallet->getAddressByPath("M/9999'/0/44"));

        return $wallet;
    }

    public function testCreateWalletV3CustomPrimary() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;
        $primarySeed = new Buffer('primaryseed', 32);
        $backupSeed = new Buffer('backupseed', 32);
        $secret = new Buffer('secret', 32);
        $recoverySecret = new Buffer('recoverysecret', 32);

        $encryptedSecret = self::mockV3Encrypt($secret, new Buffer($password), KeyDerivation::DEFAULT_ITERATIONS)->getBuffer();
        $encryptedPrimarySeed = self::mockV3Encrypt($primarySeed, $secret, KeyDerivation::SUBKEY_ITERATIONS)->getBuffer();
        $recoveryEncryptedSecret = self::mockV3Encrypt($secret, $recoverySecret, KeyDerivation::DEFAULT_ITERATIONS)->getBuffer();

        $client->shouldReceive('newV3BackupSeed')->andReturn($backupSeed)->once();

        $client->shouldReceive('storeNewWalletV3')
            ->withArgs([
                $identifier,
                ["tpubD8Ke9MXfpW2zvymTPJAxETZE3KnBdsTwjNB3sHRj2iUJMPpGTMbZHyJPuMASFzJC4FbEeCg5r7N7HN36oPwibL9mWTRx9d2J6VPhsH9NEAS", "M/9999'"],
                ["tpubD6NzVbkrYhZ4WTekiCroqzjETeQbxvQy8azRH4MT3tKhzZvf8G2QWWFdrTaiTHdYJVNPJJ85nvHjhP9dP7CZAkKsBEJ95DuY7bNZH1Gvw4w", "M"],
                false,
                false,
                false,
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
            'store_data_on_server' => false,
            'primary_seed' => $primarySeed,
            'key_index' => 9999,
        ]);
        /** @var Wallet $wallet */
        $wallet = $res[0];

        $this->assertTrue(!!$wallet);
    }

    public function testCreateWalletV2() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;
        $primarySeed = (new Buffer('primaryseed', 32))->getBinary();
        $backupSeed = (new Buffer('backupseed', 32))->getBinary();
        $secret = (new Buffer('secret', 32))->getBinary();
        $recoverySecret = 'recoverysecretrecoverysecretreco';

        $encryptedSecret = self::mockV2Encrypt($secret, $password);
        $encryptedPrimarySeed = self::mockV2Encrypt($primarySeed, $secret);
        $recoveryEncryptedSecret = self::mockV2Encrypt($secret, $recoverySecret);

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

        $res = $client->createNewWallet([
            'identifier' => $identifier,
            'password' => $password,
            'key_index' => 9999,
            'wallet_version' => Wallet::WALLET_VERSION_V2,
        ]);
        /** @var Wallet $wallet */
        $wallet = $res[0];

        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M", $wallet->getBackupKey()[1]);
        $this->assertEquals("tpubD6NzVbkrYhZ4WTekiCroqzjETeQbxvQy8azRH4MT3tKhzZvf8G2QWWFdrTaiTHdYJVNPJJ85nvHjhP9dP7CZAkKsBEJ95DuY7bNZH1Gvw4w", $wallet->getBackupKey()[0]);
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key()->toExtendedKey());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key()->toExtendedKey());

        $client->shouldReceive('_getNewDerivation')
            ->withArgs([$identifier, "m/9999'/0/*"])
            ->andReturn([
                'path' => "M/9999'/0/0",
                'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                'scriptpubkey' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                'witness_script' => null,
            ])
            ->once();

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa", $address);

        $client->shouldReceive('_getNewDerivation')
            ->withArgs([$identifier, "m/9999'/0/*"])
            ->andReturn([
                'path' => "M/9999'/0/1",
                'address' => '2N38FErCcqDq1sqKAoQPkFtBKEosjTe4uaz',
                'scriptpubkey' => 'a9146c5f63f72c26725c100bc6ab0156e0dd36ffc8a087',
                'redeem_script' => '522102382362e09b4f9e15ba16397fc3b4c4caaf82da29819c5decf1f4d650bf088adf2102453a9ebff02859cb285987a2b14da82a05f87250bca5ba994e05dec4a6c7a6df21033dcb8536d7e0c6a3a556f07accd55262dd4f452d4ca702e100557be6c26b096453ae',
                'witness_script' => null,
            ])
            ->once();

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2N38FErCcqDq1sqKAoQPkFtBKEosjTe4uaz", $address);

        // get the 2nd address again
        $this->assertEquals("2N38FErCcqDq1sqKAoQPkFtBKEosjTe4uaz", $wallet->getAddressByPath("M/9999'/0/1"));

        // get some more addresses
        $this->assertEquals("2NDeL5p8sX89QE2FAxTvuiZdNbk6Jv2vRVs", $wallet->getAddressByPath("M/9999'/0/6"));
        $this->assertEquals("2NBP1aarake1UfiTU6aPrtdyooSQY7Dgm4T", $wallet->getAddressByPath("M/9999'/0/44"));

        return $wallet;
    }

    public function testCreateWalletV1() {
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

        $res = $client->createNewWallet([
            'identifier' => $identifier,
            'password' => $password,
            'key_index' => 9999,
            'wallet_version' => Wallet::WALLET_VERSION_V1,
        ]);
        /** @var Wallet $wallet */
        $wallet = $res[0];

        $this->assertEquals($identifier, $wallet->getIdentifier());
        $this->assertEquals("M", $wallet->getBackupKey()[1]);
        $this->assertEquals("tpubD6NzVbkrYhZ4Wg9K1EjgX5F9rCi3nMjpMsyMZGyketABh8Y2Cfn92dfHAWEffZB3VJAZS2rd5iYTGfyiW32dEzWCnxubrDbbLHig3JGcvEZ", $wallet->getBackupKey()[0]);
        $this->assertEquals("M/9999'", $wallet->getBlocktrailPublicKeys()[9999][1]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKeys()[9999][0]);
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("m/9999'")->key()->toExtendedKey());
        $this->assertEquals("tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ", $wallet->getBlocktrailPublicKey("M/9999'")->key()->toExtendedKey());

        $client->shouldReceive('_getNewDerivation')
            ->withArgs([$identifier, "m/9999'/0/*"])
            ->andReturn([
                'path' => "M/9999'/0/0",
                'address' => '2NEcM4eKZVhdjjHzpf19wBYThdQxD4ehevG',
                'scriptpubkey' => 'a914ea594860336ee7ec1e4b67e0ed59452dec088ba887',
                'redeem_script' => '52210200af504e1da5fd53537bffc245ac5948cca756ee530c9fba4a797b5de9e4e48a210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e372103dd7a2df2216b3729eb7d19c654b9fd4816a34768d51e299762e0781e8f8f16c253ae',
                'witness_script' => null,
            ])
            ->once();

        // get a new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/0", $path);
        $this->assertEquals("2NEcM4eKZVhdjjHzpf19wBYThdQxD4ehevG", $address);


        $client->shouldReceive('_getNewDerivation')
            ->withArgs([$identifier, "m/9999'/0/*"])
            ->andReturn([
                'path' => "M/9999'/0/1",
                'address' => '2N3RS2ndcGy69zEfFLQ4MW7HMwUXjx1diEg',
                'scriptpubkey' => 'a9146f9f7878330a1b3416df6122b08ebf3be5624d0487',
                'redeem_script' => '5221030d057824924d0400bd9f3525a3913c74719a6f6b2f7dec8e898df378d5b2aeee21033dcb8536d7e0c6a3a556f07accd55262dd4f452d4ca702e100557be6c26b096421035a058ca98ac2640062b1a1313a74fd754f48bc29079e6830f03d45b225f54ebb53ae',
                'witness_script' => null,
            ])
            ->once();

        // get another new pair
        list($path, $address) = $wallet->getNewAddressPair();
        $this->assertEquals("M/9999'/0/1", $path);
        $this->assertEquals("2N3RS2ndcGy69zEfFLQ4MW7HMwUXjx1diEg", $address);

        // get the 2nd address again
        $this->assertEquals("2N3RS2ndcGy69zEfFLQ4MW7HMwUXjx1diEg", $wallet->getAddressByPath("M/9999'/0/1"));

        // get some more addresses
        $this->assertEquals("2NBz1n7vHdrtCJKtKUeQipRd8rBxsoKv39M", $wallet->getAddressByPath("M/9999'/0/6"));
        $this->assertEquals("2MzEv48F5qkhpaEFwDVcNUa595NJJZjqb6C", $wallet->getAddressByPath("M/9999'/0/44"));

        return $wallet;
    }

    /**
     * @param Wallet $wallet
     * @depends testCreateWalletV1
     * @expectedException \Blocktrail\SDK\Exceptions\NotImplementedException

     */
    public function testCreateWalletV1PasswordChangeNotPossible(Wallet $wallet) {
        $wallet->passwordChange('');
    }

    public function testV3EncryptedPrimarySeedNullOrBuffer() {
        $sdk = $this->mockSDK();
        $reader = new BitcoinAddressReader();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Encrypted Primary Seed must be a Buffer or null");

        new WalletV3($sdk, "walletIdentifier", "", new Buffer(''), [], [], [], 0, "btc", false, false, $reader, "");
    }
    public function testV3EncryptedSecretNullOrBuffer() {
        $sdk = $this->mockSDK();
        $reader = new BitcoinAddressReader();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Encrypted Secret must be a Buffer or null");

        new WalletV3($sdk, "walletIdentifier", new Buffer(), "", [], [], [], 0, "btc", false, false, $reader, "");
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid wallet version
     */
    public function testCreateWalletInvalidVersion() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;

        $client->createNewWallet([
            'wallet_version' => "v9912312",
            "password" => $password,
            "identifier" => $identifier,
        ]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Can only provide either passphrase or password
     */
    public function testCreateWalletBothPasswordAndPassphraseIsInvalid() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;

        $client->createNewWallet([
            'wallet_version' => "v1",
            "password" => $password,
            "passphrase" => $password."...",
            "identifier" => $identifier,
        ]);
    }
}
