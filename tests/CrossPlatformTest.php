<?php

namespace Blocktrail\SDK\Tests;

use GuzzleHttp\Post\PostBody;
use HttpSignatures\Context;
use HttpSignatures\GuzzleHttp\Message;
use HttpSignatures\GuzzleHttp\RequestSubscriber;

/**
 * Class CrossPlatformTest
 * test cases to validate if the various low-level libraries in our SDKs give the same results
 */
class CrossPlatformTest extends \PHPUnit_Framework_TestCase {

    public function testPostBodyMD5() {
        $postBody = new PostBody();
        $postBody->replaceFields(['signature' => "HPMOHRgPSMKdXrU6AqQs/i9S7alOakkHsJiqLGmInt05Cxj6b/WhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk="]);

        $this->assertEquals("signature=HPMOHRgPSMKdXrU6AqQs%2Fi9S7alOakkHsJiqLGmInt05Cxj6b%2FWhS7kJxbIQxKmDW08YKzoFnbVZIoTI2qofEzk%3D", (string)$postBody);
        $this->assertEquals("fdfc1a717d2c97649f3b8b2142507129", md5((string)$postBody));
    }

    public function testHMAC() {
        $context = new Context(array(
            'keys' => array('pda' => 'secret'),
            'algorithm' => 'hmac-sha256',
            'headers' => array('(request-target)', 'date'),
        ));

        $client = new \GuzzleHttp\Client([
            'auth' => 'http-signatures'
        ]);
        $client->getEmitter()->attach(new RequestSubscriber($context));

        $message = $client->createRequest('GET', '/path?query=123', array(
            'headers' => array('date' => 'today', 'accept' => 'llamas')
        ));

        $context->signer()->sign(new Message($message));

        $expectedString = implode(
            ',',
            array(
                'keyId="pda"',
                'algorithm="hmac-sha256"',
                'headers="(request-target) date"',
                'signature="SFlytCGpsqb/9qYaKCQklGDvwgmrwfIERFnwt+yqPJw="',
            )
        );

        $this->assertEquals(
            $expectedString,
            (string) $message->getHeader('Signature')
        );
    }
}
