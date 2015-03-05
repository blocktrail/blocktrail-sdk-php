<?php

namespace Blocktrail\SDK\Console\Commands;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\Console\Application;
use Blocktrail\SDK\WalletInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class ListUTXOCommand extends AbstractWalletCommand {

    protected function configure() {
        $this
            ->setName('list_utxos')
            // ->setAliases(['utxos'])
            ->setDescription("List UTXO set for a wallet")
            ->addOption('page', 'p', InputOption::VALUE_REQUIRED, 'pagination page', 1)
            ->addOption('per-page', 'pp', InputOption::VALUE_REQUIRED, 'pagination limit', 50);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        parent::execute($input, $output);

        $wallet = $this->getWallet($input, $output);

        $page = $input->getOption('page');
        $perpage = $input->getOption('per-page');

        $UTXOs = $wallet->utxos($page, $perpage)['data'];

        $table = new Table($output);
        $table->setHeaders(['#', 'tx', 'idx', 'value', 'confirmations', 'address']);
        foreach ($UTXOs as $i => $UTXO) {
            $table->addRow([$i, $UTXO['hash'], $UTXO['idx'], BlocktrailSDK::toBTCString($UTXO['value']), $UTXO['confirmations'], $UTXO['address']]);
        }

        $table->render();

        if (count($UTXOs) >= $perpage) {
            $output->writeln("there are more UTXOs, use --page and --perpage to see all of them ...");
        }
    }
}
