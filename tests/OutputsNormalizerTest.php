<?php

namespace Blocktrail\SDK\Tests;

use BitWasp\Bitcoin\Network\NetworkFactory;
use Blocktrail\SDK\Address\BitcoinAddressReader;
use Blocktrail\SDK\Address\BitcoinCashAddressReader;
use Blocktrail\SDK\Blocktrail;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\Network\BitcoinCash;
use Blocktrail\SDK\Network\BitcoinCashTestnet;
use Blocktrail\SDK\OutputsNormalizer;

class OutputsNormalizerTest extends \PHPUnit_Framework_TestCase
{
    private function loadAddressReader($network, $testnet) {
        switch ($network) {
            case "BTC":
                if ($testnet) {
                    return [NetworkFactory::bitcoinTestnet(), new BitcoinAddressReader()];
                }
                return [NetworkFactory::bitcoin(), new BitcoinAddressReader()];
                break;
            case "BCC":
                if ($testnet) {
                    $network = new BitcoinCashTestnet();
                } else {
                    $network = new BitcoinCash();
                }

                return [$network, new BitcoinCashAddressReader(true)];
                break;
            default:
                throw new \RuntimeException("Unknown network");
        }
    }

    public function getFixtures() {
        return [
            [
                "BTC",
                true,
                [
                    "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs" => 12345,
                    "tb1qrp33g0q5c5txsp9arysrx4k6zdkfs4nce4xj0gdcccefvpysxf3q0sl5k7" => 99999,
                ],
                [
                    [
                        "scriptPubKey" => "00149bce9399f9eeddad66635d4be171ec8f14decc59",
                        "value" => 12345,
                    ],
                    [
                        "scriptPubKey" => "00201863143c14c5166804bd19203356da136c985678cd4d27a1b8c6329604903262",
                        "value" => 99999,
                    ]
                ]
            ],
            [
                "BTC",
                true,
                [],
                []
            ],
            [
                "BTC",
                true,
                [
                    ["tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs", 12345]
                ],
                [
                    [
                        "scriptPubKey" => "00149bce9399f9eeddad66635d4be171ec8f14decc59",
                        "value" => 12345,
                    ]
                ]
            ],
            [
                "BTC",
                true,
                [
                    [
                        "address" => "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs",
                        "value" => 12345
                    ]
                ],
                [
                    [
                        "scriptPubKey" => "00149bce9399f9eeddad66635d4be171ec8f14decc59",
                        "value" => 12345,
                    ]
                ]
            ],
            [
                "BTC",
                true,
                [
                    [
                        "scriptPubKey" => "00149bce9399f9eeddad66635d4be171ec8f14decc59",
                        "value" => 12345,
                    ],
                    [
                        "scriptPubKey" => "001442424242f9eeddad66635d4be171ec8f14decc59",
                        "value" => 12345,
                    ]
                ],
                [
                    [
                        "scriptPubKey" => "00149bce9399f9eeddad66635d4be171ec8f14decc59",
                        "value" => 12345,
                    ],
                    [
                        "scriptPubKey" => "001442424242f9eeddad66635d4be171ec8f14decc59",
                        "value" => 12345,
                    ]
                ]
            ],
            [
                "BTC",
                true,
                [
                    "tb1qrp33g0q5c5txsp9arysrx4k6zdkfs4nce4xj0gdcccefvpysxf3q0sl5k7" => 12345
                ],
                [
                    [
                        "scriptPubKey" => "00201863143c14c5166804bd19203356da136c985678cd4d27a1b8c6329604903262",
                        "value" => 12345,
                    ]
                ]
            ],
            [
                "BTC",
                true,
                [
                    "muinVykhtZyonxQxk8zBptX6Lmri91bdNG" => 12345
                ],
                [
                    [
                        "scriptPubKey" => "76a9149bce9399f9eeddad66635d4be171ec8f14decc5988ac",
                        "value" => 12345,
                    ]
                ]
            ],
            [
                "BTC",
                true,
                [
                    "2N7T4CD6CEuNHJoGKpoJH3YexqektXjyy6L" => 12345
                ],
                [
                    [
                        "scriptPubKey" => "a9149bce9399f9eeddad66635d4be171ec8f14decc5987",
                        "value" => 12345,
                    ],
                ]
            ],
            [
                "BTC",
                true,
                [
                    [
                        "address" => "opreturn",
                        "value" => "to the moon!",
                    ],
                ],
                [
                    [
                        "scriptPubKey" => "6a0c746f20746865206d6f6f6e21",
                        "value" => 0,
                    ]
                ]
            ],
            [
                "BCC",
                true,
                [
                    "muinVykhtZyonxQxk8zBptX6Lmri91bdNG" => 12345
                ],
                [
                    [
                        "scriptPubKey" => "76a9149bce9399f9eeddad66635d4be171ec8f14decc5988ac",
                        "value" => 12345,
                    ]
                ]
            ],
            [
                "BCC",
                false,
                [
                    "bitcoincash:ppm2qsznhks23z7629mms6s4cwef74vcwvn0h829pq" => 12345
                ],
                [
                    [
                        "scriptPubKey" => "a91476a04053bda0a88bda5177b86a15c3b29f55987387",
                        "value" => 12345,
                    ]
                ]
            ]
        ];
    }

    /**
     * @param $network
     * @param $testnet
     * @param array $input
     * @param array $expected
     * @throws \Blocktrail\SDK\Exceptions\BlocktrailSDKException
     * @dataProvider getFixtures
     */
    public function testOutputsNormalizer($network, $testnet, array $input, array $expected)
    {
        list ($network, $addressReader) = $this->loadAddressReader($network, $testnet);

        $normalizer = new OutputsNormalizer($addressReader);
        $outputs = $normalizer->normalize($input, $network);

        $this->assertEquals(count($expected), count($outputs));
        foreach ($expected as $i => $output) {
            $result = $outputs[$i];
            $this->assertEquals($output['value'], $result['value']);
            $this->assertEquals($output['scriptPubKey'], $result['scriptPubKey']->getHex());
        }
    }

    public function getErrorFixtures()
    {
        return [
            [
                "BTC",
                true,
                [
                    []
                ],
                BlocktrailSDKException::class,
                "Invalid transaction output for numerically indexed list",
            ],
            [
                "BTC",
                true,
                [
                    "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs" => 12345,
                    999999999 => 12390,
                ],
                BlocktrailSDKException::class,
                "Encountered invalid address while traversing address keyed list",
            ],
            [
                "BTC",
                true,
                [
                    999999999 => 12390,
                    "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs" => 12345,
                ],
                BlocktrailSDKException::class,
                "Encountered invalid output while traversing numerically indexed list",
            ],
            [
                "BTC",
                true,
                [
                    [
                        "address" => "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs",
                        "value" => 1234,
                    ],
                    "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs" => 1,
                ],
                BlocktrailSDKException::class,
                "Encountered invalid index while traversing numerically indexed list",
            ],
            [
                "BTC",
                true,
                [
                    "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs" => 1234.1
                ],
                BlocktrailSDKException::class,
                "Value should be in Satoshis",
            ],
            [
                "BTC",
                true,
                [
                    "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs" => '1234.1'
                ],
                BlocktrailSDKException::class,
                "Value should be in Satoshis",
            ],
            [
                "BTC",
                true,
                [
                    [
                        "scriptPubKey" => "",
                        "value" => 1234,
                    ]
                ],
                BlocktrailSDKException::class,
                "Script cannot be empty",
            ],
            [
                "BTC",
                true,
                [
                    [
                        "address" => "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs",
                        "value" => 0,
                    ]
                ],
                BlocktrailSDKException::class,
                "Values should be non zero",
            ],
            [
                "BTC",
                true,
                [
                    [
                        "address" => "tb1qn08f8x0eamw66enrt497zu0v3u2danzey6asqs",
                        "value" => 1,
                    ]
                ],
                BlocktrailSDKException::class,
                "Values should be more than dust (".Blocktrail::DUST.")",
            ],
        ];
    }

    /**
     * @dataProvider getErrorFixtures
     * @param $network
     * @param $testnet
     * @param array $input
     * @param $exception
     * @param $exceptionMsg
     * @throws BlocktrailSDKException
     */
    public function testErrorCase($network, $testnet, array $input, $exception, $exceptionMsg)
    {
        list ($network, $addressReader) = $this->loadAddressReader($network, $testnet);

        $normalizer = new OutputsNormalizer($addressReader);

        $this->setExpectedException($exception, $exceptionMsg);

        $normalizer->normalize($input, $network);

    }
}
