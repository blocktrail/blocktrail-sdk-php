<?php

namespace Blocktrail\SDK\V3Crypt;

use AESGCM\AESGCM;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use BitWasp\Buffertools\Parser;

class Encryption
{
    const DEFAULT_SALTLEN = 32;
    const TAGLEN_BITS = 128;
    const IVLEN_BYTES = 16;

    /**
     * @param BufferInterface $pt
     * @param BufferInterface $pw
     * @return BufferInterface
     */
    public static function encrypt(BufferInterface $pt, BufferInterface $pw)
    {
        $salt = new Buffer(random_bytes(self::DEFAULT_SALTLEN));
        $iv = new Buffer(random_bytes(self::IVLEN_BYTES));
        $iterations = KeyDerivation::DEFAULT_ITERATIONS;
        return self::encryptWithSaltAndIV($pt, $pw, $salt, $iv, $iterations);
    }

    /**
     * @param BufferInterface $ct
     * @param BufferInterface $pw
     * @return BufferInterface
     */
    public static function decrypt(BufferInterface $ct, BufferInterface $pw)
    {
        $parser = new Parser($ct);

        $saltLen = (int) $parser->readBytes(1)->getInt();
        $salt = $parser->readBytes($saltLen);
        $iterationsB = $parser->readBytes(4);
        $iterations = (int) $iterationsB->flip()->getInt();
        $iv = $parser->readBytes(16);
        $act = $parser->readBytes($ct->getSize() - $parser->getPosition());
        $tag = $act->slice(-16);
        $ct = $act->slice(0, -16);

        return new Buffer(AESGCM::decrypt(KeyDerivation::compute($pw, $salt, $iterations)->getBinary(), $iv->getBinary(), $ct->getBinary(), $salt->getBinary(), $tag->getBinary()));
    }

    /**
     * @param BufferInterface $pt
     * @param BufferInterface $pw
     * @param BufferInterface $salt
     * @param BufferInterface $iv
     * @param int $iterations
     * @return BufferInterface
     */
    public static function encryptWithSaltAndIV(BufferInterface $pt, BufferInterface $pw, BufferInterface $salt, BufferInterface $iv, $iterations)
    {
        if ($iv->getSize() !== 16) {
            throw new \RuntimeException('IV must be exactly 16 bytes');
        }

        list ($ct, $tag) = AESGCM::encrypt(KeyDerivation::compute($pw, $salt, $iterations)->getBinary(), $iv->getBinary(), $pt->getBinary(), $salt->getBinary());

        return new Buffer(pack("c", $salt->getSize()) . $salt->getBinary() .Buffer::int($iterations, 4)->flip()->getBinary(). $iv->getBinary() . $ct . $tag);
    }
}
