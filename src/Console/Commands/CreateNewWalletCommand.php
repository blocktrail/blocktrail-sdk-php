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

class CreateNewWalletCommand extends AbstractCommand {

    protected function configure() {
        $this
            ->setName('create_new_wallet')
            // ->setAliases(['create_new_wallet', 'create_wallet'])
            ->setDescription("Create a new wallet")
            ->addArgument('identifier', InputArgument::REQUIRED, 'A unique identifier to be used as wallet identifier')
            ->addArgument('passphrase', InputArgument::OPTIONAL, 'A strong passphrase to be used as wallet passphrase');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        parent::execute($input, $output);

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $identifier = trim($input->getArgument('identifier'));
        $passphrase = trim($input->getArgument('passphrase'));

        if (!$identifier) {
            $output->writeln("<error>Identifier is required!</error>");
            exit(1);
        }

        $output->writeln("<comment>Creating wallet with identifier [<comment-bold>{$identifier}</comment-bold>]</comment>");

        while (!$passphrase) {
            do {
                $question = new Question("<question>Please choose a strong passphrase for the wallet:</question> \n");
                $question->setHidden(true);
                $passphrase1 = $questionHelper->ask($input, $output, $question);
            } while (!trim($passphrase1));

            do {
                $question = new Question("<question>Please repeat the passphrase for the wallet:</question> \n");
                $question->setHidden(true);
                $passphrase2 = $questionHelper->ask($input, $output, $question);
            } while (!trim($passphrase2));

            if ($passphrase1 != $passphrase2) {
                $output->writeln("<error>Both passwords must be the same, please try again!</error> \n");
            } else {
                $passphrase = trim($passphrase1);
            }
        }

        $sdk = $this->getBlocktrailSDK();

        /** @var WalletInterface $wallet */
        list($wallet, $primaryMnemonic, $backupMnemonic) = $sdk->createNewWallet($identifier, $passphrase, 9999);

        $output->writeln("<success>Wallet created</success>");

        $output->writeln("<comment>Make sure to backup the following information somewhere safe;</comment>");
        $output->writeln("<bold>Primary Mnemonic:</bold>\n {$primaryMnemonic}");
        $output->writeln("<bold>Backup Mnemonic:</bold>\n {$backupMnemonic}");

        while (!$questionHelper->ask($input, $output, new ConfirmationQuestion("Did you store the information? [y/N] ", false))) {
            $output->writeln("...");
        }

        $output->writeln("<success>DONE!</success>");
    }
}
