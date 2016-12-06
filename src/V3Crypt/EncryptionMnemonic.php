<?php

namespace Blocktrail\SDK\V3Crypt;

use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

class EncryptionMnemonic
{
    const CHUNK_SIZE = 4;
    const PADDING_DUMMY = "\x81";

    /**
     * @param string $data
     * @return string
     */
    private static function derivePadding($data) {
        if (strlen($data) > 0 && ord($data[0]) > 0x80) {
            throw new \RuntimeException('Sanity check: data for mnemonic is not valid');
        }

        $padLen = self::CHUNK_SIZE - (strlen($data) % self::CHUNK_SIZE);
        return str_pad('', $padLen, self::PADDING_DUMMY);
    }

    /**
     * @param BufferInterface $data
     * @return string
     */
    public static function encode(BufferInterface $data) {
        $bip39 = MnemonicFactory::bip39();
        $mnemonic = $bip39->entropyToMnemonic(new Buffer(self::derivePadding($data->getBinary()) . $data->getBinary()));

        try {
            $bip39->mnemonicToEntropy($mnemonic);
        } catch (\Exception $e) {
            throw new \RuntimeException('BIP39 produced an invalid mnemonic');
        }

        return $mnemonic;
    }

    /**
     * @param string $mnemonic
     * @return BufferInterface
     */
    public static function decode($mnemonic) {
        $bip39 = MnemonicFactory::bip39();
        $decoded = $bip39->mnemonicToEntropy($mnemonic)->getBinary();
        $padFinish = 0;
        while ($decoded[$padFinish] === self::PADDING_DUMMY) {
            $padFinish++;
        }

        $data = substr($decoded, $padFinish);
        if (self::derivePadding($data) !== substr($decoded, 0, $padFinish)) {
            throw new \RuntimeException('The data was incorrectly padded');
        }

        return new Buffer($data);
    }
}
