<?php

namespace Blocktrail\SDK\Tests\RestClient;


use Blocktrail\SDK\Connection\BaseRestClient;

use HttpSignatures\Context;

class MockRestClient extends BaseRestClient
{
    /**
     * @var array
     */
    private $requestHandler = [];

    /**
     * @param callable $handler
     */
    public function expectRequest(callable $handler)
    {
        $this->requestHandler[] = $handler;
    }

    /**
     * @param string $method
     * @param string $endpointUrl
     * @param null $queryString
     * @param null $body
     * @param null $auth
     * @param null $contentMD5Mode
     * @param null $timeout
     * @return \Blocktrail\SDK\Connection\Response
     */
    public function request($method, $endpointUrl, $queryString = null, $body = null, $auth = null, $contentMD5Mode = null, $timeout = null)
    {
        if (count($this->requestHandler) < 1) {
            throw new \RuntimeException("No handlers left in MockRestClient");
        }

        $request = $this->buildRequest($method, $endpointUrl, $queryString, $body, $auth, $contentMD5Mode, $timeout);

        if ($auth) {
            $context = new Context([
                'keys' => [$this->apiKey => $this->apiSecret],
                'algorithm' => 'hmac-sha256',
                'headers' => ['(request-target)', 'Content-MD5', 'Date'],
            ]);

            $request = $context->signer()->sign($request);
        }

        $handler = array_shift($this->requestHandler);
        list ($statusCode, $headers, $body) = $handler($request);

        if (!is_string($body)) {
            $body = json_encode($body);
        }

        $response = new \GuzzleHttp\Psr7\Response($statusCode, $headers, $body);
        return $this->responseHandler($response);
    }
}
