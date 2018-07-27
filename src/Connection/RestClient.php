<?php

namespace Blocktrail\SDK\Connection;

use Blocktrail\SDK\Blocktrail;
use Blocktrail\SDK\Throttler;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use HttpSignatures\Context;

class RestClient extends BaseRestClient
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $curlOptions = [];

    /**
     * @var Guzzle
     */
    protected $guzzle;

    /**
     * @var Throttler
     */
    protected $throttler;

    /**
     * GuzzleRestClient constructor.
     * @param $apiEndpoint
     * @param $apiVersion
     * @param $apiKey
     * @param $apiSecret
     */
    public function __construct($apiEndpoint, $apiVersion, $apiKey, $apiSecret) {
        parent::__construct($apiEndpoint, $apiVersion, $apiKey, $apiSecret);
        $this->guzzle = $this->createGuzzleClient();
        if ($throttle = \getenv('BLOCKTRAIL_SDK_THROTTLE_BTCCOM')) {
            $throttle = (float)$throttle;
        } else {
            $throttle = 0.33;
        }

        $this->throttler = Throttler::getInstance($this->apiEndpoint, $throttle);
    }

    /**
     * @param array $options
     * @param array $curlOptions
     * @return Guzzle
     */
    protected function createGuzzleClient(array $options = [], array $curlOptions = []) {
        $options = $options + $this->options;
        $curlOptions = $curlOptions + $this->curlOptions;

        $context = new Context([
            'keys' => [$this->apiKey => $this->apiSecret],
            'algorithm' => 'hmac-sha256',
            'headers' => ['(request-target)', 'Content-MD5', 'Date'],
        ]);

        $curlHandler = new CurlHandler($curlOptions);
        $handler = HandlerStack::create($curlHandler);
//        $handler->push(GuzzleHttpSignatures::middlewareFromContext($context));

        return new Guzzle($options + array(
            'handler' => $handler,
            'base_uri' => $this->apiEndpoint,
            'headers' => array(
                'User-Agent' => Blocktrail::SDK_USER_AGENT . '/' . Blocktrail::SDK_VERSION,
            ),
            'http_errors' => false,
            'connect_timeout' => 3,
            'timeout' => 20.0, // tmp until we have a good matrix of all the requests and their expect min/max time
            'verify' => true,
            'proxy' => '',
            'debug' => false,
            'config' => array(),
            'auth' => '',
        ));
    }

    /**
     * @return Guzzle
     */
    public function getGuzzleClient() {
        return $this->guzzle;
    }

    /**
     * enable CURL debugging output
     *
     * @param   bool        $debug
     */
    public function setCurlDebugging($debug = true) {
        $this->options['debug'] = $debug;

        $this->guzzle = $this->createGuzzleClient();
    }


    /**
     * set cURL default option on Guzzle client
     * @param string    $key
     * @param bool      $value
     */
    public function setCurlDefaultOption($key, $value) {
        $this->curlOptions[$key] = $value;

        $this->guzzle = $this->createGuzzleClient();
    }

    /**
     * set the proxy config for Guzzle
     *
     * @param   $proxy
     */
    public function setProxy($proxy) {
        $this->options['proxy'] = $proxy;

        $this->guzzle = $this->createGuzzleClient();
    }

    /**
     * generic request executor
     *
     * @param   string          $method         GET, POST, PUT, DELETE
     * @param   string          $endpointUrl
     * @param   array           $queryString
     * @param   array|string    $body
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @param   string          $contentMD5Mode body or url
     * @param   float           $timeout        timeout in seconds
     * @return Response
     */
    public function request($method, $endpointUrl, $queryString = null, $body = null, $auth = null, $contentMD5Mode = null, $timeout = null) {
        $request = $this->buildRequest($method, $endpointUrl, $queryString, $body, $auth, $contentMD5Mode, $timeout);
        $this->throttler->waitForThrottle();
        $response = $this->guzzle->send($request, ['auth' => $auth, 'timeout' => $timeout]);

        return $this->responseHandler($response);
    }
}
