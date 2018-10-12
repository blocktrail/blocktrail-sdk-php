<?php

namespace Blocktrail\SDK\Tests\Wallet;

use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Tests\MockBlocktrailSDK;
use Blocktrail\SDK\Wallet;
use Btccom\JustEncrypt\Encryption;
use Btccom\JustEncrypt\KeyDerivation;
use Mockery\Mock;

class InitWalletTest extends WalletTestBase {

    /**
     * this test is the basis for WalletBaseTest::initWallet
     */
    public function testInitWalletV3() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;

        $client->shouldReceive('getWallet')
            ->withArgs([$identifier])
            ->andReturn([
                'primary_mnemonic' => NULL,
                'encrypted_secret' => 'CgAAAAAAAAAAAAC4iAAAAAAAAAAAAAAAAAAAAAAAADOe7reb5jZH+CmNxEtRtYQzMp/cZPAigKzZWo9yYxOqD4smZbROEj5zn8aeek5ZfA==',
                'encrypted_primary_seed' => 'CgAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAHp305+tX2NFSGsQcEiJFpHFXg8L6GJyBt0Zzr7M+KsD5P1zH0PgehzhxdIJZzmHlA==',
                'wallet_version' => 'v3',
                'key_index' => 9999,
                'checksum' => 'mgGXvgCUxJMt1mf9vdzdyPaJjmaKVHAtkZ',
                'segwit' => false,
                'backup_public_key' => ['tpubD6NzVbkrYhZ4WTekiCroqzjETeQbxvQy8azRH4MT3tKhzZvf8G2QWWFdrTaiTHdYJVNPJJ85nvHjhP9dP7CZAkKsBEJ95DuY7bNZH1Gvw4w','M'],
                'blocktrail_public_keys' => [
                    9999 => ['tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ', 'M/9999\''],
                ],
                'primary_public_keys' => [
                    9999 => ['tpubD8Ke9MXfpW2zvymTPJAxETZE3KnBdsTwjNB3sHRj2iUJMPpGTMbZHyJPuMASFzJC4FbEeCg5r7N7HN36oPwibL9mWTRx9d2J6VPhsH9NEAS', 'M/9999\''],
                ],
                'upgrade_key_index' => NULL,
            ])
            ->once();

        /** @var Wallet $wallet */
        $wallet = $client->initWallet([
            'identifier' => $identifier,
            'password' => $password,
        ]);

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
    }

    /**
     * this tests BC input to initWallet
     */
    public function testInitWalletOldSyntax() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;

        $this->shouldGetWalletV3($client, $identifier);

        /** @var Wallet $wallet */
        $wallet = $client->initWallet($identifier, $password);

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
    }

    /**
     * this test is the basis for WalletBaseTest::initWalletV1
     */
    public function testInitWalletV1() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        $password = self::WALLET_PASSWORD;

        $client->shouldReceive('getWallet')
            ->withArgs([$identifier])
            ->andReturn([
                'primary_mnemonic' => 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon achieve atom harvest help frame very curve razor muscle spawn',
                'wallet_version' => 'v1',
                'key_index' => 9999,
                'checksum' => 'n3yffrKj4xReaWjt5pdLoe7ZoBwe1UGTm8',
                'segwit' => false,
                'backup_public_key' => ['tpubD6NzVbkrYhZ4Wg9K1EjgX5F9rCi3nMjpMsyMZGyketABh8Y2Cfn92dfHAWEffZB3VJAZS2rd5iYTGfyiW32dEzWCnxubrDbbLHig3JGcvEZ','M'],
                'blocktrail_public_keys' => [
                    9999 => ['tpubD9q6vq9zdP3gbhpjs7n2TRvT7h4PeBhxg1Kv9jEc1XAss7429VenxvQTsJaZhzTk54gnsHRpgeeNMbm1QTag4Wf1QpQ3gy221GDuUCxgfeZ', 'M/9999\''],
                ],
                'primary_public_keys' => [
                    9999 => ['tpubDA5AEZ2jW8afwPeKGwNGb52ydoNVtYRTiPsxtyw7i6eJ2qBwzhXrR9mRV1AkBPkYzPMApdiiGPtJ6CetcYzZkWAHVWxk4hsZU9vc1XWS8aa', 'M/9999\''],
                ],
                'upgrade_key_index' => NULL,
            ])
            ->once();

        /** @var Wallet $wallet */
        $wallet = $client->initWallet([
            'identifier' => $identifier,
            'password' => $password,
        ]);

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
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Failed to decrypt secret with password
     */
    public function testBadPassword() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWallet($client, $identifier, "badpassword");
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Checksum [msDQeMojn8ooMv9FniuCkqjdyvXRUY86eR] does not match [n3yffrKj4xReaWjt5pdLoe7ZoBwe1UGTm8], most likely due to incorrect password
     */
    public function testBadPasswordV1() {
        $client = $this->mockSDK();

        $identifier = self::WALLET_IDENTIFIER;
        /** @var Wallet $wallet */
        /** @var BlocktrailSDK|Mock $client */
        list($wallet, $client) = $this->initWalletV1($client, $identifier, "badpassword");
    }
}
