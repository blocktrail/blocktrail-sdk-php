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

class SplitUTXOsCommand extends AbstractWalletCommand {

    protected function configure() {
        $this
            ->setName('split_utxos')
            ->setDescription("Split UTXOs")
            ->addArgument("count", InputArgument::REQUIRED, "the amount of chunks")
            ->addArgument("value", InputArgument::REQUIRED, "the value of each chunk")
            ->addOption('value-is-total', null, InputOption::VALUE_NONE, "the value argument should be devided by the count")
            ->addOption('silent', 's', InputOption::VALUE_NONE, "don't ask for confirmation");

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        parent::execute($input, $output);

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $wallet = $this->getWallet($input, $output);

        list($confirmed, $unconfirmed) = $wallet->getBalance();

        $count = $input->getArgument('count');
        $value = $input->getArgument('value');

        if (strpos($value, ".") !== false || strpos($value, "," !== false)) {
            $value = BlocktrailSDK::toSatoshi($value);
        } else {
            if (!$questionHelper->ask($input, $output, new ConfirmationQuestion("Did you specify this value in satoshi? [y/N] ", false))) {
                $value = BlocktrailSDK::toSatoshi($value);
            }
        }

        if ($input->getOption('value-is-total')) {
            $value = (int)floor($value / $count);
        }

        if ($value * $count > $confirmed) {
            $output->writeln("<error>You do not have enough confirmed balance; " . BlocktrailSDK::toBTCString($value * $count) . " BTC > " . BlocktrailSDK::toBTCString($confirmed) . " BTC) </error>");
            exit(1);
        }

        $pay = [];
        for ($i = 0; $i < $count; $i++) {
            $pay[$wallet->getNewAddress()] = $value;
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
