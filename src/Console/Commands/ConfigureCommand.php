<?php

namespace Blocktrail\SDK\Console\Commands;

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

class ConfigureCommand extends AbstractCommand {

    protected function configure() {
        $this
            ->setName('configure')
            // ->setAliases(['setup'])
            ->setDescription("Configure credentials");

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $interactive = true; // @TODO
        $apiKey = trim($input->getOption('api_key'));
        $apiSecret = trim($input->getOption('api_secret'));
        $testnet = !!$input->getOption('testnet');

        if ($interactive) {
            if (!$apiKey) {
                $question = new Question("<question>Set the default API_KEY to use (blank to not set a default API_KEY):</question> \n");
                $apiKey = $questionHelper->ask($input, $output, $question);
            }
            if (!$apiSecret) {
                $question = new Question("<question>Set the default API_SECRET to use (blank to not set a default API_SECRET):</question> \n");
                $apiSecret = $questionHelper->ask($input, $output, $question);
            }

            if (!$testnet) {
                $question = new ConfirmationQuestion("<question>Set weither to use TESTNET by default? [y/N]</question> \n", false);
                $testnet = $questionHelper->ask($input, $output, $question);
            }
        }

        $this->replaceConfig($input, [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'testnet' => $testnet
        ]);
    }
}
