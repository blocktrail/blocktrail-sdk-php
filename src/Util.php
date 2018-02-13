<?php

namespace Blocktrail\SDK;

abstract class Util {
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
        } else if ($network === "RBCH") {
            // Regtest is magic
            $apiNetwork = "rBCH";
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
     * @param $network
     * @param $testnet
     * @return array
     * @throws \Exception
     */
    public static function normalizeNetwork($network, $testnet) {
        $regtest = false;
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
            case 'bcc':
            case 'bch':
            case 'bitcoincash':
                $network = 'bitcoincash';
                break;

            case 'tbch':
            case 'tbcc':
            case 'bitcoincash-testnet':
                $network = 'bitcoincash';
                $testnet = true;
                break;

            case 'rbtc':
            case 'bitcoin-regtest':
                $network = 'bitcoin';
                $testnet = true;
                $regtest = true;
                break;

            case 'rbch':
            case 'bitcoincash-regtest':
                $network = 'bitcoincash';
                $testnet = true;
                $regtest = true;
                break;

            default:
                throw new \Exception("Unknown network [{$network}]");
            // this comment silences a phpcs error.
        }

        return [$network, $testnet, $regtest];
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
