<?PHP

namespace BlockTrail\SDK\Connection;

use BlockTrail\SDK\Connection\Exceptions\EndpointSpecificError;
use BlockTrail\SDK\Connection\Exceptions\GenericServerError;
use BlockTrail\SDK\Connection\Exceptions\ObjectNotFound;
use BlockTrail\SDK\Connection\Exceptions\UnknownEndpointSpecificError;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Post\PostBodyInterface;
use GuzzleHttp\Stream\Stream;
use HttpSignatures\Context;
use HttpSignatures\GuzzleHttp\RequestSubscriber;

use BlockTrail\SDK\BlockTrail;

use BlockTrail\SDK\Connection\Exceptions\EmptyResponse;
use BlockTrail\SDK\Connection\Exceptions\InvalidCredentials;
use BlockTrail\SDK\Connection\Exceptions\MissingEndpoint;
use BlockTrail\SDK\Connection\Exceptions\GenericHTTPError;

/**
 * Class RestClient
 *
 * @package BlockTrail\SDK\Connection
 */
class RestClient {

    protected $guzzle;

    protected $apiKey;

    public function __construct($apiEndpoint, $apiVersion, $apiKey, $apiSecret) {
        $this->guzzle = new Guzzle(array(
            'base_url' => $apiEndpoint,
            'defaults' => array(
                'headers' => array(
                    'User-Agent' => BlockTrail::SDK_USER_AGENT . '/' . BlockTrail::SDK_VERSION
                ),
                'exceptions' => false,
                'connect_timeout' => 3,
                'verify' => true,
                'proxy' => '',
                'debug' => false,
                'config' => array(),
                'auth' => '',
            ),
        ));

        $this->apiKey = $apiKey;

        $this->guzzle->getEmitter()->attach(new RequestSubscriber(
            new Context([
                'keys' => [$apiKey => $apiSecret],
                'algorithm' => 'hmac-sha256',
                'headers' => ['(request-target)', 'Content-MD5', 'Date'],
            ])
        ));
    }

    /**
     * enable CURL debugging output
     *
     * @param   bool        $debug
     */
    public function setCurlDebugging($debug = true) {
        $this->guzzle->setDefaultOption('debug', $debug);
    }

    /**
     * set the proxy config for Guzzle
     *
     * @param   $proxy
     */
    public function setProxy($proxy) {
        $this->guzzle->setDefaultOption('proxy', $proxy);
    }

    /**
     * @param   string      $endpointUrl
     * @param   array       $queryString
     * @param   string      $auth                   http-signatures to enable http-signature signing
     * @return  Response
     */
    public function get($endpointUrl, $queryString = null, $auth = null) {
        $request = $this->guzzle->createRequest('GET', $endpointUrl);

        if ($queryString) {
            $query = $request->getQuery();
            $query->replace($queryString);
        }

        if (!$request->getQuery()->get('api_key')) {
            $request->getQuery()->set('api_key', $this->apiKey);
        }

        if (!$request->hasHeader('Date')) {
            $request->setHeader('Date', $this->getRFC1123DateString());
        }

        //for GET requests, MD5 the request URI (excludes domain, includes query strings)
        $qs = (string)$request->getQuery();
        $request->setHeader('Content-MD5', md5($request->getPath() . ($qs ? "?{$qs}" : "")));

        if ($auth) {
            $request->getConfig()['auth'] = $auth;
        }

        $response = $this->guzzle->send($request);

        return $this->responseHandler($response);
    }

    /**
     * @param   string      $endpointUrl
     * @param   array|null  $postData
     * @param   string      $auth                   http-signatures to enable http-signature signing
     * @return  Response
     */
    public function post($endpointUrl, $postData, $auth = null) {
        $request = $this->guzzle->createRequest('POST', $endpointUrl);

        if (!$request->getQuery()->get('api_key')) {
            $request->getQuery()->set('api_key', $this->apiKey);
        }

        if (!$request->hasHeader('Date')) {
            $request->setHeader('Date', $this->getRFC1123DateString());
        }

        if (!$request->hasHeader('Content-Type')) {
            $request->setHeader('Content-Type', 'application/json');
        }

        $postBody = json_encode($postData);
        $request->setBody(Stream::factory($postBody));
        $request->setHeader('Content-MD5', md5((string)$postBody));

        if ($auth) {
            $request->getConfig()['auth'] = $auth;
        }

        $response = $this->guzzle->send($request);

        return $this->responseHandler($response);
    }

    /**
     * @param   string      $endpointUrl
     * @param   string      $auth                   http-signatures to enable http-signature signing
     * @return  Response
     */
    public function delete($endpointUrl, $auth = null) {
        $request = $this->guzzle->createRequest('DELETE', $endpointUrl);

        if (!$request->getQuery()->get('api_key')) {
            $request->getQuery()->set('api_key', $this->apiKey);
        }

        if (!$request->hasHeader('Date')) {
            $request->setHeader('Date', $this->getRFC1123DateString());
        }

        //for GET requests, MD5 the request URI (excludes domain, includes query strings)
        $qs = (string)$request->getQuery();
        $request->setHeader('Content-MD5', md5($request->getPath() . ($qs ? "?{$qs}" : "")));

        if ($auth) {
            $request->getConfig()['auth'] = $auth;
        }

        $response = $this->guzzle->send($request);

        return $this->responseHandler($response);
    }

    /**
     * @param   string      $endpointUrl
     * @param   array       $putData
     * @param   string      $auth                   http-signatures to enable http-signature signing
     * @return  Response
     */
    public function put($endpointUrl, $putData, $auth = null) {
        $request = $this->guzzle->createRequest('PUT', $endpointUrl);

        if (!$request->getQuery()->get('api_key')) {
            $request->getQuery()->set('api_key', $this->apiKey);
        }

        if (!$request->hasHeader('Date')) {
            $request->setHeader('Date', $this->getRFC1123DateString());
        }

        if (!$request->hasHeader('Content-Type')) {
            $request->setHeader('Content-Type', 'application/json');
        }

        $putBody = json_encode($putData);
        $request->setBody(Stream::factory($putBody));
        $request->setHeader('Content-MD5', md5((string)$putBody));

        if ($auth) {
            $request->getConfig()['auth'] = $auth;
        }

        $response = $this->guzzle->send($request);

        return $this->responseHandler($response);
    }

    public function responseHandler(ResponseInterface $responseObj) {
        $httpResponseCode = (int)$responseObj->getStatusCode();
        $httpResponsePhrase = (string)$responseObj->getReasonPhrase();
        $body = $responseObj->getBody();

        if ($httpResponseCode == 200) {
            if (!$body) {
                throw new EmptyResponse(BlockTrail::EXCEPTION_EMPTY_RESPONSE, $httpResponseCode);
            }

            $result = new Response($httpResponseCode, $body);

            return $result;
        } elseif ($httpResponseCode == 400 || $httpResponseCode == 403) {
            $data = json_decode($body, true);

            if ($data && isset($data['msg'], $data['code'])) {
                throw new EndpointSpecificError($data['msg'], $data['code']);
            } else {
                throw new UnknownEndpointSpecificError(BlockTrail::EXCEPTION_UNKNOWN_ENDPOINT_SPECIFIC_ERROR);
            }
        } elseif ($httpResponseCode == 401) {
            throw new InvalidCredentials(BlockTrail::EXCEPTION_INVALID_CREDENTIALS, $httpResponseCode);
        } elseif ($httpResponseCode == 404) {
            if ($httpResponsePhrase == "Endpoint Not Found") {
                throw new MissingEndpoint(BlockTrail::EXCEPTION_MISSING_ENDPOINT, $httpResponseCode);
            } else {
                throw new ObjectNotFound(BlockTrail::EXCEPTION_OBJECT_NOT_FOUND, $httpResponseCode);
            }
        } elseif ($httpResponseCode == 500) {
            throw new GenericServerError(BlockTrail::EXCEPTION_GENERIC_SERVER_ERROR . "\nServer Response: " . $body, $httpResponseCode);
        } else {
            throw new GenericHTTPError(BlockTrail::EXCEPTION_GENERIC_HTTP_ERROR . "\nServer Response: " . $body, $httpResponseCode);
        }
    }

    /**
     * Returns curent fate time in RFC1123 format, using UTC time zone
     *
     * @return  string
     */
    private function getRFC1123DateString() {
        $date = new \DateTime(null, new \DateTimeZone("UTC"));
        return str_replace("+0000", "GMT", $date->format(\DateTime::RFC1123));
    }
}
