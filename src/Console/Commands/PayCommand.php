<?php

namespace Blocktrail\SDK\Console\Commands;

use BitWasp\BitcoinLib\BitcoinLib;
use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Console\Application;
use Blocktrail\SDK\WalletInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class PayCommand extends AbstractWalletCommand {

    protected function configure() {
        $this
            ->setName('pay')
            // ->setAliases(['send'])
            ->setDescription("Send a payment")
            ->addArgument("recipient", InputArgument::IS_ARRAY, "<address>:<btc-value>")
            ->addOption('silent', 's', InputOption::VALUE_NONE, "don't ask for confirmation");

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        parent::execute($input, $output);

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $wallet = $this->getWallet($input, $output);

        $pay = [];
        $address = null;
        foreach ($input->getArgument("recipient") as $recipient) {
            $recipient = explode(":", $recipient);

            if (count($recipient) == 2) {
                if ($address) {
                    throw new \Exception("Bad input");
                }

                $address = $recipient[0];
                $value = $recipient[1];
            } else if (count($recipient) == 1) {
                if (!$address) {
                    $address = $recipient[0];
                    continue;
                } else {
                    $value = $recipient[0];
                }
            } else {
                throw new \Exception("Bad input");
            }

            if (!BitcoinLib::validate_address($address)) {
                throw new \Exception("Invalid address");
            }

            if (isset($pay[$address])) {
                throw new \Exception("Same address apears twice in input");
            }

            if (strpos($value, ".") !== false || strpos($value, "," !== false)) {
                $value = BlocktrailSDK::toSatoshi($value);
            } else {
                if (!$questionHelper->ask($input, $output, new ConfirmationQuestion("Did you specify this value in satoshi? [y/N] ", false))) {
                    $value = BlocktrailSDK::toSatoshi($value);
                }
            }

            $pay[$address] = $value;
            $address = null;
        }

        if ($address) {
            throw new \Exception("Bad input");
        }

        if (!$input->getOption('silent')) {
            $output->writeln("Sending payment from [<bold>{$wallet->getIdentifier()}</bold>] to:");
            foreach ($pay as $address => $value) {
                $output->writeln("[{$address}] {$value} Satoshi = " . BlocktrailSDK::toBTCString($value) . " BTC");
            }

            if (!$questionHelper->ask($input, $output, new ConfirmationQuestion("Send? [Y/n] ", true))) {
                exit(1);
            }
        }

        $txHash = $wallet->pay($pay);

        $output->writeln("TX {$txHash}");
    }
}
