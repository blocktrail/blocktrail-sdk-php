<?php

namespace Blocktrail\SDK\Backend;

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Script\Classifier\OutputClassifier;
use BitWasp\Bitcoin\Script\ScriptType;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionInput;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Connection\Exceptions\EndpointSpecificError;

class BtccomConverter implements ConverterInterface {
    public function paginationParams($params) {
        if (!$params) {
            return $params;
        }

        if (isset($params['limit'])) {
            $params['pagesize'] = $params['limit'];
        }

        return $params;
    }

    public function getUrlForBlock($blockHash) {
        return "block/{$blockHash}";
    }

    public function getUrlForTransaction($txId) {
        return "tx/{$txId}?verbose=3";
    }

    public function getUrlForTransactions($txIds) {
        return "tx/" . implode(",", $txIds) . "?verbose=3";
    }

    public function getUrlForBlockTransaction($blockHash) {
        return "block/{$blockHash}/tx?verbose=3";
    }

    public function getUrlForAddress($address) {
        return "address/{$address}";
    }

    public function getUrlForAddressTransactions($address) {
        return "address/{$address}/tx?verbose=3";
    }

    public function getUrlForAddressUnspent($address) {
        return "address/{$address}/unspent";
    }

    public function getUrlForBatchAddressesUnspent($addresses) {
        return "multi-address/" . \implode(",", $addresses) . "/unspent";
    }

    public function getUrlForAllBlocks() {
        return "block/list";
    }

    public function handleErros($data) {
        if (isset($data['err_no']) && $data['err_no'] > 0) {
            throw new EndpointSpecificError($data['err_msg'], $data['err_no']);
        }
    }

    public function convertBlock($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        $this->handleErros($data);

        return $this->_convertBlock($data['data']);
    }

    private function _convertBlock($blockData) {
        return [
            "hash" => $blockData['hash'],
            "version" => (string)$blockData['version'],
            "height" => $blockData['height'],
            "block_time" => self::utcTimestampToISODateStr($blockData['timestamp']),
            "arrival_time" => self::utcTimestampToISODateStr($blockData['timestamp']),
            "bits" => $blockData['bits'],
            "nonce" => $blockData['nonce'],
            "merkleroot" => $blockData['mrkl_root'],
            "prev_block" => $blockData['prev_block_hash'],
            "next_block" => $blockData['next_block_hash'],
            "byte_size" => $blockData['stripped_size'],
            "difficulty" => (int)\floor($blockData['difficulty']),
            "transactions" => $blockData['tx_count'],
            "reward_block" => $blockData['reward_block'],
            "reward_fees" => $blockData['reward_fees'],
            "created_at" => $blockData['created_at'],
            "confirmations" => $blockData['confirmations'],
            "is_orphan" => $blockData['is_orphan'],
            "is_sw_block" => $blockData['is_sw_block'],
            "weight" => $blockData['weight'],
            "miningpool_name" => isset($blockData['miningpool_name']) ? $blockData['miningpool_name'] : null,
            "miningpool_url" => isset($blockData['miningpool_url']) ? $blockData['miningpool_url'] : null,
            "miningpool_slug" => isset($blockData['miningpool_slug']) ? $blockData['miningpool_slug'] : null
        ];
    }

    public function convertBlocks($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        $this->handleErros($data);

        return [
            'data' => $data['data']['list'],
            'current_page' => $data['data']['page'],
            'per_page' => $data['data']['pagesize'],
            'total' => $data['data']['total_count'],
        ];
    }

    public function convertBlockTxs($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        $this->handleErros($data);

        $list = array_map(function ($tx) {
            return $this->_convertTx($tx);
        }, $data['data']['list']);

        return [
            'data' => $list,
            'current_page' => $data['data']['page'],
            'per_page' => $data['data']['pagesize'],
            'total' => $data['data']['total_count'],
        ];
    }

    public function convertTx($res, $rawTx) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        $this->handleErros($data);
        return $this->_convertTx($data['data']);
    }

    public function convertTxs($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        $this->handleErros($data);

        return ['data' => array_map(function ($tx) {
            return $this->_convertTx($tx);
        }, $data['data'])];
    }

    public function convertAddressTxs($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        $this->handleErros($data);

        $list = array_map(function ($tx) {
            return $this->_convertTx($tx);
        }, $data['data']['list']);

        return [
            'data' => $list,
            'current_page' => $data['data']['page'],
            'per_page' => $data['data']['pagesize'],
            'total' => $data['data']['total_count'],
        ];
    }

    public function convertAddress($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        $this->handleErros($data);

        return [
            'address' => $data['data']['address'],
            'hash160' => self::getBase58AddressHash160($data['data']['address']),
            'balance' => $data['data']['balance'],
            'received' => $data['data']['received'],
            'sent' => $data['data']['sent'],
            'transactions' => $data['data']['tx_count'],
            'utxos' => $data['data']['unspent_tx_count'],
            'unconfirmed_received' => $data['data']['unconfirmed_received'],
            'unconfirmed_sent' => $data['data']['unconfirmed_sent'],
            'unconfirmed_transactions' => $data['data']['unconfirmed_tx_count'],
            'first_tx' => $data['data']['first_tx'],
            'last_tx' => $data['data']['last_tx'],
        ];
    }

    public function convertAddressUnspentOutputs($res, $address) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        $this->handleErros($data);

        $spk = AddressFactory::fromString($address)->getScriptPubKey();
        $type = (new OutputClassifier())->classify($spk);
        $scriptAsm = $spk->getScriptParser()->getHumanReadable();
        $scriptHex = $spk->getHex();

        $list = array_map(function ($tx) use ($address, $type, $scriptAsm, $scriptHex) {
            return $this->_convertUtxo($tx, $address, $type, $scriptAsm, $scriptHex);
        }, $data['data']['list']);

        return [
            'data' => $list,
            'current_page' => $data['data']['page'],
            'per_page' => $data['data']['pagesize'],
            'total' => $data['data']['total_count'],
        ];
    }

    public function convertBatchAddressesUnspentOutputs($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        $this->handleErros($data);

        $list = [];

        foreach ($data['data'] as $row) {
            if (!$row) {
                continue;
            }

            $spk = AddressFactory::fromString($row['address'])->getScriptPubKey();
            $type = (new OutputClassifier())->classify($spk);
            $scriptAsm = $spk->getScriptParser()->getHumanReadable();
            $scriptHex = $spk->getHex();

            foreach ($row['list'] as $utxo) {
                $list[] = $this->_convertUtxo($utxo, $row['address'], $type, $scriptAsm, $scriptHex);
            }
        }

        return [
            'data' => $list,
            'current_page' => null,
            'per_page' => null,
            'total' => count($list),
        ];
    }

    private function _convertUtxo($utxo, $address, $type, $scriptAsm, $scriptHex) {
        return [
            'hash' => $utxo['tx_hash'],
            'confirmations' => $utxo['confirmations'],
            'value' => $utxo['value'],
            'index' => $utxo['tx_output_n'],
            'address' => $address,
            'type' => $type,
            'script' => $scriptAsm,
            'script_hex' => $scriptHex,
        ];
    }

    private function _convertTx($tx) {
        $data = [];
        $data['size'] = $tx['vsize'];
        $data['hash'] = $tx['hash'];
        $data['block_height'] = $tx['block_height'];
        $data['block_time'] =
        $data['time'] = self::utcTimestampToISODateStr($tx['block_time']);
        $data['block_hash'] = isset($tx['block_hash']) ? $tx['block_hash'] : null;
        $data['confirmations'] = $tx['confirmations'];
        $data['is_coinbase'] = $tx['is_coinbase'];

        if ($data['is_coinbase']) {
            $totalInputValue = $tx['outputs'][0]['value'] - $tx['fee'];
        } else {
            $totalInputValue = $tx['inputs_value'];
        }

        $data['total_input_value'] = $totalInputValue;
        $data['total_output_value'] = array_reduce($tx['outputs'], function ($total, $output) {
            return $total + $output['value'];
        }, 0);
        $data['total_fee'] = $tx['fee'];
        $data['inputs'] = [];
        $data['outputs'] = [];
        $data['opt_in_rbf'] = false;

        foreach ($tx['inputs'] as $inputIdx => $input) {
            if ($input['sequence'] < TransactionInput::SEQUENCE_FINAL - 1) {
                $data['opt_in_rbf'] = true;
            }

            if ($data['is_coinbase'] && $input['prev_position'] === -1 &&
                $input['prev_tx_hash'] === "0000000000000000000000000000000000000000000000000000000000000000") {
                $scriptType = "coinbase";
                $inputTxid = null;
                $inputValue = $totalInputValue;
                $outpointIdx = 0;
            } else {
                $scriptType = $input['prev_type'];
                $inputValue = $input['prev_value'];
                $inputTxid = $input['prev_tx_hash'];
                $outpointIdx = $input['prev_position'];
            }

            $data['inputs'][] = [
                'index' => (int)$inputIdx,
                'output_hash' => $inputTxid,
                'output_index' => $outpointIdx,
                'value' => $inputValue,
                'sequence' => $input['sequence'],
                'address' => self::flattenAddresses($input['prev_addresses'], $scriptType),
                'type' => self::convertBtccomOutputScriptType($scriptType),
                'script_signature' => $input['script_hex'],
            ];
        }


        foreach ($tx['outputs'] as $outIdx => $output) {
            $data['outputs'][] = [
                'index' => (int)$outIdx,
                'value' => $output['value'],
                'address' => self::flattenAddresses($output['addresses'], $output['type']),
                'type' => self::convertBtccomOutputScriptType($output['type']),
                'script' => self::prettifyAsm($output['script_asm']),
                'script_hex' => $output['script_hex'],
                'spent_hash' => $output['spent_by_tx'],
                'spent_index' => $output['spent_by_tx_position'],
            ];
        }

        $data['size'] = $tx['size'];
        $data['is_double_spend'] = $tx['is_double_spend'];

        $data['lock_time_timestamp'] = null;
        $data['lock_time_block_height'] = null;
        if ($tx['lock_time']) {
            if ($tx['lock_time'] < 5000000) {
                $data['lock_time_block_height'] = $tx['lock_time'];
            } else {
                $data['lock_time_timestamp'] = $tx['lock_time'];
            }
        }

        // Extra fields from Btc.com
        $data['is_sw_tx'] = $tx['is_sw_tx'];
        $data['weight'] = $tx['weight'];
        $data['witness_hash'] = $tx['witness_hash'];
        $data['lock_time'] = $tx['lock_time'];
        $data['sigops'] = $tx['sigops'];
        $data['version'] = $tx['version'];

        return $data;
    }

    protected static function flattenAddresses($addresses, $type = null) {
        if ($type && in_array($type, ["P2WSH_V0", "P2WPKH"])) {
            return null;
        }

        if (!$addresses) {
            return null;
        } else if (count($addresses) === 1) {
            return $addresses[0];
        } else {
            return $addresses;
        }
    }

    protected static function convertBtccomOutputScriptType($scriptType) {
        switch ($scriptType) {
            case "P2PKH_PUBKEY":
                return "pubkey";
            case "P2PKH":
                return "pubkeyhash";
            case "P2SH":
                return "scripthash";
            case "P2WSH_V0":
                return "unknown";
            case "P2WPKH":
                return "unknown";
            case "NULL_DATA":
                return "op_return";
            case "coinbase":
                return "coinbase";
            default:
                throw new \Exception("Not implemented yet, script type: {$scriptType}");
        }
    }

    protected static function convertBitwaspScriptType($scriptType) {
        switch ($scriptType) {
            case ScriptType::P2PK:
                return "pubkey";
            case ScriptType::P2PKH:
                return "pubkeyhash";
            case ScriptType::NULLDATA:
                return "op_return";
            case ScriptType::P2SH:
                return "scripthash";
            case ScriptType::P2WSH:
                return "witnessscripthash";
            case ScriptType::P2WKH:
                return "witnesspubkeyhash";
            case ScriptType::MULTISIG:
            case ScriptType::WITNESS_COINBASE_COMMITMENT:
            case ScriptType::NONSTANDARD:
                return "unknown";
            default:
                throw new \Exception("Not implemented yet, script type: {$scriptType}");
        }
    }

    protected static function prettifyAsm($asm) {
        if (!$asm) {
            return $asm;
        }

        return preg_replace("/^0 /", "OP_0 ", $asm);
    }

    protected static function utcTimestampToISODateStr($time) {
        return (new \DateTime("@{$time}"))->format(\DATE_ISO8601);
    }

    protected static function getBase58AddressHash160($addr) {
        try {
            return \strtoupper(AddressFactory::fromString($addr)->getHash()->getHex());
        } catch (\Exception $e) {
            return null;
        }
    }
}
