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
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class ListWalletsCommand extends AbstractCommand {

    protected function configure() {
        $this
            ->setName('list_wallets')
            // ->setAliases(['create_new_wallet', 'create_wallet'])
            ->setDescription("List all wallets");

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        parent::execute($input, $output);

        $sdk = $this->getBlocktrailSDK();
        $config = $this->getConfig();

        // $wallets = $sdk->getWallets();
        $wallets = [
            ['identifier' => 'cli-created-wallet', 'balance' => BlocktrailSDK::toSatoshi(28311.3238283)],
            ['identifier' => 'another-cli-wallet', 'balance' => BlocktrailSDK::toSatoshi(0.32)],
        ];

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
    }
}
