<?php

namespace Blocktrail\SDK\Tests\V3Crypt;

use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\V3Crypt\Encryption;
use Blocktrail\SDK\V3Crypt\Mnemonic;

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
     * @dataProvider getDecryptionOnlyVectors
     * @param BufferInterface $password
     * @param string $encryptedPrimarySeedMnemonic
     * @param string $encryptedSecretMnemonic
     * @param string $checksum
     */
    public function testDecryptionOnly(BufferInterface $password, $encryptedPrimarySeedMnemonic, $encryptedSecretMnemonic, $checksum) {
        $decodedSecret = Mnemonic::decode($encryptedSecretMnemonic);
        $decryptedSecret = Encryption::decrypt($decodedSecret, $password);

        $decodedPrimarySeed = Mnemonic::decode($encryptedPrimarySeedMnemonic);
        $decryptedPrimarySeed = Encryption::decrypt($decodedPrimarySeed, $decryptedSecret);

        $hdnode = HierarchicalKeyFactory::fromEntropy($decryptedPrimarySeed);
        $this->assertEquals($checksum, $hdnode->getPublicKey()->getAddress()->getAddress());
    }

    public function testAllowsPasswordReset() {
        $expectedSecret = Buffer::hex('9d1a50059b9107f430b8526697d371205770986d020c45900867d228fe56feaa');
        $recoverySecret = Buffer::hex('c40f61d7be45d699cc91dd929af01da235cf67abd6d3d8c0290d2b30c4066acf');
        $recoveryEncryptedSecret = 'light army dragon annual army gauge pumpkin swift home license scale accident supply garbage turn atom display comfort frequent suit choice demand strategy wasp enrich occur dash slogan spring express melt edge long budget dwarf exile crystal limb that normal eternal unveil tennis quality cruel hamster whisper parade situate viable sting special kingdom output height supply surround local can fork';

        $decodedRS = Mnemonic::decode($recoveryEncryptedSecret);
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
            'encryptedPrimarySeed' => Mnemonic::encode($encryptedPrimarySeed),
            'encryptedSecret' => Mnemonic::encode($encryptedSecret),
            'recoveryEncryptedSecret' => Mnemonic::encode($recoveryEncryptedSecret),
        ];

        foreach ($backupInfo as $key => $val) {
            $cmp = $$key;
            $this->assertTrue(Mnemonic::decode($val)->equals($cmp));
        }
    }
}
