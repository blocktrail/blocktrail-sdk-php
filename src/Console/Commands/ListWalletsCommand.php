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

class ListWalletsCommand extends AbstractCommand {

    protected function configure() {
        $this
            ->setName('list_wallets')
            // ->setAliases(['create_new_wallet', 'create_wallet'])
            ->setDescription("List all wallets")
            ->addOption('page', 'p', InputOption::VALUE_REQUIRED, 'pagination page', 1)
            ->addOption('per-page', 'pp', InputOption::VALUE_REQUIRED, 'pagination limit', 50);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        parent::execute($input, $output);

        $sdk = $this->getBlocktrailSDK();
        $config = $this->getConfig();

        $page = $input->getOption('page');
        $perpage = $input->getOption('per-page');

        $wallets = $sdk->allWallets($page, $perpage)['data'];

        if (!$wallets) {
            $output->writeln("<error>There are no wallets!</error>");
            exit(1);
        }

        $table = new Table($output);
        $table->setHeaders(['identifier', 'balance', '']);
        foreach ($wallets as $wallet) {
            $isDefault = isset($config['identifier']) && $config['identifier'] == $wallet['identifier'];

            $table->addRow([$wallet['identifier'], BlocktrailSDK::toBTCString($wallet['balance']), $isDefault ? 'IS_DEFAULT' : '']);
        }

        $table->render();

        if (count($wallets) >= $perpage) {
            $output->writeln("there are more wallets, use --page and --perpage to see all of them ...");
        }
    }
}
