<?php

namespace Blocktrail\SDK\Console\Commands;

use Blocktrail\SDK\Console\Application;
use Blocktrail\SDK\WalletInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class UseWalletCommand extends AbstractCommand {

    protected function configure() {
        $this
            ->setName('default_wallet')
            ->setDescription("Configure default wallet to use")
            ->addOption('identifier', null, InputOption::VALUE_REQUIRED, 'Wallet identifier')
            ->addOption('passphrase', null, InputOption::VALUE_REQUIRED, 'Wallet passphrase');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $interactive = true; // @TODO;
        $identifier = trim($input->getOption('identifier'));
        $passphrase = trim($input->getOption('passphrase'));

        if ($interactive) {
            if (!$identifier) {
                $question = new Question("<question>Set the default wallet identifier to use (blank to not set a default identifier):</question> \n");
                $identifier = $questionHelper->ask($input, $output, $question);
            }
            if (!$passphrase) {
                $question = new Question("<question>Set the default wallet passphrase to use (blank to not set a default passphrase):</question> \n");
                $question->setHidden(true);
                $passphrase = $questionHelper->ask($input, $output, $question);
            }
        }

        $this->updateConfig([
            'identifier' => $identifier,
            'passphrase' => $passphrase,
        ]);
    }
}
