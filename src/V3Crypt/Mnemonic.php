<?php

namespace Blocktrail\SDK\V3Crypt;

use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;

class Mnemonic
{
    const PADDING_BLOCK_SIZE = 4;
    const PADDING_DUMMY = "\x81";

    /**
     * @param BufferInterface $data
     * @return string
     */
    public static function encode(BufferInterface $data)
    {
        $padLen = self::PADDING_BLOCK_SIZE - $data->getSize() % self::PADDING_BLOCK_SIZE;
        $data = new Buffer(str_pad('', $padLen, self::PADDING_DUMMY) . $data->getBinary());

        $bip39 = MnemonicFactory::bip39();
        $mnemonic = $bip39->entropyToMnemonic($data);
        $bip39->mnemonicToEntropy($mnemonic);

        return $mnemonic;
    }

    /**
     * @param string $mnemonic
     * @return BufferInterface
     */
    public static function decode($mnemonic)
    {
        $bip39 = MnemonicFactory::bip39();
        $str = $bip39->mnemonicToEntropy($mnemonic)->getBinary();
        $padFinish = 0;
        $length = strlen($str);

        for ($i = 0; $padFinish == 0 && $i < $length; $i+=1) {
            if ($str[$i] !== self::PADDING_DUMMY) {
                $padFinish = $i;
            }
        }

        return new Buffer(substr($str, $padFinish));
    }
}
