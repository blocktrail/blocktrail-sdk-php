<?php

namespace Blocktrail\SDK\Tests;


use BitWasp\Bitcoin\Script\Opcodes;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\TransactionBuilder;
use Blocktrail\SDK\Wallet;

class TransactionBuilderTest extends BlocktrailTestCase
{
    public function feeStrategyProvider()
    {
        return [
            [Wallet::FEE_STRATEGY_BASE_FEE],
            [Wallet::FEE_STRATEGY_OPTIMAL],
            [Wallet::FEE_STRATEGY_LOW_PRIORITY],
        ];
    }

    /**
     * @param string $strategy
     * @dataProvider feeStrategyProvider
     */
    public function testSetsFeeStrategy($strategy)
    {
        $txBuilder = new TransactionBuilder();
        $txBuilder->setFeeStrategy($strategy);

        $this->assertEquals($strategy, $txBuilder->getFeeStrategy());
    }

    /**
     */
    public function testFeeStrategyDefaultOptimal()
    {
        $txBuilder = new TransactionBuilder();
        $this->assertEquals(Wallet::FEE_STRATEGY_OPTIMAL, $txBuilder->getFeeStrategy());
    }

    /**
     * @expectedException \Blocktrail\SDK\Exceptions\BlocktrailSDKException
     * @expectedExceptionMessage Unknown feeStrategy [gibberish]
     */
    public function testFeeStrategyUnknown()
    {
        (new TransactionBuilder())
            ->setFeeStrategy("gibberish")
        ;
    }

    public function testSetFee() {
        $amt = 12345;
        $builder = (new TransactionBuilder())
            ->setFee($amt)
        ;
        $this->assertEquals($amt, $builder->getFee());
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Fee should be in Satoshis (int) - can be 0
     */
    public function testSetFeeBadInput() {
        (new TransactionBuilder())
            ->setFee(12345.1)
        ;
    }

    /**
     */
    public function testSetsChangeAddress()
    {
        $address = "1F1xcRt8H8Wa623KqmkEontwAAVqDSAWCV";
        $txBuilder = new TransactionBuilder();
        $txBuilder->setChangeAddress($address);

        $this->assertEquals($address, $txBuilder->getChangeAddress());
    }

    /**
     */
    public function testChangeAddressDefaultNull()
    {
        $txBuilder = new TransactionBuilder();
        $this->assertEquals(null, $txBuilder->getChangeAddress());
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Input to randomizeChangeOutput should be a boolean
     */
    public function testBadInputRandomizeChangeOutput()
    {
        (new TransactionBuilder())
            ->randomizeChangeOutput("gibberish")
        ;
    }

    public function addOpReturnProvider() {
        return [
            ['', false],
            ['hithere', false],
            [str_repeat('A', 75), false],
            [str_repeat('A', 76), false],
            [str_repeat('A', 79), false],
            [str_repeat('A', 80), true],
        ];
    }

    /**
     * @param $rawString
     * @param $allowNonStandard
     * @dataProvider addOpReturnProvider
     */
    public function testAddOpReturn($rawString, $allowNonStandard) {
        $builder = new TransactionBuilder();
        $builder->addOpReturn($rawString, $allowNonStandard);

        $outputs = $builder->getOutputs();
        $this->assertEquals(1, count($outputs));
        $output = $outputs[0];
        $this->assertTrue(array_key_exists('scriptPubKey', $output));
        $this->assertTrue(array_key_exists('value', $output));
        $this->assertEquals(0, $output['value']);

        $raw = new Buffer($rawString);
        $varIntSize = ScriptFactory::sequence([$raw])->getBuffer()->getSize() - $raw->getSize();

        // check against expected size: op_return|varint|data push
        $this->assertEquals(1+$varIntSize+strlen($rawString), strlen($output['scriptPubKey']->getBinary()));
    }

    /**
     * @expectedExceptionMessage OP_RETURN data should be <= 79 bytes to remain standard!
     * @expectedException \Exception
     */
    public function testAddOpReturnChecksIsStandard() {
        (new TransactionBuilder())
            ->addOpReturn(str_repeat('A', 80))
        ;
    }
}
