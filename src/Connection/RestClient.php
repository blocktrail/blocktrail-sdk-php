<?PHP

namespace Blocktrail\SDK\Connection;

use Blocktrail\SDK\Connection\Exceptions\BannedIP;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use HttpSignatures\Context;
use Blocktrail\SDK\Blocktrail;
use Blocktrail\SDK\Connection\Exceptions\EndpointSpecificError;
use Blocktrail\SDK\Connection\Exceptions\GenericServerError;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\Connection\Exceptions\UnknownEndpointSpecificError;
use Blocktrail\SDK\Connection\Exceptions\EmptyResponse;
use Blocktrail\SDK\Connection\Exceptions\InvalidCredentials;
use Blocktrail\SDK\Connection\Exceptions\MissingEndpoint;
use Blocktrail\SDK\Connection\Exceptions\GenericHTTPError;
use HttpSignatures\GuzzleHttpSignatures;
use Psr\Http\Message\ResponseInterface;

/**
 * Class RestClient
 *
 */
class RestClient {

    const AUTH_HTTP_SIG = 'http-signatures';

    /**
     * @var Guzzle
     */
    protected $guzzle;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $apiEndpoint;

    /**
     * @var string
     */
    protected $apiVersion;

    /**
     * @var string
     */
    protected $apiSecret;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $curlOptions = [];

    /**
     * @var bool
     */
    protected $verboseErrors = false;

    public function __construct($apiEndpoint, $apiVersion, $apiKey, $apiSecret) {
        $this->apiEndpoint = $apiEndpoint;
        $this->apiVersion = $apiVersion;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;

        $this->guzzle = $this->createGuzzleClient();
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
        $handler->push(GuzzleHttpSignatures::middlewareFromContext($context));

        return new Guzzle($options + array(
            'handler' => $handler,
            'base_uri' => $this->apiEndpoint,
            'headers' => array(
                'User-Agent' => Blocktrail::SDK_USER_AGENT . '/' . Blocktrail::SDK_VERSION
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
     * enable verbose errors
     *
     * @param   bool        $verboseErrors
     */
    public function setVerboseErrors($verboseErrors = true) {
        $this->verboseErrors = $verboseErrors;
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
     * @param   string          $endpointUrl
     * @param   array           $queryString
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @param   float           $timeout        timeout in seconds
     * @return  Response
     */
    public function get($endpointUrl, $queryString = null, $auth = null, $timeout = null) {
        return $this->request('GET', $endpointUrl, $queryString, null, $auth, null, $timeout);
    }

    /**
     * @param   string          $endpointUrl
     * @param   null            $queryString
     * @param   array|string    $postData
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @param   float           $timeout        timeout in seconds
     * @return  Response
     */
    public function post($endpointUrl, $queryString = null, $postData = '', $auth = null, $timeout = null) {
        return $this->request('POST', $endpointUrl, $queryString, $postData, $auth, null, $timeout);
    }

    /**
     * @param   string          $endpointUrl
     * @param   null            $queryString
     * @param   array|string    $putData
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @param   float           $timeout        timeout in seconds
     * @return  Response
     */
    public function put($endpointUrl, $queryString = null, $putData = '', $auth = null, $timeout = null) {
        return $this->request('PUT', $endpointUrl, $queryString, $putData, $auth, null, $timeout);
    }

    /**
     * @param   string          $endpointUrl
     * @param   null            $queryString
     * @param   array|string    $postData
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @param   float           $timeout        timeout in seconds
     * @return  Response
     */
    public function delete($endpointUrl, $queryString = null, $postData = null, $auth = null, $timeout = null) {
        return $this->request('DELETE', $endpointUrl, $queryString, $postData, $auth, 'url', $timeout);
    }

    private static $replaceQuery = ['=' => '%3D', '&' => '%26'];
    public static function hasQueryValue(Uri $uri, $key) {
        $current = $uri->getQuery();
        $key = strtr($key, self::$replaceQuery);

        if (!$current) {
            $result = [];
        } else {
            $result = [];
            foreach (explode('&', $current) as $part) {
                if (explode('=', $part)[0] === $key) {
                    return true;
                };
            }
        }

        return false;
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
     * @return Request
     */
    public function buildRequest($method, $endpointUrl, $queryString = null, $body = null, $auth = null, $contentMD5Mode = null, $timeout = null) {
        if (is_null($contentMD5Mode)) {
            $contentMD5Mode = !is_null($body) ? 'body' : 'url';
        }

        $request = new Request($method, $endpointUrl);
        $uri = $request->getUri();

        if ($queryString) {
            foreach ($queryString as $k => $v) {
                $uri = Uri::withQueryValue($uri, $k, $v);
            }
        }

        if (!self::hasQueryValue($uri, 'api_key')) {
            $uri = Uri::withQueryValue($uri, 'api_key', $this->apiKey);
        }

        // normalize the query string the same way the server expects it
        /** @var Request $request */
        $request = $request->withUri($uri->withQuery(\Symfony\Component\HttpFoundation\Request::normalizeQueryString($uri->getQuery())));

        if (!$request->hasHeader('Date')) {
            $request = $request->withHeader('Date', $this->getRFC1123DateString());
        }

        if (!is_null($body)) {
            if (!$request->hasHeader('Content-Type')) {
                $request = $request->withHeader('Content-Type', 'application/json');
            }

            if (!is_string($body)) {
                $body = json_encode($body);
            }
            $request = $request->withBody(\GuzzleHttp\Psr7\stream_for($body));
        }

        // for GET/DELETE requests, MD5 the request URI (excludes domain, includes query strings)
        if ($contentMD5Mode == 'body') {
            $request = $request->withHeader('Content-MD5', md5((string)$body));
        } else {
            $request = $request->withHeader('Content-MD5', md5($request->getRequestTarget()));
        }

        return $request;
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
        $request = $this->buildRequest($method, $endpointUrl, $queryString, $body, $contentMD5Mode);
        $response = $this->guzzle->send($request, ['auth' => $auth, 'timeout' => $timeout]);

        return $this->responseHandler($response);
    }

    public function responseHandler(ResponseInterface $responseObj) {
        $httpResponseCode = (int)$responseObj->getStatusCode();
        $httpResponsePhrase = (string)$responseObj->getReasonPhrase();
        $body = $responseObj->getBody();

        if ($httpResponseCode == 200) {
            if (!$body) {
                throw new EmptyResponse(Blocktrail::EXCEPTION_EMPTY_RESPONSE, $httpResponseCode);
            }

            $result = new Response($httpResponseCode, $body);

            return $result;
        } elseif ($httpResponseCode == 400 || $httpResponseCode == 403) {
            $data = json_decode($body, true);

            if ($data && isset($data['msg'], $data['code'])) {
                throw new EndpointSpecificError(!is_string($data['msg']) ? json_encode($data['msg']) : $data['msg'], $data['code']);
            } else {
                if (preg_match("/^banned( IP)? \[(.+)\]\n?$/", $body, $m)) {
                    throw new BannedIP($m[2]);
                }

                throw new UnknownEndpointSpecificError($this->verboseErrors ? $body : Blocktrail::EXCEPTION_UNKNOWN_ENDPOINT_SPECIFIC_ERROR);
            }
        } elseif ($httpResponseCode == 401) {
            throw new InvalidCredentials($this->verboseErrors ? $body : Blocktrail::EXCEPTION_INVALID_CREDENTIALS, $httpResponseCode);
        } elseif ($httpResponseCode == 404) {
            if ($httpResponsePhrase == "Endpoint Not Found") {
                throw new MissingEndpoint($this->verboseErrors ? $body : Blocktrail::EXCEPTION_MISSING_ENDPOINT, $httpResponseCode);
            } else {
                throw new ObjectNotFound($this->verboseErrors ? $body : Blocktrail::EXCEPTION_OBJECT_NOT_FOUND, $httpResponseCode);
            }
        } elseif ($httpResponseCode == 500) {
            throw new GenericServerError(Blocktrail::EXCEPTION_GENERIC_SERVER_ERROR . "\nServer Response: " . $body, $httpResponseCode);
        } else {
            throw new GenericHTTPError(Blocktrail::EXCEPTION_GENERIC_HTTP_ERROR . "\nServer Response: " . $body, $httpResponseCode);
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
