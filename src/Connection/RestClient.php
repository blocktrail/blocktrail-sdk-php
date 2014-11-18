<?PHP

namespace Blocktrail\SDK\Connection;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Post\PostBodyInterface;
use GuzzleHttp\Query;
use GuzzleHttp\Stream\Stream;
use HttpSignatures\Context;
use HttpSignatures\GuzzleHttp\RequestSubscriber;
use Blocktrail\SDK\Blocktrail;
use Blocktrail\SDK\Connection\Exceptions\EndpointSpecificError;
use Blocktrail\SDK\Connection\Exceptions\GenericServerError;
use Blocktrail\SDK\Connection\Exceptions\ObjectNotFound;
use Blocktrail\SDK\Connection\Exceptions\UnknownEndpointSpecificError;
use Blocktrail\SDK\Connection\Exceptions\EmptyResponse;
use Blocktrail\SDK\Connection\Exceptions\InvalidCredentials;
use Blocktrail\SDK\Connection\Exceptions\MissingEndpoint;
use Blocktrail\SDK\Connection\Exceptions\GenericHTTPError;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class RestClient
 *
 * @package Blocktrail\SDK\Connection
 */
class RestClient {

    /**
     * @var Guzzle
     */
    protected $guzzle;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var bool
     */
    protected $verboseErrors = false;

    public function __construct($apiEndpoint, $apiVersion, $apiKey, $apiSecret) {
        $this->guzzle = new Guzzle(array(
            'base_url' => $apiEndpoint,
            'defaults' => array(
                'headers' => array(
                    'User-Agent' => Blocktrail::SDK_USER_AGENT . '/' . Blocktrail::SDK_VERSION
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
        $this->guzzle->setDefaultOption($key, $value);
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
     * @param   string          $endpointUrl
     * @param   array           $queryString
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @return  Response
     */
    public function get($endpointUrl, $queryString = null, $auth = null) {
        return $this->request('GET', $endpointUrl, $queryString, null, $auth);
    }

    /**
     * @param   string          $endpointUrl
     * @param   null            $queryString
     * @param   array|string    $postData
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @return  Response
     */
    public function post($endpointUrl, $queryString = null, $postData = '', $auth = null) {
        return $this->request('POST', $endpointUrl, $queryString, $postData, $auth);
    }

    /**
     * @param   string          $endpointUrl
     * @param   null            $queryString
     * @param   array|string    $putData
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @return  Response
     */
    public function put($endpointUrl, $queryString = null, $putData = '', $auth = null) {
        return $this->request('PUT', $endpointUrl, $queryString, $putData, $auth);
    }

    /**
     * @param   string          $endpointUrl
     * @param   null            $queryString
     * @param   array|string    $postData
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @return  Response
     */
    public function delete($endpointUrl, $queryString = null, $postData = null, $auth = null) {
        return $this->request('DELETE', $endpointUrl, $queryString, $postData, $auth, 'url');
    }

    /**
     * generic request executor
     *
     * @param   string          $method         GET, POST, PUT, DELETE
     * @param   string          $endpointUrl
     * @param   string          $queryString
     * @param   array|string    $body
     * @param   string          $auth           http-signatures to enable http-signature signing
     * @param   string          $contentMD5Mode body or url
     * @return Response
     */
    protected function request($method, $endpointUrl, $queryString = null, $body = null, $auth = null, $contentMD5Mode = null) {
        if (is_null($contentMD5Mode)) {
            $contentMD5Mode = !is_null($body) ? 'body' : 'url';
        }

        $request = $this->guzzle->createRequest($method, $endpointUrl);

        if ($queryString) {
            $request->getQuery()->replace($queryString);
        }

        if (!$request->getQuery()->get('api_key')) {
            $request->getQuery()->set('api_key', $this->apiKey);
        }

        // normalize the query string the same way the server expects it
        $request->setQuery(Query::fromString(Request::normalizeQueryString((string)$request->getQuery())));

        if (!$request->hasHeader('Date')) {
            $request->setHeader('Date', $this->getRFC1123DateString());
        }

        if (!is_null($body)) {
            if (!$request->hasHeader('Content-Type')) {
                $request->setHeader('Content-Type', 'application/json');
            }

            if (!is_string($body)) {
                $body = json_encode($body);
            }
            $request->setBody(Stream::factory($body));
        }

        // for GET/DELETE requests, MD5 the request URI (excludes domain, includes query strings)
        if ($contentMD5Mode == 'body') {
            $request->setHeader('Content-MD5', md5((string)$body));

        // for POST/PUT requests, MD5 the body
        } else {
            $qs = (string)$request->getQuery();
            $request->setHeader('Content-MD5', md5($request->getPath() . ($qs ? "?{$qs}" : "")));
        }

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
                throw new EmptyResponse(Blocktrail::EXCEPTION_EMPTY_RESPONSE, $httpResponseCode);
            }

            $result = new Response($httpResponseCode, $body);

            return $result;
        } elseif ($httpResponseCode == 400 || $httpResponseCode == 403) {
            $data = json_decode($body, true);

            if ($data && isset($data['msg'], $data['code'])) {
                throw new EndpointSpecificError($data['msg'], $data['code']);
            } else {
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
