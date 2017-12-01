<?php

namespace Blocktrail\SDK\Backend;

use Blocktrail\SDK\BlocktrailSDK;

class BlocktrailConverter implements ConverterInterface {
    public function paginationParams($params) {
        return $params;
    }

    public function getUrlForBlock($blockHash) {
        return "block/{$blockHash}";
    }

    public function getUrlForTransaction($txId) {
        return "transaction/{$txId}";
    }

    public function getUrlForTransactions($txIds) {
        return "transactions/" . implode(",", $txIds);
    }

    public function getUrlForBlockTransaction($blockHash) {
        return "block/{$blockHash}/transactions";
    }

    public function getUrlForAddress($address) {
        return "address/{$address}";
    }

    public function getUrlForAddressTransactions($address) {
        return "address/{$address}/transactions";
    }

    public function getUrlForAddressUnspent($address) {
        return "address/{$address}/unspent-outputs";
    }

    public function getUrlForAllBlocks() {
        return "all-blocks";
    }

    public function handleErros($data) {
        throw new \Exception("Not implemented");
    }

    public function convertBlock($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        return $data;
    }

    public function convertBlocks($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        return $data;
    }

    public function convertBlockTxs($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        return $data;
    }

    public function convertTx($res, $rawTx) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        return $data;
    }

    public function convertTxs($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        return $data;
    }

    public function convertAddressTxs($res) {

        $data = BlocktrailSDK::jsonDecode($res, true);
        return $data;
    }

    public function convertAddress($res) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        return $data;
    }

    public function convertAddressUnspentOutputs($res, $address) {
        $data = BlocktrailSDK::jsonDecode($res, true);
        return $data;
    }
}
