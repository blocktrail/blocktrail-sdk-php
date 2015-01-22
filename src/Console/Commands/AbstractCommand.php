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

abstract class AbstractCommand extends Command {

    protected $apiKey = null;
    protected $apiSecret = null;
    protected $testnet = null;

    /**
     * @return Application
     */
    public function getApplication() {
        return parent::getApplication();
    }

    protected function configure() {
        $this
            ->addOption('api_key', null, InputOption::VALUE_REQUIRED, 'API_KEY to be used')
            ->addOption('api_secret', null, InputOption::VALUE_REQUIRED, 'API_SECRET to be used')
            ->addOption('testnet', null, InputOption::VALUE_NONE, 'use testnet instead of mainnet');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        /** @var Output $output */
        $config = $this->getConfig();

        $this->apiKey = $input->hasOptionInput('api_key') ? trim($input->getOption('api_key')) : (isset($config['api_key']) ? $config['api_key'] : null);
        $this->apiSecret = $input->hasOptionInput('api_secret') ? trim($input->getOption('api_secret')) : (isset($config['api_secret']) ? $config['api_secret'] : null);
        $this->testnet = $input->hasOptionInput('testnet') ? $input->getOption('testnet') : (isset($config['testnet']) ? $config['testnet'] : false);

        if (!$this->apiKey) {
            throw new \RuntimeException('API_KEY is required.');
        }
        if (!$this->apiSecret) {
            throw new \RuntimeException('API_SECRET is required.');
        }
    }

    /**
     * @return BlocktrailSDKInterface
     */
    public function getBlocktrailSDK() {
        return new BlocktrailSDK($this->apiKey, $this->apiSecret, "BTC", $this->testnet);
    }

    public function getConfig() {
        $dir = "{$_SERVER['HOME']}/.blocktrail";
        $file = "{$dir}/config.json";

        if (!file_exists($file)) {
            return [];
        }

        return json_decode(file_get_contents($file), true);
    }

    public function replaceConfig(array $config) {
        $dir = "{$_SERVER['HOME']}/.blocktrail";

        if (!file_exists($dir)) {
            mkdir($dir);
        }

        file_put_contents("{$dir}/config.json", json_encode($config));

        return null;
    }

    public function updateConfig(array $config) {
        return $this->replaceConfig(array_replace($this->getConfig(), $config));
    }
}
