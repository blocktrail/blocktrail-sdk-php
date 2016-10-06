<?php

namespace Blocktrail\SDK\V3Crypt;

use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

class KeyDerivation
{
    const HASHER = 'sha512';
    const DEFAULT_ITERATIONS = 35000;
    const KEYLEN_BITS = 256;

    /**
     * @param BufferInterface $password
     * @param BufferInterface $salt
     * @param int $iterations
     * @return BufferInterface
     */
    public static function compute(BufferInterface $password, BufferInterface $salt, $iterations)
    {
        if (!($iterations >= 0 && $iterations < pow(2, 32))) {
            throw new \RuntimeException('Iterations must be a number between 1 and 2^32');
        }

        if ($iterations < 512) {
            throw new \RuntimeException('Iteration count should be at least 512');
        }

        if ($salt->getSize() === 0) {
            throw new \RuntimeException('Salt must not be empty');
        }

        return new Buffer(hash_pbkdf2(self::HASHER, $password->getBinary(), $salt->getBinary(), $iterations, self::KEYLEN_BITS / 8, true));
    }
}
