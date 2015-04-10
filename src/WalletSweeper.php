<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BIP39\BIP39;
use BitWasp\BitcoinLib\BitcoinLib;
use BitWasp\BitcoinLib\RawTransaction;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Bitcoin\BIP32Path;

class WalletSweeper {

    /**
     * network to use - currently only supporting 'bitcoin'
     * @var string
     */
    protected $network;

    /**
     * using testnet or not
     * @var bool
     */
    protected $testnet;

    /**
     * backup private key
     * @var BIP32Key
     */
    protected $backupPrivateKey;

    /**
     * primary private key
     * @var BIP32Key
     */
    protected $primaryPrivateKey;

    /**
     * blocktrail public keys, mapped to their relevant keyIndex
     * @var
     */
    protected $blocktrailPublicKeys;

    /**
     * bitcoin client service used to initialise unspent output finder and then query bitcoin data
     * @var BlockchainDataServiceInterface
     */
    protected $bitcoinClient;

    /**
     * gets unspent outputs for addresses
     * @var UnspentOutputFinder
     */
    protected $utxoFinder;

    /**
     * holds wallet addresses, along with path, redeem script, and discovered unspent outputs
     * @var array
     */
    protected $sweepData;

    /**
     * process logging for debugging
     * @var bool
     */
    protected $debug = false;

    /**
     * @param                                $primaryMnemonic
     * @param                                $primaryPassphrase
     * @param                                $backupMnemonic
     * @param array                          $blocktrailPublicKeys
     * @param BlockchainDataServiceInterface $bitcoinClient
     * @param string                         $network
     * @param bool                           $testnet
     * @throws \Exception
     */
    public function __construct($primaryMnemonic, $primaryPassphrase, $backupMnemonic, array $blocktrailPublicKeys, BlockchainDataServiceInterface $bitcoinClient, $network = 'btc', $testnet = false) {
        // normalize network and set bitcoinlib to the right magic-bytes
        list($this->network, $this->testnet) = $this->normalizeNetwork($network, $testnet);
        BitcoinLib::setMagicByteDefaults($this->network . ($this->testnet ? '-testnet' : ''));

        //create BIP32 keys for the Blocktrail public keys
        foreach ($blocktrailPublicKeys as $blocktrailKey) {
            $this->blocktrailPublicKeys[$blocktrailKey['keyIndex']] = BIP32Key::create($blocktrailKey['pubkey'], $blocktrailKey['path']);
        }

        //set the unspent output finder, using the given bitcoin data service provider
        $this->bitcoinClient = $bitcoinClient;
        $this->utxoFinder = new UnspentOutputFinder($this->bitcoinClient);

        // cleanup copy paste errors from mnemonics
        $primaryMnemonic = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($primaryMnemonic))));
        $backupMnemonic = str_replace("  ", " ", str_replace("\r\n", " ", str_replace("\n", " ", trim($backupMnemonic))));

        // convert the primary and backup mnemonics to seeds (using BIP39), then create private keys (using BIP32)
        $primarySeed = BIP39::mnemonicToSeedHex($primaryMnemonic, $primaryPassphrase);
        $backupSeed = BIP39::mnemonicToSeedHex($backupMnemonic, "");
        $this->primaryPrivateKey = BIP32Key::create(BIP32::master_key($primarySeed, $this->network, $this->testnet));
        $this->backupPrivateKey = BIP32Key::create(BIP32::master_key($backupSeed, $this->network, $this->testnet));
    }

    /**
     * enable debug info logging (just to console)
     */
    public function enableLogging() {
        $this->debug = true;
        $this->utxoFinder->enableLogging();
    }

    /**
     * disable debug info logging
     */
    public function disableLogging() {
        $this->debug = false;
        $this->utxoFinder->disableLogging();
    }


    /**
     * normalize network string
     *
     * @param $network
     * @param $testnet
     * @return array
     * @throws \Exception
     */
    protected function normalizeNetwork($network, $testnet) {
        switch (strtolower($network)) {
            case 'btc':
            case 'bitcoin':
                $network = 'bitcoin';

                break;

            case 'tbtc':
            case 'bitcoin-testnet':
                $network = 'bitcoin';
                $testnet = true;

                break;

            default:
                throw new \Exception("Unknown network [{$network}]");
        }

        return [$network, $testnet];
    }


    /**
     * generate multisig address for given path
     *
     * @param $path
     * @return array
     * @throws \Exception
     */
    protected function createAddress($path) {
        $path = BIP32Path::path($path);

        //build public keys for this path
        $primaryPubKey = $this->primaryPrivateKey->buildKey($path)->publicKey();
        $backupPubKey = $this->backupPrivateKey->buildKey($path->unhardenedPath())->publicKey();
        $blocktrailPubKey = $this->getBlocktrailPublicKey($path)->buildKey($path)->publicKey();

        //sort the keys
        $multisigKeys = BlocktrailSDK::sortMultisigKeys([$primaryPubKey, $backupPubKey, $blocktrailPubKey]);

        //create the multisig address
        $multiSig = RawTransaction::create_multisig(2, $multisigKeys);

        return [$multiSig['address'], $multiSig['redeemScript']];
    }

    /**
     * create a batch of multisig addresses
     *
     * @param $start
     * @param $count
     * @param $keyIndex
     * @return array
     */
    protected function createBatchAddresses($start, $count, $keyIndex) {
        $addresses = array();

        for ($i = 0; $i < $count; $i++) {
            //create a path subsequent address
            $path = (string)WalletPath::create($keyIndex, $_chain = 0, $start+$i)->path()->publicPath();
            list($address, $redeem) = $this->createAddress($path);
            $addresses[$address] = array(
                //'address' => $address,
                'redeem' => $redeem,
                'path' => $path
            );

            if ($this->debug) {
                echo ".";
            }
        }

        return $addresses;
    }


    /**
     * gets the blocktrail pub key for the given path from the stored array of pub keys
     *
     * @param string|BIP32Path  $path
     * @return BIP32Key
     * @throws \Exception
     */
    protected function getBlocktrailPublicKey($path) {
        $path = BIP32Path::path($path);

        $keyIndex = str_replace("'", "", $path[1]);

        if (!isset($this->blocktrailPublicKeys[$keyIndex])) {
            throw new \Exception("No blocktrail publickey for key index [{$keyIndex}]");
        }

        return $this->blocktrailPublicKeys[$keyIndex];
    }

    /**
     * discover funds in the wallet
     *
     * @param int $increment    how many addresses to scan at a time
     * @return array
     */
    public function discoverWalletFunds($increment = 200) {
        $totalBalance = 0;
        $totalUTXOs = 0;
        $totalAddressesGenerated = 0;

        $addressUTXOs = array();    //addresses and their utxos, paths and redeem scripts

        //for each blocktrail pub key, do fund discovery on batches of addresses
        foreach ($this->blocktrailPublicKeys as $keyIndex => $blocktrailPubKey) {
            $i = 0;
            do {
                if ($this->debug) {
                    echo "\ngenerating $increment addresses using blocktrail key index $keyIndex\n";
                }
                $addresses = $this->createBatchAddresses($i, $increment, $keyIndex);
                $totalAddressesGenerated += count($addresses);

                if ($this->debug) {
                    echo "\nstarting fund discovery for $increment addresses";
                }

                //get the unspent outputs for this batch of addresses
                $utxos = $this->utxoFinder->getUTXOs(array_keys($addresses));
                //save the address utxos, along with relevant path and redeem script
                foreach ($utxos as $address => $outputs) {
                    $addressUTXOs[$address] = array(
                        'path' =>  $addresses[$address]['path'],
                        'redeem' =>  $addresses[$address]['redeem'],
                        'utxos' =>  $outputs,
                    );
                    $totalUTXOs += count($outputs);

                    //add up the total utxo value for all addresses
                    $totalBalance = array_reduce($outputs, function ($carry, $output) {
                        return $carry += $output['value'];
                    }, $totalBalance);

                    if ($this->debug) {
                        echo "\nfound ".count($outputs)." unspent outputs in address $address";
                    }
                }

                //increment for next batch
                $i += $increment;
            } while (count($utxos) > 0);
        }

        if ($this->debug) {
            echo "\nfinished fund discovery: $totalBalance Satoshi (in $totalUTXOs outputs) found when searching $totalAddressesGenerated addresses";
        }

        $this->sweepData = ['utxos' => $addressUTXOs, 'count' => $totalUTXOs, 'balance' => $totalBalance, 'addressesSearched' => $totalAddressesGenerated];
        return $this->sweepData;
    }

    /**
     * sweep the wallet of all funds and send to a single address
     *
     * @param string    $destinationAddress     address to receive found funds
     * @param int       $sweepBatchSize         number of addresses to search at a time
     * @return array                            returns signed transaction for sending, success status, and signature count
     * @throws \Exception
     */
    public function sweepWallet($destinationAddress, $sweepBatchSize = 200) {
        if ($this->debug) {
            echo "\nstarting wallet sweeping to address $destinationAddress";
        }

        //do wallet fund discovery
        if (!isset($this->sweepData)) {
            $this->discoverWalletFunds($sweepBatchSize);
        }

        if ($this->sweepData['balance'] === 0) {
            //no funds found
            throw new \Exception("No funds found after searching through {$this->sweepData['addressesSearched']} addresses");
        }

        //create and sign the transaction
        $transaction = $this->createTransaction($destinationAddress);

        //return or send the transaction
        return $transaction;
    }

    /**
     * create a signed transaction sending all the found outputs to the given address
     *
     * @param $destinationAddress
     * @return array
     * @throws \Exception
     */
    protected function createTransaction($destinationAddress) {
        if ($this->debug) {
            echo "\nCreating transaction to address $destinationAddress";
        }

        // create raw transaction
        $inputs = [];
        foreach ($this->sweepData['utxos'] as $address => $data) {
            $inputs = array_merge(
                $inputs,
                array_map(function ($utxo) use ($address, $data) {
                    return [
                        'txid' => $utxo['hash'],
                        'vout' => $utxo['index'],
                        'scriptPubKey' => $utxo['script_hex'],
                        'value' => $utxo['value'],
                        'address' => $address,
                        'path' => $data['path'],
                        'redeemScript' => $data['redeem']
                    ];
                }, $data['utxos'])
            );
        }

        $outputs = [];
        $fee = Wallet::estimateFee($this->sweepData['count'], 1);
        $outputs[$destinationAddress] = $this->sweepData['balance'] - $fee;

        //create the raw transaction
        $rawTransaction = RawTransaction::create($inputs, $outputs);
        if (!$rawTransaction) {
            throw new \Exception("Failed to create raw transaction");
        }

        if ($this->debug) {
            echo "\nSigning transaction";
        }

        //sign the raw transaction
        $transaction = $this->signTransaction($rawTransaction, $inputs);
        if (!$transaction['sign_count']) {
            throw new \Exception("Failed to sign transaction");
        }

        return $transaction;
    }

    /**
     * signs a raw transaction
     *
     * @param $rawTransaction
     * @param $inputs
     * @return array
     */
    protected function signTransaction($rawTransaction, $inputs) {
        $wallet = [];
        $keys = [];
        $redeemScripts = [];

        foreach ($inputs as $input) {
            //create private keys for signing
            $path = BIP32Path::path($input['path'])->privatePath();
            $keys[] = $this->primaryPrivateKey->buildKey($path);
            $keys[] = $this->backupPrivateKey->buildKey($path->unhardenedPath());
            $redeemScripts[] = $input['redeemScript'];
        }

        //add the keys and redeem scripts to a wallet to sign the transaction with
        BIP32::bip32_keys_to_wallet($wallet, array_map(function (BIP32Key $key) {
            return $key->tuple();
        }, $keys));
        RawTransaction::redeem_scripts_to_wallet($wallet, $redeemScripts);

        return RawTransaction::sign($wallet, $rawTransaction, json_encode($inputs));
    }
}
