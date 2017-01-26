<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKey;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Exceptions\NotImplementedException;

class WalletV1 extends Wallet {

    /**
     * BIP39 Mnemonic for the master primary private key
     *
     * @var string
     */
    protected $primaryMnemonic;

    /**
     * @param BlocktrailSDKInterface        $sdk                        SDK instance used to do requests
     * @param string                        $identifier                 identifier of the wallet
     * @param string                        $primaryMnemonic
     * @param BIP32Key[]                    $primaryPublicKeys
     * @param BIP32Key                      $backupPublicKey            should be BIP32 master public key M/
     * @param BIP32Key[]                    $blocktrailPublicKeys
     * @param int                           $keyIndex
     * @param string                        $network
     * @param bool                          $testnet
     * @param string                        $checksum
     */
    public function __construct(BlocktrailSDKInterface $sdk, $identifier, $primaryMnemonic, array $primaryPublicKeys, $backupPublicKey, array $blocktrailPublicKeys, $keyIndex, $network, $testnet, $checksum) {
        $this->primaryMnemonic = $primaryMnemonic;

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
    public function unlock($options, callable $fn = null) {
        // explode the wallet data
        $password = isset($options['passphrase']) ? $options['passphrase'] : (isset($options['password']) ? $options['password'] : null);
        $primaryMnemonic = $this->primaryMnemonic;
        $primaryPrivateKey = isset($options['primary_private_key']) ? $options['primary_private_key'] : null;

        if ($primaryMnemonic && $primaryPrivateKey) {
            throw new \InvalidArgumentException("Can't specify Primary Mnemonic and Primary PrivateKey");
        }

        if (!$primaryMnemonic && !$primaryPrivateKey) {
            throw new \InvalidArgumentException("Can't init wallet without Primary Mnemonic or Primary PrivateKey");
        }

        if ($primaryMnemonic && !$password) {
            throw new \InvalidArgumentException("Can't init wallet with Primary Mnemonic without a passphrase");
        }

        if ($primaryPrivateKey) {
            if (!($primaryPrivateKey instanceof HierarchicalKey)) {
                $primaryPrivateKey = HierarchicalKeyFactory::fromExtended($primaryPrivateKey);
            }
        } else {
            // convert the mnemonic to a seed using BIP39 standard
            $primarySeed = (new Bip39SeedGenerator())->getSeed($primaryMnemonic, $password);
            // create BIP32 private key from the seed
            $primaryPrivateKey = HierarchicalKeyFactory::fromEntropy($primarySeed);
        }

        $this->primaryPrivateKey = BIP32Key::create($primaryPrivateKey, "m");

        // create checksum (address) of the primary privatekey to compare to the stored checksum
        $checksum = $this->primaryPrivateKey->key()->getPublicKey()->getAddress()->getAddress();
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
    }

    /**
     * lock the wallet (unsets primary private key)
     *
     * @return void
     */
    public function lock() {
        $this->primaryPrivateKey = null;
        $this->locked = true;
    }

    /**
     * change password that is used to store data encrypted on server
     *
     * @param $newPassword
     * @return array backupInfo
     * @throws NotImplementedException
     */
    public function passwordChange($newPassword) {
        throw new NotImplementedException();
    }
}
