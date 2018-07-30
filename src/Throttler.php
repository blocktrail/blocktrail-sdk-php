<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Crypto\EcAdapter\EcSerializer;
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PublicKeyInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Signature\CompactSignatureSerializerInterface;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKey;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\MessageSigner\MessageSigner;
use BitWasp\Bitcoin\MessageSigner\SignedMessage;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\CryptoJSAES\CryptoJSAES;
use Blocktrail\SDK\Address\AddressReaderBase;
use Blocktrail\SDK\Address\BitcoinAddressReader;
use Blocktrail\SDK\Address\BitcoinCashAddressReader;
use Blocktrail\SDK\Address\CashAddress;
use Blocktrail\SDK\Backend\BlocktrailConverter;
use Blocktrail\SDK\Backend\BtccomConverter;
use Blocktrail\SDK\Backend\ConverterInterface;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Connection\RestClient;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\Network\BitcoinCash;
use Blocktrail\SDK\Connection\RestClientInterface;
use Blocktrail\SDK\Network\BitcoinCashRegtest;
use Blocktrail\SDK\Network\BitcoinCashTestnet;
use Blocktrail\SDK\V3Crypt\Encryption;
use Blocktrail\SDK\V3Crypt\EncryptionMnemonic;
use Blocktrail\SDK\V3Crypt\KeyDerivation;

class Throttler {

    /**
     * @var float|null
     */
    private $lastTime = null;

    /**
     * interval to wait in seconds, can be float
     *
     * @var float
     */
    private $interval;

    public function __construct($interval) {
        $this->interval = $interval;
    }

    public function waitForThrottle() {
        if (!$this->lastTime) {
            $this->lastTime = \microtime(true);
            return;
        }

        $now = \microtime(true);
        $diff = $this->interval - ($now - $this->lastTime);

        echo "Throttle routine\n";
        echo "* interval is {$this->interval}\n";
        echo "* now is {$now}\n";
        echo "* lastTime is {$this->lastTime}\n";
        echo "* wait time should be $diff\n";

        if ($diff > 0) {
            $usleep = (int)ceil($diff * 1000 * 1000);
            echo "do sleep ($diff, $usleep)\n";
            usleep($usleep);
        }

        if ($this->lastTime) {
            echo "Real diff ".(microtime(true)-$this->lastTime).PHP_EOL;
        }
        $this->lastTime = \microtime(true);
    }

    private static $instances = [];

    public static function getInstance($key, $interval) {
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new Throttler($interval);
        }

        return self::$instances[$key];
    }
}
