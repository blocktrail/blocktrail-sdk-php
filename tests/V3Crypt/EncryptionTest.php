<?php

namespace Blocktrail\SDK\Tests\V3Crypt;

use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\V3Crypt\Encryption;
use BitWasp\Buffertools\Buffer;

class EncryptionTest extends AbstractTestCase
{
    /**
     * @return array
     */
    public function getEncryptionVectors() {
        return array_map(function (array $row) {
            return [Buffer::hex($row['password']), Buffer::hex($row['salt']), $row['iterations'], Buffer::hex($row['iv']), Buffer::hex($row['pt']), Buffer::hex($row['ct']), Buffer::hex($row['tag']), Buffer::hex($row['full'])];
        }, $this->getTestVectors()['encryption']);
    }

    /**
     * @dataProvider getEncryptionVectors
     * @param BufferInterface $password
     * @param BufferInterface $salt
     * @param int $iterations
     * @param BufferInterface $iv
     * @param BufferInterface $plaintext
     * @param BufferInterface $ciphertext
     * @param BufferInterface $tag
     * @param BufferInterface $serialized
     */
    public function testEncryption(BufferInterface $password, BufferInterface $salt, $iterations, BufferInterface $iv, BufferInterface $plaintext, BufferInterface $ciphertext, BufferInterface $tag, BufferInterface $serialized) {

        $encrypt = Encryption::encryptWithSaltAndIV($plaintext, $password, $salt, $iv, $iterations);
        $decrypted = Encryption::decrypt($encrypt, $password);

        $this->assertEquals($plaintext, $decrypted);
        $this->assertEquals($plaintext, Encryption::decrypt($serialized, $password));
        $this->assertEquals($encrypt, $serialized);
    }
}
