<?php

namespace Blocktrail\SDK\Backend;

interface ConverterInterface {
    public function paginationParams($params);

    public function getUrlForBlock($blockHash);

    public function getUrlForTransaction($txId);

    public function getUrlForTransactions($txIds);

    public function getUrlForBlockTransaction($blockHash);

    public function getUrlForAddress($address);

    public function getUrlForAddressTransactions($address);

    public function getUrlForAddressUnspent($address);

    public function getUrlForAllBlocks();

    public function handleErros($data);

    public function convertBlock($oldData);

    public function convertBlocks($oldData);

    public function convertBlockTxs($oldData);

    public function convertTx($oldData, $rawTx);

    public function convertTxs($oldData);

    public function convertAddressTxs($oldData);

    public function convertAddress($oldData);

    public function convertAddressUnspentOutputs($oldData, $address);
}
