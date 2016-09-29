<?php

namespace Blocktrail\SDK\Tests\V3Crypt;

use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\SDK\V3Crypt\Mnemonic;

class MnemonicTest extends AbstractTestCase
{
    /**
     * @return array
     */
    public function getConsistencyCheckVectors() {
        return array_map(function (array $row) {
            return [Buffer::hex($row['data']), $row['mnemonic']];
        }, $this->getTestVectors()['mnemonic']);
    }

    /**
     * @dataProvider getConsistencyCheckVectors
     * @param BufferInterface $data
     * @param $mnemonic
     */
    public function testConsistency(BufferInterface $data, $mnemonic) {
        $this->assertEquals($mnemonic, Mnemonic::encode($data));
        $this->assertTrue($data->equals(Mnemonic::decode($mnemonic)));
    }
}
