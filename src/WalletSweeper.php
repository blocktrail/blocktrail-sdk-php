<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Script\P2shScript;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Factory\SignData;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Bitcoin\BIP32Path;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

abstract class WalletSweeper {

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
     * @param BufferInterface     $primarySeed
     * @param BufferInterface     $backupSeed
     * @param array               $blocktrailPublicKeys =
     * @param UnspentOutputFinder $utxoFinder
     * @param string              $network
     * @param bool                $testnet
     * @throws \Exception
     */
    public function __construct(BufferInterface $primarySeed, BufferInterface $backupSeed, array $blocktrailPublicKeys, UnspentOutputFinder $utxoFinder, $network = 'btc', $testnet = false) {
        // normalize network and set bitcoinlib to the right magic-bytes
        list($this->network, $this->testnet) = $this->normalizeNetwork($network, $testnet);

        assert($this->network == "bitcoin");
        Bitcoin::setNetwork($this->testnet ? NetworkFactory::bitcoinTestnet() : NetworkFactory::bitcoin());

        //create BIP32 keys for the Blocktrail public keys
        foreach ($blocktrailPublicKeys as $blocktrailKey) {
            $this->blocktrailPublicKeys[$blocktrailKey['keyIndex']] = BlocktrailSDK::normalizeBIP32Key([$blocktrailKey['pubkey'], $blocktrailKey['path']]);
        }

        $this->utxoFinder = $utxoFinder;

        $this->primaryPrivateKey = BIP32Key::create(HierarchicalKeyFactory::fromEntropy($primarySeed), "m");
        $this->backupPrivateKey = BIP32Key::create(HierarchicalKeyFactory::fromEntropy($backupSeed), "m");
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
     * @param string|BIP32Path $path
     * @return array[string, ScriptInterface]                 [address, redeemScript]
     * @throws \Exception
     */
    protected function createAddress($path) {
        $path = BIP32Path::path($path)->publicPath();

        $redeemScript = ScriptFactory::scriptPubKey()->multisig(2, BlocktrailSDK::sortMultisigKeys([
            $this->primaryPrivateKey->buildKey($path)->publicKey(),
            $this->backupPrivateKey->buildKey($path->unhardenedPath())->publicKey(),
            $this->getBlocktrailPublicKey($path)->buildKey($path)->publicKey()
        ]), false);

        return [(new P2shScript($redeemScript))->getAddress()->getAddress(), $redeemScript];
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
     * @param int $increment how many addresses to scan at a time
     * @return array
     * @throws BlocktrailSDKException
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
                foreach ($utxos as $utxo) {
                    if (!isset($utxo['address'], $utxo['value'])) {
                        throw new BlocktrailSDKException("Missing data");
                    }

                    $address = $utxo['address'];

                    if (!isset($addressUTXOs[$address])) {
                        $addressUTXOs[$address] = [
                            'path' =>  $addresses[$address]['path'],
                            'redeem' =>  $addresses[$address]['redeem'],
                            'utxos' =>  [],
                        ];
                    }

                    $addressUTXOs[$address]['utxos'][] = $utxo;
                    $totalUTXOs ++;

                    //add up the total utxo value for all addresses
                    $totalBalance += $utxo['value'];
                }

                if ($this->debug) {
                    echo "\nfound ".count($utxos)." unspent outputs";
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
     * @return string                           HEX string of signed transaction
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
        return $transaction->getHex();
    }

    /**
     * @param $destinationAddress
     * @return TransactionInterface
     */
    protected function createTransaction($destinationAddress) {
        $txb = new TxBuilder();

        $signInfo = [];
        $utxos = [];
        foreach ($this->sweepData['utxos'] as $address => $data) {
            foreach ($data['utxos'] as $utxo) {
                $utxo = new UTXO(
                    $utxo['hash'],
                    $utxo['index'],
                    $utxo['value'],
                    AddressFactory::fromString($address),
                    ScriptFactory::fromHex($utxo['script_hex']),
                    $data['path'],
                    $data['redeem']
                );

                $utxos[] = $utxo;
                $signInfo[] = new SignInfo($utxo->path, $utxo->redeemScript, new TransactionOutput($utxo->value, $utxo->scriptPubKey));
            }
        }

        foreach ($utxos as $utxo) {
            $txb->spendOutPoint(new OutPoint(Buffer::hex($utxo->hash), $utxo->index), $utxo->scriptPubKey);
        }

        $fee = Wallet::estimateFee($this->sweepData['count'], 1);

        $txb->payToAddress($this->sweepData['balance'] - $fee, AddressFactory::fromString($destinationAddress));


        if ($this->debug) {
            echo "\nSigning transaction";
        }

        $tx = $this->signTransaction($txb->get(), $signInfo);

        return $tx;
    }

    /**
     * sign a raw transaction with the private keys that we have
     *
     * @param TransactionInterface $tx
     * @param SignInfo[]  $signInfo
     * @return TransactionInterface
     * @throws \Exception
     */
    protected function signTransaction(TransactionInterface $tx, array $signInfo) {
        $signer = new Signer($tx, Bitcoin::getEcAdapter());

        assert(Util::all(function ($signInfo) {
            return $signInfo instanceof SignInfo;
        }, $signInfo), '$signInfo should be SignInfo[]');

        foreach ($signInfo as $idx => $info) {
            $path = BIP32Path::path($info->path)->privatePath();
            $redeemScript = $info->redeemScript;
            $output = $info->output;

            $key = $this->primaryPrivateKey->buildKey($path)->key()->getPrivateKey();
            $backupKey = $this->backupPrivateKey->buildKey($path->unhardenedPath())->key()->getPrivateKey();

            $signData = new SignData();
            $signData->p2sh($redeemScript);
            $input = $signer->input($idx, $output, $signData);

            $input->sign($key);
            $input->sign($backupKey);
        }

        return $signer->get();
    }
}
