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

class DiscoveryCommand extends AbstractWalletCommand {

    protected function configure() {
        $this
            ->setName('discovery')
            ->setDescription("Do wallet discovery");

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        parent::execute($input, $output);

        $wallet = $this->getWallet($input, $output);

        list($confirmed, $unconfirmed) = $wallet->doDiscovery();
        $final = $confirmed + $unconfirmed;

        $output->writeln("Confirmed Balance; {$confirmed} Satoshi = " . BlocktrailSDK::toBTCString($confirmed) . " BTC");
        $output->writeln("Unconfirmed Balance; {$unconfirmed} Satoshi = " . BlocktrailSDK::toBTCString($unconfirmed) . " BTC");
        $output->writeln("Final Balance; {$final} Satoshi = " . BlocktrailSDK::toBTCString($final) . " BTC");
    }
}
