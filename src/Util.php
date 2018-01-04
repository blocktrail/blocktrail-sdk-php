<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Network\NetworkFactory;

abstract class Util {

    /**
     * @param callable $fn
     * @param array $arr
     * @return bool
     */
    public static function all(callable $fn, array $arr) {
        $allvalues = array_map($fn, $arr);
        return count(array_unique($allvalues)) === 1 && end($allvalues) === true;
    }

    /**
     * Given a $network string and $testnet bool, this function
     * will apply the old method (conditionally prepending 't'
     * to the network if ($testnet).. but also some new special
     * cases which ignore the testnet setting.
     *
     * @param string $network
     * @param bool $testnet
     * @return array
     */
    public static function parseApiNetwork($network, $testnet) {
        $network = strtoupper($network);

        // deal with testnet special cases
        if (strlen($network) === 4 && $network[0] === 'T') {
            $testnet = true;
            $network = substr($network, 1);
        }

        if (strlen($network) === 3) {
            // work out apiNetwork
            $apiNetwork = $network;
            if ($testnet) {
                $apiNetwork = "t{$network}";
            }
        } else if ($network === "RBTC") {
            // Regtest is magic
            $apiNetwork = "rBTC";
            $testnet = true;
        } else {
            // Default to bitcoin if they make no sense.
            $apiNetwork = "BTC";
            $testnet = false;
        }

        return [$apiNetwork, $testnet];
    }

    /**
     * normalize network string
     *
     * @param string $network
     * @param bool $testnet
     * @return NetworkParams
     * @throws \Exception
     */
    public static function normalizeNetwork($network, $testnet) {
        $network = strtolower($network);

        switch (strtolower($network)) {
            case 'btc':
                $name = 'bitcoin';
                $params = NetworkFactory::bitcoin();
                break;

            case 'tbtc':
                $name = 'bitcoin';
                $params = NetworkFactory::bitcoinTestnet();
                $testnet = true;
                break;

            case 'bcc':
                $name = 'bitcoincash';
                $params = NetworkFactory::bitcoin();
                break;

            case 'tbcc':
                $name = 'bitcoincash';
                $params = NetworkFactory::bitcoinTestnet();
                $testnet = true;
                break;

            case 'rbtc':
                $name = 'bitcoin';
                $params = NetworkFactory::bitcoinRegtest();
                $testnet = true;
                break;

            default:
                throw new \Exception("Unknown network [{$network}]");
            // this comment silences a phpcs error.
        }

        return new NetworkParams($network, $name, $testnet, $params);
    }

    public static function arrayMapWithIndex(callable $fn, $arr) {
        $result = [];
        $assoc = null;
        foreach ($arr as $idx => $value) {
            list($newidx, $newvalue) = $fn($idx, $value);

            if ($assoc === null) {
                $assoc = $newidx !== null;
            }

            if ($newidx === null && $assoc || $newidx !== null && !$assoc) {
                throw new \Exception("Mix of non assoc and assoc keys");
            }

            if ($assoc) {
                $result[$newidx] = $newvalue;
            } else {
                $result[] = $newvalue;
            }
        }

        return $result;
    }
}
