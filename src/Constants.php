<?php

namespace BlockTrail\SDK;

/**
 * Class Constants
 *
 * @package BlockTrail\SDK
 */
abstract class Constants {
    const SDK_VERSION = "0.0.1";
    const SDK_USER_AGENT = "blocktrail-sdk-php";
    const DEFAULT_TIME_ZONE = "UTC";

    const EXCEPTION_INVALID_CREDENTIALS = "Your credentials are incorrect.";
    const EXCEPTION_GENERIC_HTTP_ERROR = "An HTTP Error has occurred!";
    const EXCEPTION_GENERIC_SERVER_ERROR = "A Server Error has occurred!";
    const EXCEPTION_EMPTY_RESPONSE = "The HTTP Response was empty.";
    const EXCEPTION_UNKNOWN_ENDPOINT_SPECIFIC_ERROR = "The endpoint returned an unknown error.";
    const EXCEPTION_MISSING_ENDPOINT = "The endpoint you've tried to access does not exist. Check your URL.";
    const EXCEPTION_OBJECT_NOT_FOUND = "The object you've tried to access does not exist.";
}
