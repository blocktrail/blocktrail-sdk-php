<?php

namespace Blocktrail\SDK;

/**
 * Class Blocktrail contains constants for usage throughout the codebase
 *
 */
abstract class Blocktrail {

    const COIN = 100000000;
    const PRECISION = 8;
    const COIN_FORMAT = "%.8f";

    const EVENT_ADDRESS_TRANSACTIONS = 'address-transactions';
    const EVENT_BLOCK = 'block';

    const DUST = 546;
    const BASE_FEE = 10000;

    const SDK_VERSION = "1.3.1";
    const SDK_USER_AGENT = "blocktrail-sdk-php";
    const DEFAULT_TIME_ZONE = "UTC";

    const EXCEPTION_INVALID_CREDENTIALS = "Your credentials are incorrect.";
    const EXCEPTION_GENERIC_HTTP_ERROR = "An HTTP Error has occurred!";
    const EXCEPTION_GENERIC_SERVER_ERROR = "A Server Error has occurred!";
    const EXCEPTION_EMPTY_RESPONSE = "The HTTP Response was empty.";
    const EXCEPTION_UNKNOWN_ENDPOINT_SPECIFIC_ERROR = "The endpoint returned an unknown error.";
    const EXCEPTION_MISSING_ENDPOINT = "The endpoint you've tried to access does not exist. Check your URL.";
    const EXCEPTION_OBJECT_NOT_FOUND = "The object you've tried to access does not exist.";

    /**
     * @deprecated use BlocktrailSDK::toBTC instead
     *
     * convert a Satoshi value (int) to a BTC value (float)
     *
     * @param int       $satoshi
     * @return float
     */
    public static function toBTC($satoshi) {
        return BlocktrailSDK::toBTC($satoshi);
    }

    /**
     * @deprecated use BlocktrailSDK::toBTCString instead
     *
     * convert a Satoshi value (int) to a BTC value (float) and return it as a string
     *
     * @param int       $satoshi
     * @return string
     */
    public static function toBTCString($satoshi) {
        return BlocktrailSDK::toBTCString($satoshi);
    }

    /**
     * @deprecated use BlocktrailSDK::toSatoshi instead
     *
     * convert a BTC value (float) to a Satoshi value (int)
     *
     * @param float     $btc
     * @return int
     */
    public static function toSatoshi($btc) {
        return BlocktrailSDK::toSatoshi($btc);
    }
}
