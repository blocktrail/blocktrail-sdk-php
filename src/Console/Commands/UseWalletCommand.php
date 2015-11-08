<?php

namespace Blocktrail\SDK\Console\Commands;

use Blocktrail\SDK\BlocktrailSDKInterface;
use Blocktrail\SDK\Console\Application;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\Exceptions\WalletChecksumException;
use Blocktrail\SDK\WalletInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class UseWalletCommand extends AbstractCommand {

    const OPTION_MORE = "more ...";
    const OPTION_LESS = "less ...";
    const OPTION_FREEFORM = "let me type it";
    const OPTION_NO_DEFAULT = "no default";

    protected function configure() {
        $this
            ->setName('default_wallet')
            ->setDescription("Configure default wallet to use")
            ->addOption('identifier', null, InputOption::VALUE_REQUIRED, 'Wallet identifier')
            ->addOption('passphrase', null, InputOption::VALUE_REQUIRED, 'Wallet passphrase');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        parent::execute($input, $output);

        $sdk = $this->getBlocktrailSDK();
        $interactive = true; // @TODO;
        $identifier = trim($input->getOption('identifier'));

        if ($interactive) {
            if (!$identifier) {
                $identifier = $this->promptForIdentifier($input, $output, $sdk);
            }
        }

        if ($identifier) {
            try {
                $this->getBlocktrailSDK()->initWallet($identifier, "");
            } catch (WalletChecksumException $e) {
                // OK
            }
        }

        $this->updateConfig($input, [
            $this->getNetwork() => [
                'default_wallet' => $identifier
            ]
        ]);

        $output->writeln("<success>OK!</success>");
    }

    protected function promptForIdentifier($input, $output, BlocktrailSDKInterface $sdk) {
        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $identifier = null;

        $page = 1;
        $perpage = 50;

        while (!$identifier) {
            $wallets = $sdk->allWallets($page, $perpage)['data'];
            $fill = ($perpage * ($page - 1) + 1);
            $options = array_slice(
                array_merge(
                    array_fill(0, $fill, ''),
                    array_column($wallets, 'identifier')
                ),
                $fill,
                null,
                true
            );

            if (count($wallets) >= $perpage) {
                $options['more'] = self::OPTION_MORE;
            }
            if ($page > 1) {
                $options['less'] = self::OPTION_LESS;
            }

            $options['no'] = self::OPTION_NO_DEFAULT;
            $options['manual'] = self::OPTION_FREEFORM;

            $question = new ChoiceQuestion("Please select the wallet you'd like to use as default", $options, null);
            $question->setAutocompleterValues([]);
            $choice = $questionHelper->ask($input, $output, $question);

            if ($choice == self::OPTION_NO_DEFAULT) {
                $identifier = null;
                break;
            } else if ($choice == self::OPTION_FREEFORM) {
                $question = new Question("Please fill in the wallet identifier you'd like to use as default? ");
                $identifier = $questionHelper->ask($input, $output, $question);
            } else if ($choice == self::OPTION_MORE) {
                $page += 1;

            } else if ($choice == self::OPTION_LESS) {
                $page -= 1;

            } else {
                $identifier = $choice;
            }
        }

        return $identifier;
    }
}
