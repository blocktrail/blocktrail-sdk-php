<?php

namespace Blocktrail\SDK\Tests\V3Crypt;

use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\V3Crypt\Encryption;
use Blocktrail\SDK\V3Crypt\EncryptionMnemonic;

class FullTest extends AbstractTestCase
{
    /**
     * @param int $len
     * @return BufferInterface
     */
    public function random($len) {
        return new Buffer(random_bytes($len));
    }

    /**
     * @return array
     */
    public function getDecryptionOnlyVectors() {
        return array_map(function (array $row) {
            return [Buffer::hex($row['password']), $row['primaryEncryptedSeed'], $row['encryptedSecret'], $row['checksum']];
        }, $this->getTestVectors()['decryptonly']);
    }

    /**
     * @return array
     */
    public function getPasswordResetVectors() {
        return array_map(function (array $row) {
            return [Buffer::hex($row['expectedSecret']), Buffer::hex($row['recoverySecret']), $row['recoveryEncryptedMnemonic']];
        }, $this->getTestVectors()['password_reset_case']);
    }

    /**
     * @dataProvider getDecryptionOnlyVectors
     * @param BufferInterface $password
     * @param string $encryptedPrimarySeedMnemonic
     * @param string $encryptedSecretMnemonic
     * @param string $checksum
     */
    public function testDecryptionOnly(BufferInterface $password, $encryptedPrimarySeedMnemonic, $encryptedSecretMnemonic, $checksum) {
        $decodedSecret = EncryptionMnemonic::decode($encryptedSecretMnemonic);
        $decryptedSecret = Encryption::decrypt($decodedSecret, $password);

        $decodedPrimarySeed = EncryptionMnemonic::decode($encryptedPrimarySeedMnemonic);
        $decryptedPrimarySeed = Encryption::decrypt($decodedPrimarySeed, $decryptedSecret);

        $hdnode = HierarchicalKeyFactory::fromEntropy($decryptedPrimarySeed);
        $this->assertEquals($checksum, $hdnode->getPublicKey()->getAddress()->getAddress());
    }

    /**
     * @param BufferInterface $expectedSecret
     * @param BufferInterface $recoverySecret
     * @param $recoveryEncryptedMnemonic
     * @dataProvider getPasswordResetVectors
     */
    public function testAllowsPasswordReset(BufferInterface $expectedSecret, BufferInterface $recoverySecret, $recoveryEncryptedMnemonic) {
        $decodedRS = EncryptionMnemonic::decode($recoveryEncryptedMnemonic);
        $decryptedSecret = Encryption::decrypt($decodedRS, $recoverySecret);

        $this->assertEquals($decryptedSecret->getHex(), $expectedSecret->getHex());
    }

    public function testProcedure() {
        $passphrase = new Buffer('FFUgnayLMUDLqpTY2bctzBvx5ckPhFt3n5VadNxyMp8XwpZ8SjVJRZpALTWaUvnE7Fru8j8GqgSzC8zdHeQxV6CM2jzL46ULQeRjPXAsVrbSSYnvW8Axrfgv');
        $primarySeed = $this->random(32);
        $secret = $this->random(32);

        $encryptedSecret = Encryption::encrypt($secret, $passphrase);
        $this->assertTrue($secret->equals(Encryption::decrypt($encryptedSecret, $passphrase)));

        $encryptedPrimarySeed = Encryption::encrypt($primarySeed, $secret);
        $this->assertTrue($primarySeed->equals(Encryption::decrypt($encryptedPrimarySeed, $secret)));

        $recoverySecret = $this->random(32);
        $recoveryEncryptedSecret = Encryption::encrypt($secret, $recoverySecret);
        $this->assertTrue($secret->equals(Encryption::decrypt($recoveryEncryptedSecret, $recoverySecret)));

        $backupInfo = [
            'encryptedPrimarySeed' => EncryptionMnemonic::encode($encryptedPrimarySeed),
            'encryptedSecret' => EncryptionMnemonic::encode($encryptedSecret),
            'recoveryEncryptedSecret' => EncryptionMnemonic::encode($recoveryEncryptedSecret),
        ];

        foreach ($backupInfo as $key => $val) {
            $cmp = $$key;
            $this->assertTrue(EncryptionMnemonic::decode($val)->equals($cmp));
        }
    }
}
