<?php

namespace BlockTrail\SDK\Tests;

use BlockTrail\SDK\APIClient;
use BlockTrail\SDK\Connection\Exceptions\InvalidCredentials;
use BlockTrail\SDK\Exceptions\InvalidFormat;
use GuzzleHttp\Post\PostBody;

/**
 * Class CrossPlatformTest
 * test cases to validate if the various low-level libraries in our SDKs give the same results
 *
 * @package BlockTrail\SDK\Tests
 */
class CrossPlatformTest extends \PHPUnit_Framework_TestCase {

    public function testPostBodyMD5() {
        $postBody = new PostBody();
        $postBody->replaceFields(['signature' => "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk="]);

        $this->assertEquals("signature=HPMOHRgPSMKdXrU6AqQs%2Fi9S7alOakkHsJiqLGmInt05Cxj6b%2FWhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk%3D", (string)$postBody);
        $this->assertEquals("fdfc1a717d2c97649f3b8b2142507129", md5((string)$postBody));
    }
}