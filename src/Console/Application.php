<?php

namespace Blocktrail\SDK\Console;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\BlocktrailSDKInterface;
use Blocktrail\SDK\Console\Commands\BalanceCommand;
use Blocktrail\SDK\Console\Commands\ConfigureCommand;
use Blocktrail\SDK\Console\Commands\CreateNewWalletCommand;
use Blocktrail\SDK\Console\Commands\GetNewAddressCommand;
use Blocktrail\SDK\Console\Commands\ListWalletsCommand;
use Blocktrail\SDK\Console\Commands\PayCommand;
use Blocktrail\SDK\Console\Commands\UseWalletCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Application
 */
class Application extends ConsoleApplication {

    protected function getDefaultCommands() {
        $commands = parent::getDefaultCommands();

        $commands[] = new CreateNewWalletCommand();
        $commands[] = new GetNewAddressCommand();
        $commands[] = new ConfigureCommand();
        $commands[] = new UseWalletCommand();
        $commands[] = new ListWalletsCommand();
        $commands[] = new PayCommand();
        $commands[] = new BalanceCommand();

        return $commands;
    }

    public function run(InputInterface $input = null, OutputInterface $output = null) {
        if (null === $input) {
            $input = new ArgvInput();
        }

        if (null === $output) {
            $output = new ConsoleOutput();
        }

        $output->getFormatter()->setStyle('success', new OutputFormatterStyle('green', null, ['bold']));
        $output->getFormatter()->setStyle('bold', new OutputFormatterStyle(null, null, ['bold']));
        $output->getFormatter()->setStyle('info-bold', new OutputFormatterStyle('green', null, ['bold']));
        $output->getFormatter()->setStyle('question-bold', new OutputFormatterStyle('yellow', 'cyan', ['bold']));
        $output->getFormatter()->setStyle('comment-bold', new OutputFormatterStyle('yellow', null, ['bold']));

        return parent::run($input, $output);
    }
}
