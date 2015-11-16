<?php

namespace Blocktrail\SDK\Connection;

use Psr\Http\Message\StreamInterface;

/**
 * Class Response
 *
 */
class Response {

    /**
     * @var int
     */
    private $responseCode;

    /**
     * @var StreamInterface
     */
    private $responseBody;

    public function __construct($responseCode, StreamInterface $responseBody) {
        $this->responseCode = $responseCode;
        $this->responseBody = $responseBody;
    }

    public function statusCode() {
        return $this->responseCode;
    }

    public function body() {
        return (string)$this->responseBody;
    }
}
