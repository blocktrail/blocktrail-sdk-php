<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\V3Crypt\Encryption;
use Blocktrail\SDK\V3Crypt\EncryptionMnemonic;

class WalletV3Sweeper extends WalletSweeper
{
    /**
     * @param string              $encryptedPrimaryMnemonic
     * @param string              $encryptedSecretMnemonic
     * @param string              $backupMnemonic
     * @param BufferInterface     $passphrase
     * @param array               $blocktrailPublicKeys
     * @param UnspentOutputFinder $utxoFinder
     * @param string              $network
     * @param bool                $testnet
     * @throws BlocktrailSDKException
     */
    public function __construct($encryptedPrimaryMnemonic, $encryptedSecretMnemonic, $backupMnemonic, BufferInterface $passphrase, array $blocktrailPublicKeys, UnspentOutputFinder $utxoFinder, $network = 'btc', $testnet = false) {
        // cleanup copy paste errors from mnemonics
        $encryptedPrimaryMnemonic = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($encryptedPrimaryMnemonic))));
        $encryptedSecretMnemonic = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($encryptedSecretMnemonic))));
        $backupMnemonic = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($backupMnemonic))));

        if (!($secret = Encryption::decrypt(EncryptionMnemonic::decode($encryptedSecretMnemonic), $passphrase))) {
            throw new BlocktrailSDKException("Failed to decret password encrypted secret");
        }

        if (!($primarySeed = Encryption::decrypt(EncryptionMnemonic::decode($encryptedPrimaryMnemonic), $secret))) {
            throw new BlocktrailSDKException("failed to decrypt encrypted primary seed! (weird!)");
        }

        $backupMnemonic = MnemonicFactory::bip39()->mnemonicToEntropy($backupMnemonic);
        parent::__construct($primarySeed, $backupMnemonic, $blocktrailPublicKeys, $utxoFinder, $network, $testnet);
    }
}
