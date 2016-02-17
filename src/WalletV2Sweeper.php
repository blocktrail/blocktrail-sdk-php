<?php

namespace Blocktrail\SDK;


use BitWasp\BitcoinLib\BIP39\BIP39;


use Blocktrail\CryptoJSAES\CryptoJSAES;


use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

class WalletV2Sweeper extends WalletSweeper {

    /**
     * @param string              $encryptedPrimarySeed
     * @param string              $passwordEncryptedSecret
     * @param string              $backupSeed
     * @param string              $passphrase
     * @param array               $blocktrailPublicKeys
     * @param UnspentOutputFinder $utxoFinder
     * @param string              $network
     * @param bool                $testnet
     * @throws BlocktrailSDKException
     */
    public function __construct($encryptedPrimarySeed, $passwordEncryptedSecret, $backupSeed, $passphrase, array $blocktrailPublicKeys, UnspentOutputFinder $utxoFinder, $network = 'btc', $testnet = false) {
        // cleanup copy paste errors from mnemonics
        $encryptedPrimarySeed = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($encryptedPrimarySeed))));
        $passwordEncryptedSecret = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($passwordEncryptedSecret))));
        $backupSeed = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($backupSeed))));

        if (!($secret = CryptoJSAES::decrypt(base64_encode(hex2bin(BIP39::mnemonicToEntropy($passwordEncryptedSecret))), $passphrase))) {
            throw new BlocktrailSDKException("Failed to decret password encrypted secret");
        }

        if (!($primarySeed = CryptoJSAES::decrypt(base64_encode(hex2bin(BIP39::mnemonicToEntropy($encryptedPrimarySeed))), $secret))) {
            throw new BlocktrailSDKException("failed to decrypt encrypted primary seed! (weird!)");
        }

        $backupSeed = BIP39::mnemonicToEntropy($backupSeed);

        parent::__construct(bin2hex($primarySeed), $backupSeed, $blocktrailPublicKeys, $utxoFinder, $network, $testnet);
    }
}
