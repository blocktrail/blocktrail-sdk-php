<?php

namespace Blocktrail\SDK\Console\Commands;

use Blocktrail\SDK\Console\Application;
use Blocktrail\SDK\WalletInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class GetNewAddressCommand extends AbstractWalletCommand {

    protected function configure() {
        $this
            ->setName('new_address')
            // ->setAliases(['get_new_address', 'address'])
            ->setDescription("Get a new address for a wallet");

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        parent::execute($input, $output);

        $wallet = $this->getWallet($input, $output);

        $output->writeln($wallet->getNewAddress());
    }
}
