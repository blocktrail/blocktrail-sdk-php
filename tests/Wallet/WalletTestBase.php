<?php

namespace Blocktrail\SDK\Tests\Wallet;

use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Script\ScriptType;
use Blocktrail\SDK\Address\CashAddress;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Tests\MockBlocktrailSDK;
use Blocktrail\SDK\Wallet;
use Blocktrail\SDK\WalletScript;
use Mockery\Mock;

class WalletTestBase extends \PHPUnit_Framework_TestCase {

    const WALLET_IDENTIFIER = 'mywallet';
    const WALLET_PASSWORD = 'mypassword';

    public function tearDown() {
        parent::tearDown();

        \Mockery::close();
    }

    /**
     * @param bool $bcash
     * @return BlocktrailSDK|Mock
     */
    protected function mockSDK($bcash = false) {
        $apiKey = getenv('BLOCKTRAIL_SDK_APIKEY') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APIKEY';
        $apiSecret = getenv('BLOCKTRAIL_SDK_APISECRET') ?: 'EXAMPLE_BLOCKTRAIL_SDK_PHP_APISECRET';
        if ($bcash) {
            $network = 'rBCH';
        } else {
            $network = 'rBTC';
        }
        $testnet = true;
        $apiVersion = 'v1';
        $apiEndpoint = null;

        $client = \Mockery::mock(MockBlocktrailSDK::class, [$apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint])->makePartial();

        $client->shouldReceive('feePerKB')
                ->andReturn([
                    Wallet::FEE_STRATEGY_HIGH_PRIORITY => 40000,
                    Wallet::FEE_STRATEGY_LOW_PRIORITY => 10000,
                    Wallet::FEE_STRATEGY_OPTIMAL => 20000,
                ]);

        // this makes debugging a lot easier
        if (\getenv('BLOCKTRAIL_VAR_DUMP_MOCK')) {
            foreach (['getWallet',
                     'sendTransaction',
                     'coinSelection',
                     'storeNewWalletV1',
                     'storeNewWalletV2',
                     'storeNewWalletV3',
                     '_getNewDerivation',
                     'upgradeKeyIndex',
                     'walletTransactions',
                     'setupWalletWebhook',
                     'deleteWalletWebhook',
                     'feePerKB'
                     ] as $method) {

                $client->shouldReceive($method)
                    ->withArgs(function () use($method) {
                        var_dump($method, \func_get_args());
                        return false;
                    });
            }
        }

        return $client;
    }

    /**
     * @param BlocktrailSDK|Mock $client
     * @param string             $identifier
     * @param string             $password
     * @param bool               $segwit
     * @param array              $options
     * @return array
     * @throws \Exception
     */
    protected function initWallet($client, $identifier = self::WALLET_IDENTIFIER, $password = self::WALLET_PASSWORD, $segwit = false, $options = []) {
        $this->shouldGetWalletV3($client, $identifier, $segwit);

        $options = array_merge([
            'identifier' => $identifier,
            'password' => $password,
        ], (array)$options);

        if ($password === null) {
            unset($options['password']);
        }

        /** @var Wallet $wallet */
        $wallet = $client->initWallet($options);

        return [$wallet, $client];
    }

    /**
     * @param BlocktrailSDK|Mock $client
     * @param                    $identifier
     * @param bool               $segwit
     */
    protected function shouldGetWalletV3($client, $identifier, $segwit = false) {
        $client->shouldReceive('getWallet')
            ->withArgs([$identifier])
            ->andReturn([
                'primary_mnemonic' => NULL,
                'encrypted_secret' => 'CgAAAAAAAAAAAAC4iAAAAAAAAAAAAAAAAAAAAAAAADOe7reb5jZH+CmNxEtRtYQzMp/cZPAigKzZWo9yYxOqD4smZbROEj5zn8aeek5ZfA==',
                'encrypted_primary_seed' => 'CgAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAHp305+tX2NFSGsQcEiJFpHFXg8L6GJyBt0Zzr7M+KsD5P1zH0PgehzhxdIJZzmHlA==',
                'wallet_version' => 'v3',
                'key_index' => 9999,
                'checksum' => 'mgGXvgCUxJMt1mf9vdzdyPaJjmaKVHAtkZ',
                'segwit' => $segwit,
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
    }

    /**
     * @param BlocktrailSDK|Mock $client
     * @param string             $identifier
     * @param string             $password
     * @param bool               $segwit
     * @param array              $options
     * @return array
     * @throws \Exception
     */
    protected function initWalletV1($client, $identifier = self::WALLET_IDENTIFIER, $password = self::WALLET_PASSWORD, $segwit = false, $options = []) {
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

        $options = array_merge([
            'identifier' => $identifier,
            'password' => $password,
        ], (array)$options);

        if ($password === null) {
            unset($options['password']);
        }

        /** @var Wallet $wallet */
        $wallet = $client->initWallet($options);

        return [$wallet, $client];
    }

    /**
     * @param Mock|BlocktrailSDK $client
     * @param string     $identifier
     * @param string     $pathInput
     * @param string     $pathResult
     * @throws \Exception
     */
    protected function shouldGetNewDerivation($client, $identifier, $pathInput, $pathResult) {
        switch (\strtoupper($pathResult)) {
            case "M/9999'/0/0":
                $res = [
                    'path' => "M/9999'/0/0",
                    'address' => '2MxYE3e7R2e1NBLDicBMXMy9FRUygeTyEGa',
                    'scriptpubkey' => 'a9143a0fbfa2f446af8d76ac7f174618e7448674606987',
                    'redeem_script' => '5221031cc2f421316d93aab290c82cd01ca53a19a82c6bd4285a68b6f12659626f834d210334eea53fc0b0a6261e7e31a71437924779c5460435ebf79428f204b3ef791e37210342a88cc5467c9846b86fc37ac97823aebd3d754470cf7786fe6605674366120153ae',
                    'witness_script' => null,
                ];
                break;
            case "M/9999'/0/1":
                $res = [
                    'path' => "M/9999'/0/1",
                    'address' => '2N38FErCcqDq1sqKAoQPkFtBKEosjTe4uaz',
                    'scriptpubkey' => 'a9146c5f63f72c26725c100bc6ab0156e0dd36ffc8a087',
                    'redeem_script' => '522102382362e09b4f9e15ba16397fc3b4c4caaf82da29819c5decf1f4d650bf088adf2102453a9ebff02859cb285987a2b14da82a05f87250bca5ba994e05dec4a6c7a6df21033dcb8536d7e0c6a3a556f07accd55262dd4f452d4ca702e100557be6c26b096453ae',
                    'witness_script' => null,
                ];
                break;
            case "M/9999'/1/0":
                $res = [
                    'path' => "M/9999'/1/0",
                    'address' => '2MtkfTCz3xYTzfU4LS4PZGtTaDgzRf1nZnT',
                    'scriptpubkey' => 'a914108971f33a5fd5a33df0df690592c25f45e5a97e87',
                    'redeem_script' => '522103df848467aa6f6ab3982dd20556d0c27dc187e7fb7a7d5c10c66e6d05389f7a572103e33daa795617e9075140e0db47a5a77371c0e9c8fe6571af24272c7f0a2130be2103e98c09c808f8934442897eebb396a3718d889186fcd5b7f075d5238acdad2b5853ae',
                    'witness_script' => null,
                ];
                break;
            case "M/9999'/2/0":
                $res = [
                    'path' => "M/9999'/2/0",
                    'address' => '2MtPQLhhzFNtaY59QQ9wt28a5qyQ2t8rnvd',
                    'scriptpubkey' => 'a9140c84184d6d00096482c1359ce0194376ad248d2287',
                    'redeem_script' => '00204679ecfef34eb556071e349442e74837d059cb42ef22c4946a2efbb0c9041e68',
                    'witness_script' => '522102381a4cf140c24080523b5e63082496b514e99657d3506444b7f77c121766353021028e2ec167fbdd100fcb95e22ce4b49d4733b1f291eef6b5f5748852c96aa9522721038ba8bf95fc3449c3b745232eeca5493fd3277b801e659482cc868ca6e55ce23b53ae',
                ];
                break;
            default:
                throw new \Exception("Unsupported path {$pathResult}");
        }

        $client->shouldReceive('_getNewDerivation')
            ->withArgs([$identifier, $pathInput])
            ->andReturn($res)
            ->once();
    }

    protected function checkP2sh(ScriptInterface $spk, ScriptInterface $rs) {
        $spkData = (new OutputClassifier())->decode($spk);
        $this->assertEquals(ScriptType::P2SH, $spkData->getType());

        $scriptHash = $rs->getScriptHash();
        $this->assertTrue($spkData->getSolution()->equals($scriptHash));
    }

    protected function checkP2wsh(ScriptInterface $wp, ScriptInterface $ws) {
        $spkData = (new OutputClassifier())->decode($wp);
        $this->assertEquals(ScriptType::P2WSH, $spkData->getType());

        $scriptHash = $ws->getWitnessScriptHash();
        $this->assertTrue($spkData->getSolution()->equals($scriptHash));
    }

    protected function isBase58Address(WalletScript $script) {

        try {
            $addr = $script->getAddress();
            $ok = $addr instanceof PayToPubKeyHashAddress || $addr instanceof ScriptHashAddress;
        } catch (\Exception $e) {
            $ok = false;
        }

        $this->assertTrue($ok, "address should be a base58 type");

    }

    protected function isCashAddress(WalletScript $script) {

        try {
            $addr = $script->getAddress();
            $ok = $addr instanceof CashAddress;
        } catch (\Exception $e) {
            $ok = false;
        }

        $this->assertTrue($ok, "address should be a cashaddr type");

    }

    /**
     * @param BlocktrailSDK|Mock $client
     * @param                    $identifier
     * @param int|array          $value
     * @param string|null        $spk
     * @param                    $utxos
     * @param bool               $lockUTXOs
     * @param bool               $allowZeroConf
     * @param string             $feeStrategy
     * @param null               $forceFee
     */
    protected function shouldCoinSelect(
        $client, $identifier,
        $value, $spk,
        $utxos,
        $lockUTXOs = true, $allowZeroConf = false, $feeStrategy = Wallet::FEE_STRATEGY_OPTIMAL, $forceFee = null
    ) {
        if ($spk === null && is_array($value)) {
            $pay = $value;
        } else {
            $pay = [['value' => $value, 'scriptPubKey' => $spk]];
        }

        $retUtxos = [];
        foreach ($utxos as $i => $utxo) {
            $retUtxos[$i] = array_merge([
                'hash' => '6e7ee567d430b29cf78026fdc7ca01b4333b7dc3fe03aa3b99d0aceee45877ec',
                'idx' => $i,
                'value' => 1000000000,
                'confirmations' => 10,
                'green' => false,
            ], $utxo);
        }

        $client->shouldReceive('coinSelection')
            ->withArgs([$identifier, $pay, $lockUTXOs, $allowZeroConf, $feeStrategy, $forceFee])
            ->andReturn([
                'utxos' => $retUtxos,
                'size' => 366,
                'fee' => 3660,
                'fees' => [
                    'low_priority' => 5000,
                    'optimal' => 10000,
                    'high_priority' => 12000,
                ],
                'change' => 1000000000 - array_sum(\array_column($pay, 'value')) - 3660,
                'lock_time' => 7.2,
            ])
            ->once();
    }
}
