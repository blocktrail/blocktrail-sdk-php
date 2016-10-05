<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKey;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\Exceptions\WalletDecryptException;
use Blocktrail\SDK\V3Crypt\Encryption;
use Blocktrail\SDK\V3Crypt\Mnemonic;

class WalletV3 extends Wallet
{

    /**
     * @var BufferInterface
     */
    protected $encryptedPrimarySeed;

    /**
     * @var BufferInterface
     */
    protected $encryptedSecret;

    /**
     * @var BufferInterface
     */
    protected $secret = null;

    /**
     * @var BufferInterface
     */
    protected $primarySeed = null;

    /**
     * @param BlocktrailSDKInterface $sdk        SDK instance used to do requests
     * @param string                 $identifier identifier of the wallet
     * @param BufferInterface        $encryptedPrimarySeed
     * @param BufferInterface        $encryptedSecret
     * @param BIP32Key[]             $primaryPublicKeys
     * @param BIP32Key               $backupPublicKey
     * @param BIP32Key[]             $blocktrailPublicKeys
     * @param int                    $keyIndex
     * @param string                 $network
     * @param bool                   $testnet
     * @param string                 $checksum
     */
    public function __construct(BlocktrailSDKInterface $sdk, $identifier, BufferInterface $encryptedPrimarySeed, BufferInterface $encryptedSecret, $primaryPublicKeys, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $network, $testnet, $checksum)
    {
        $this->encryptedPrimarySeed = $encryptedPrimarySeed;
        $this->encryptedSecret = $encryptedSecret;

        parent::__construct($sdk, $identifier, $primaryPublicKeys, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $network, $testnet, $checksum);
    }

    /**
     * unlock wallet so it can be used for payments
     *
     * @param          $options ['primary_private_key' => key] OR ['passphrase' => pass]
     * @param callable $fn
     * @return bool
     * @throws \Exception
     */
    public function unlock($options, callable $fn = null)
    {
        // explode the wallet data
        $password = isset($options['passphrase']) ? $options['passphrase'] : (isset($options['password']) ? $options['password'] : null);

        $encryptedPrimarySeed = $this->encryptedPrimarySeed;
        $encryptedSecret = $this->encryptedSecret;
        $primaryPrivateKey = isset($options['primary_private_key']) ? $options['primary_private_key'] : null;

        if (isset($options['secret'])) {
            if (!$options['secret'] instanceof BufferInterface) {
                throw new \RuntimeException('Secret must be a BufferInterface');
            }
            $this->secret = $options['secret'];
        }
        if (isset($options['primary_seed'])) {
            if (!$options['primary_seed'] instanceof BufferInterface) {
                throw new \RuntimeException('Primary Seed must be a BufferInterface');
            }
            $this->primarySeed = $options['primary_seed'];
        }

        if (!$primaryPrivateKey) {
            if (!$password) {
                throw new \InvalidArgumentException("Can't init wallet with Primary Seed without a passphrase");
            } else if (!$encryptedSecret) {
                throw new \InvalidArgumentException("Can't init wallet with Primary Seed without a encrypted secret");
            }

            if (!$password instanceof Buffer) {
                throw new \RuntimeException('Password should be provided as a BufferInterface');
            }
        }

        if ($primaryPrivateKey) {
            if (!($primaryPrivateKey instanceof HierarchicalKey) && !($primaryPrivateKey instanceof BIP32Key)) {
                $primaryPrivateKey = HierarchicalKeyFactory::fromExtended($primaryPrivateKey);
            }
        } else {
            if (!($this->secret = Encryption::decrypt($encryptedSecret, $password))) {
                throw new WalletDecryptException("Failed to decrypt secret with password");
            }

            if (!($this->primarySeed = Encryption::decrypt($encryptedPrimarySeed, $this->secret))) {
                throw new WalletDecryptException("Failed to decrypt primary seed with secret");
            }

            // create BIP32 private key from the seed
            $primaryPrivateKey = HierarchicalKeyFactory::fromEntropy($this->primarySeed);
        }

        $this->primaryPrivateKey = $primaryPrivateKey instanceof BIP32Key ? $primaryPrivateKey : BIP32Key::create($primaryPrivateKey, "m");

        // create checksum (address) of the primary privatekey to compare to the stored checksum
        $checksum = $this->primaryPrivateKey->publicKey()->getAddress()->getAddress();
        if ($checksum != $this->checksum) {
            throw new \Exception("Checksum [{$checksum}] does not match [{$this->checksum}], most likely due to incorrect password");
        }

        $this->locked = false;

        // if the response suggests we should upgrade to a different blocktrail cosigning key then we should
        if (isset($data['upgrade_key_index'])) {
            $this->upgradeKeyIndex($data['upgrade_key_index']);
        }

        if ($fn) {
            $fn($this);
            $this->lock();
        }

        return true;
    }

    /**
     * lock the wallet (unsets primary private key)
     *
     * @return void
     */
    public function lock()
    {
        $this->primaryPrivateKey = null;
        $this->secret = null;
        $this->primarySeed = null;
        $this->locked = true;
    }

    /**
     * change password that is used to store data encrypted on server
     *
     * @param $newPassword
     * @return array backupInfo
     * @throws BlocktrailSDKException
     */
    public function passwordChange($newPassword)
    {
        if (!$newPassword instanceof BufferInterface) {
            throw new \RuntimeException('Password must be provided as a BufferInterface');
        }

        if ($this->locked) {
            throw new BlocktrailSDKException("Wallet needs to be unlocked to change password");
        }

        if (!$this->secret) {
            throw new BlocktrailSDKException("No secret");
        }

        $encryptedSecret = Encryption::encrypt($this->secret, $newPassword);

        $this->sdk->updateWallet($this->identifier, ['encrypted_secret' => base64_encode($encryptedSecret->getBinary())]);

        $this->encryptedSecret = $encryptedSecret;

        return [
            'encrypted_secret' => Mnemonic::encode($encryptedSecret),
        ];
    }
}
