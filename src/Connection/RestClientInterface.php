<?php

namespace Blocktrail\SDK\Connection;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

interface RestClientInterface
{

    /**
     * enable verbose errors
     *
     * @param   bool $verboseErrors
     */
    public function setVerboseErrors($verboseErrors = true);

    /**
     * @param   string $endpointUrl
     * @param   array $queryString
     * @param   string $auth http-signatures to enable http-signature signing
     * @param   float $timeout timeout in seconds
     * @return  Response
     */
    public function get($endpointUrl, $queryString = null, $auth = null, $timeout = null);

    /**
     * @param   string $endpointUrl
     * @param   null $queryString
     * @param   array|string $postData
     * @param   string $auth http-signatures to enable http-signature signing
     * @param   float $timeout timeout in seconds
     * @return  Response
     */
    public function post($endpointUrl, $queryString = null, $postData = '', $auth = null, $timeout = null);

    /**
     * @param   string $endpointUrl
     * @param   null $queryString
     * @param   array|string $putData
     * @param   string $auth http-signatures to enable http-signature signing
     * @param   float $timeout timeout in seconds
     * @return  Response
     */
    public function put($endpointUrl, $queryString = null, $putData = '', $auth = null, $timeout = null);

    /**
     * @param   string $endpointUrl
     * @param   null $queryString
     * @param   array|string $postData
     * @param   string $auth http-signatures to enable http-signature signing
     * @param   float $timeout timeout in seconds
     * @return  Response
     */
    public function delete($endpointUrl, $queryString = null, $postData = null, $auth = null, $timeout = null);

    /**
     * generic request executor
     *
     * @param   string $method GET, POST, PUT, DELETE
     * @param   string $endpointUrl
     * @param   array $queryString
     * @param   array|string $body
     * @param   string $auth http-signatures to enable http-signature signing
     * @param   string $contentMD5Mode body or url
     * @param   float $timeout timeout in seconds
     * @return Request
     */
    public function buildRequest($method, $endpointUrl, $queryString = null, $body = null, $auth = null, $contentMD5Mode = null, $timeout = null);

    /**
     * generic request executor
     *
     * @param   string $method GET, POST, PUT, DELETE
     * @param   string $endpointUrl
     * @param   array $queryString
     * @param   array|string $body
     * @param   string $auth http-signatures to enable http-signature signing
     * @param   string $contentMD5Mode body or url
     * @param   float $timeout timeout in seconds
     * @return Response
     */
    public function request($method, $endpointUrl, $queryString = null, $body = null, $auth = null, $contentMD5Mode = null, $timeout = null);

    public function responseHandler(ResponseInterface $responseObj);
}
