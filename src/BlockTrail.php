<?php

namespace BlockTrail\SDK;

/**
 * Class BlockTrail
 *
 * @package BlockTrail\SDK
 */
abstract class BlockTrail {
    const COIN = 100000000;
    const PRECISION = 8;
    const COIN_FORMAT = "%.8f";

    const SDK_VERSION = "1.0.2";
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
     * convert a Satoshi value (int) to a BTC value (float)
     *
     * @param int       $satoshi
     * @return float
     */
    public static function toBTC($satoshi) {
        return (float)bcdiv((int)(string)$satoshi, BlockTrail::COIN, BlockTrail::PRECISION);
    }

    /**
     * convert a Satoshi value (int) to a BTC value (float) and return it as a string

     * @param int       $satoshi
     * @return string
     */
    public static function toBTCString($satoshi) {
        return sprintf(BlockTrail::COIN_FORMAT, BlockTrail::toBTC($satoshi));
    }

    /**
     * convert a BTC value (float) to a Satoshi value (int)
     *
     * @param float     $btc
     * @return int
     */
    public static function toSatoshi($btc) {
        return (int)bcmul(sprintf("%.8f", (float)(string)$btc), BlockTrail::COIN, 0);
    }
}
