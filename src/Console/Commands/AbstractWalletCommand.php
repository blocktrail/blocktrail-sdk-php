<?php

namespace Blocktrail\SDK\Console\Commands;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\BlocktrailSDKInterface;
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

abstract class AbstractWalletCommand extends AbstractCommand {

    protected $identifier = null;
    protected $passphrase = null;

    protected function configure() {
        $this
            ->addOption('identifier', null, InputOption::VALUE_REQUIRED, 'Wallet identifier')
            ->addOption('passphrase', null, InputOption::VALUE_REQUIRED, 'Wallet passphrase');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        parent::execute($input, $output);

        $config = $this->getConfig($input);

        $this->identifier = $input->hasOptionInput('identifier') ? trim($input->getOption('identifier')) : (isset($config[$this->getNetwork()]['default_wallet']) ? $config[$this->getNetwork()]['default_wallet'] : null);

        if (!$this->identifier) {
            throw new \RuntimeException('indentifier is required.');
        }

        if ($input->hasOptionInput('passphrase')) {
            $this->passphrase = trim($input->getOption('passphrase'));
        } else if (isset($config[$this->getNetwork()]['wallet_passphrase'][$this->identifier])) {
            $this->passphrase = $config[$this->getNetwork()]['wallet_passphrase'][$this->identifier];
        }
    }

    protected function getWallet(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $sdk = $this->getBlocktrailSDK();

        while (!trim($this->passphrase)) {
            $question = new Question("<question>Please provide passphrase for wallet [<question-bold>{$this->identifier}</question-bold>]:</question> \n");
            $question->setHidden(true);
            $this->passphrase = $questionHelper->ask($input, $output, $question);
        }

        $wallet = $sdk->initWallet($this->identifier, $this->passphrase);

        $output->isVerbose() && $output->writeln("<success>Wallet initialized</success>");

        return $wallet;
    }
}
