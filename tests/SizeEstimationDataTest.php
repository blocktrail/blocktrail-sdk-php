<?php

namespace Blocktrail\SDK\Tests;


use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use Blocktrail\SDK\SizeEstimation;
use Blocktrail\SDK\UTXO;

class SizeEstimationDataTest extends BlocktrailTestCase
{
    public function testP2shMultisig2Of3() {
        $hex = "010000000140b454eadd581e2065311e9e4c1e22ebd404f7e8b5bbe3c8d5033e2c922a7fd300000000fdfe0000483045022100dc0eb9ebb44e21a2334b1a9936ff2d3e6f85b6a8b0bae2462640ad574d5653570220591365f8795c68b788d3949e9d6a984dae2f79d49bf3b0b184edf9f67673252401483045022100aac67f935a52fd961b404dd4d273600924f74498062e237fec3ef50416440e3d02204fcf8deaee86c5a95c8c8c235bb252a5d4d304b9bd3e01d9c7098fae3c9172cf014c69522102ef6587d4850890bd42e7779365ed2baeec17824b84a285894ec090edf0b604e4210322f35f79c76036a68746d8e3804b9097a6575ae6d7fc9dfb99c06280b656dfc12103f73d0f5d4f8919a74d4b0cc72fc0ef5ee9eaf759d3731319294491883d97881b53aeffffffff027f0702000000000017a91409e249cd56381853743834cf7c367093f4f8497087a08601000000000017a9148c299479cf35311569d8c1450a85de22d206ab498700000000";

        $txId = "4de2b03b46c8c1ab5e582ea3ab4f1b055c91798ffd8e9cbe05010c99bc914947";
        $network = NetworkFactory::bitcoin();
        $utxo = new UTXO(
            "d37f2a922c3e03d5c8e3bbb5e8f704d4eb221e4c9e1e3165201e58ddea54b440",
            0,
            316400,
            AddressFactory::fromString("3Fzr53M54N8nWwTPt7btU4pcXQ3kNUUtrx", $network),
            ScriptFactory::fromHex("a9148c299479cf35311569d8c1450a85de22d206ab4987"),
            null,
            ScriptFactory::fromHex("522102ef6587d4850890bd42e7779365ed2baeec17824b84a285894ec090edf0b604e4210322f35f79c76036a68746d8e3804b9097a6575ae6d7fc9dfb99c06280b656dfc12103f73d0f5d4f8919a74d4b0cc72fc0ef5ee9eaf759d3731319294491883d97881b53ae"),
            null
        );

        $client = $this->setupBlocktrailSDK();
        $tx = $client->transaction($txId);
        $utxos = [$utxo];

        $estimate = SizeEstimation::estimateVsize($utxos, $tx['outputs']);
        print_r($estimate);
    }
}
