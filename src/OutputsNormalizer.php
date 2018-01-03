<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Network\Network;
use BitWasp\Bitcoin\Network\NetworkInterface;
use BitWasp\Bitcoin\Script\Opcodes;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\Address\AddressReaderBase;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

class OutputsNormalizer
{
    /**
     * @var AddressReaderBase
     */
    private $reader;

    /**
     * OutputsNormalizer constructor.
     * @param AddressReaderBase $reader
     */
    public function __construct(AddressReaderBase $reader) {
        $this->reader = $reader;
    }

    /**
     * @param array $output
     * @return array
     * @throws BlocktrailSDKException
     */
    protected function readArrayFormat(array $output, Network $network) {
        if (array_key_exists("scriptPubKey", $output) && array_key_exists("value", $output)) {
            return [
                "scriptPubKey" => $output['scriptPubKey'],
                "value" => $output['value'],
            ];
        } else if (array_key_exists("address", $output) && array_key_exists("value", $output)) {
            return $this->parseAddressOutput($output['address'], $output['value'], $network);
        } else {
            $keys = array_keys($output);
            if (count($keys) === 2 && count($output) === 2 && $keys[0] === 0 && $keys[1] === 1) {
                return $this->parseAddressOutput($output[0], $output[1], $network);
            } else {
                throw new BlocktrailSDKException("Invalid transaction output for numerically indexed list");
            }
        }
    }

    /**
     * @param $address
     * @param $value
     * @return array
     */
    private function parseAddressOutput($address, $value, $network) {
        if ($address === "opreturn") {
            $data = new Buffer($value);
            $scriptPubKey = ScriptFactory::sequence([Opcodes::OP_RETURN, $data]);
            return [
                "value" => 0,
                "scriptPubKey" => $scriptPubKey,
            ];
        }

        $object = $this->reader->fromString($address, $network);
        return [
            "value" => $value,
            "scriptPubKey" => $object->getScriptPubKey(),
        ];
    }

    /**
     * @param array $outputs
     * @param NetworkInterface|null $network
     * @return array
     * @throws BlocktrailSDKException
     */
    public function normalize(array $outputs, NetworkInterface $network = null) {
        $network = $network ?: Bitcoin::getNetwork();
        if (empty($outputs)) {
            return [];
        }

        $keys = array_keys($outputs);
        $newOutputs = [];
        if (is_int($keys[0])) {
            foreach ($outputs as $i => $output) {
                if (!is_int($i)) {
                    throw new BlocktrailSDKException("Encountered invalid index while traversing numerically indexed list");
                }
                if (!is_array($output)) {
                    throw new BlocktrailSDKException("Encountered invalid output while traversing numerically indexed list");
                }
                $newOutputs[] = $this->readArrayFormat($output, $network);
            }
        } else if (is_string($keys[0])) {
            foreach ($outputs as $address => $value) {
                if (!is_string($address)) {
                    throw new BlocktrailSDKException("Encountered invalid address while traversing address keyed list..");
                }

                $newOutputs[] = $this->parseAddressOutput($address, $value, $network);
            }
        }

        foreach ($newOutputs as &$newOutput) {
            if (fmod($newOutput['value'], 1)) {
                throw new BlocktrailSDKException("Value should be in Satoshis");
            }

            if (is_string($newOutput['scriptPubKey'])) {
                $newOutput['scriptPubKey'] = ScriptFactory::fromHex($newOutput['scriptPubKey']);
            }

            if (strlen($newOutput['scriptPubKey']->getBinary()) < 1) {
                throw new BlocktrailSDKException("Script cannot be empty");
            }

            if ($newOutput['scriptPubKey']->getBinary()[0] !== "\x6a") {
                if (!$newOutput['value']) {
                    throw new BlocktrailSDKException("Values should be non zero");
                } else if ($newOutput['value'] < Blocktrail::DUST) {
                    throw new BlocktrailSDKException("Values should be more than dust (" . Blocktrail::DUST . ")");
                }
            }
        }

        return $newOutputs;
    }
}
