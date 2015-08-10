<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BIP39\BIP39;
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
     * @param array[string, string]         $primaryPublicKeys
     * @param array[string, string]         $backupPublicKey            should be BIP32 master public key M/
     * @param array[array[string, string]]  $blocktrailPublicKeys
     * @param int                           $keyIndex
     * @param string                        $network
     * @param bool                          $testnet
     * @param string                        $checksum
     */
    public function __construct(BlocktrailSDKInterface $sdk, $identifier, $primaryMnemonic, $primaryPublicKeys, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $network, $testnet, $checksum) {
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
            throw new \InvalidArgumentException("Can't init wallet with Primary Mnemonic or Primary PrivateKey");
        }

        if ($primaryMnemonic && !$password) {
            throw new \InvalidArgumentException("Can't init wallet with Primary Mnemonic without a passphrase");
        }

        if ($primaryPrivateKey) {
            if (is_string($primaryPrivateKey)) {
                $primaryPrivateKey = [$primaryPrivateKey, "m"];
            }
        } else {
            // convert the mnemonic to a seed using BIP39 standard
            $primarySeed = BIP39::mnemonicToSeedHex($primaryMnemonic, $password);
            // create BIP32 private key from the seed
            $primaryPrivateKey = BIP32::master_key($primarySeed, $this->network, $this->testnet);
        }

        $this->primaryPrivateKey = BIP32Key::create($primaryPrivateKey);

        // create checksum (address) of the primary privatekey to compare to the stored checksum
        $checksum = BIP32::key_to_address($primaryPrivateKey[0]);
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
