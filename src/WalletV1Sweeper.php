<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;

class WalletV1Sweeper extends WalletSweeper {

    /**
     * @param string              $primaryMnemonic
     * @param string              $primaryPassphrase
     * @param array               $backupMnemonic
     * @param array               $blocktrailPublicKeys
     * @param UnspentOutputFinder $unspentOutputFinder
     * @param string              $network
     * @param bool                $testnet
     */
    public function __construct($primaryMnemonic, $primaryPassphrase, $backupMnemonic, array $blocktrailPublicKeys, UnspentOutputFinder $unspentOutputFinder, $network = 'btc', $testnet = false) {
        // cleanup copy paste errors from mnemonics
        $primaryMnemonic = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($primaryMnemonic))));
        $backupMnemonic = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($backupMnemonic))));

        // convert the primary and backup mnemonics to seeds (using BIP39), then create private keys (using BIP32)
        $primarySeed = (new Bip39SeedGenerator())->getSeed($primaryMnemonic, $primaryPassphrase);
        $backupSeed = (new Bip39SeedGenerator())->getSeed($backupMnemonic, "");

        parent::__construct($primarySeed, $backupSeed, $blocktrailPublicKeys, $unspentOutputFinder, $network, $testnet);
    }
}
