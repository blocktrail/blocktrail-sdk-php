<?php

namespace Blocktrail\SDK\V3Crypt;

use AESGCM\AESGCM;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use BitWasp\Buffertools\Buffertools;
use BitWasp\Buffertools\Parser;

class Encryption
{
    const DEFAULT_SALTLEN = 10;
    const TAGLEN_BITS = 128;
    const IVLEN_BYTES = 16;

    /**
     * @param BufferInterface $pt
     * @param BufferInterface $pw
     * @param int $iterations
     * @return BufferInterface
     */
    public static function encrypt(BufferInterface $pt, BufferInterface $pw, $iterations = KeyDerivation::DEFAULT_ITERATIONS) {
        $salt = new Buffer(random_bytes(self::DEFAULT_SALTLEN));
        $iv = new Buffer(random_bytes(self::IVLEN_BYTES));
        if (!is_int($iterations) || $iterations < 1) {
            throw new \InvalidArgumentException('Iterations must be an integer > 0');
        }

        return self::encryptWithSaltAndIV($pt, $pw, $salt, $iv, $iterations);
    }

    /**
     * @param BufferInterface $ct
     * @param BufferInterface $pw
     * @return BufferInterface
     */
    public static function decrypt(BufferInterface $ct, BufferInterface $pw) {
        $parser = new Parser($ct);
        $sLB = $parser->readBytes(1);
        $salt = $parser->readBytes($sLB->getInt());
        $itB = $parser->readBytes(4);
        $header = new Buffer($sLB->getBinary() . $salt->getBinary() . $itB->getBinary());

        $iv = $parser->readBytes(16);
        $act = $parser->readBytes($ct->getSize() - $parser->getPosition());
        $tag = $act->slice(-16);
        $ct = $act->slice(0, -16);

        return new Buffer(
            AESGCM::decrypt(
                KeyDerivation::compute($pw, $salt, unpack('V', $itB->getBinary())[1])->getBinary(),
                $iv->getBinary(),
                $ct->getBinary(),
                $header->getBinary(),
                $tag->getBinary()
            )
        );
    }

    /**
     * @param BufferInterface $pt
     * @param BufferInterface $pw
     * @param BufferInterface $salt
     * @param BufferInterface $iv
     * @param int $iterations
     * @return BufferInterface
     */
    public static function encryptWithSaltAndIV(BufferInterface $pt, BufferInterface $pw, BufferInterface $salt, BufferInterface $iv, $iterations) {
        if ($iv->getSize() !== 16) {
            throw new \RuntimeException('IV must be exactly 16 bytes');
        }

        $header = new Buffer(pack('C', $salt->getSize()) . $salt->getBinary() . pack('V', $iterations));

        list ($ct, $tag) = AESGCM::encrypt(
            KeyDerivation::compute($pw, $salt, $iterations)->getBinary(),
            $iv->getBinary(),
            $pt->getBinary(),
            $header->getBinary()
        );

        return Buffertools::concat($header, new Buffer($iv->getBinary() . $ct . $tag));
    }
}
