<?PHP

namespace Blocktrail\SDK\Connection;

use GuzzleHttp\Stream\StreamInterface;

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
     * @var \GuzzleHttp\Stream\StreamInterface
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
