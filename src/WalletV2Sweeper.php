<?php

namespace Blocktrail\SDK;


use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;


use BitWasp\Buffertools\Buffer;
use Blocktrail\CryptoJSAES\CryptoJSAES;


use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

class WalletV2Sweeper extends WalletSweeper {

    /**
     * @param string              $encryptedPrimaryMnemonic
     * @param string              $encryptedSecretMnemonic
     * @param string              $backupSeed
     * @param string              $passphrase
     * @param array               $blocktrailPublicKeys
     * @param UnspentOutputFinder $utxoFinder
     * @param string              $network
     * @param bool                $testnet
     * @throws BlocktrailSDKException
     */
    public function __construct($encryptedPrimaryMnemonic, $encryptedSecretMnemonic, $backupSeed, $passphrase, array $blocktrailPublicKeys, UnspentOutputFinder $utxoFinder, $network = 'btc', $testnet = false) {
        // cleanup copy paste errors from mnemonics
        $encryptedPrimaryMnemonic = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($encryptedPrimaryMnemonic))));
        $encryptedSecretMnemonic = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($encryptedSecretMnemonic))));
        $backupSeed = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($backupSeed))));

        $bip39 = MnemonicFactory::bip39();

        if (!($secret = CryptoJSAES::decrypt(base64_encode($bip39->mnemonicToEntropy($encryptedSecretMnemonic)->getBinary()), $passphrase))) {
            throw new BlocktrailSDKException("Failed to decret password encrypted secret");
        }

        if (!($primarySeed = CryptoJSAES::decrypt(base64_encode($bip39->mnemonicToEntropy($encryptedPrimaryMnemonic)->getBinary()), $secret))) {
            throw new BlocktrailSDKException("failed to decrypt encrypted primary seed! (weird!)");
        }

        $backupSeed = $bip39->mnemonicToEntropy($backupSeed);

        parent::__construct(new Buffer($primarySeed), $backupSeed, $blocktrailPublicKeys, $utxoFinder, $network, $testnet);
    }
}
