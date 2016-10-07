<?php

namespace Blocktrail\SDK\Tests\V3Crypt;

abstract class AbstractTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    private $vectors = null;

    /**
     * AbstractTestCase constructor.
     * @param null $name
     * @param array $data
     * @param string $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '') {
        $this->getTestVectors();
        parent::__construct($name, $data, $dataName);
    }

    /**
     * @return array
     */
    private function readTestVectorsFile() {
        $decoded = json_decode(file_get_contents(__DIR__ .'/../data/crypt_vectors.json'), true);
        if (!$decoded || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('failed to read vectors file');
        }

        return $decoded;
    }

    /**
     * @return array
     */
    public function getTestVectors() {
        if (null === $this->vectors) {
            $this->vectors = $this->readTestVectorsFile();
        }

        return $this->vectors;
    }
}
