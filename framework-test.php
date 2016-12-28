<?php

$sdkTarget = '@dev';
if ($argc > 1) {
    $sdkTarget = $argv[1];
}

$builds = [
    'codeigniter/framework' => [
        '3.1.*', '3.0.*',
    ],
    'slim/slim' => [
        '3.7.*', '3.6.*', '3.5.*', '3.4.*', '3.3.*', '3.2.*', '3.1.*', '3.0.*', '2.6.*',
        '2.5.*', '2.4.*', '2.3.*'
    ],
    'laravel/framework' => [
        '5.3.*', '5.2.*'
    ],
    'symfony/symfony' => [
        '3.2.*', '3.1.*', '3.0.*', '2.8.*'
    ],
    'silex/silex' => [
        '2.0.*', '1.3.*',
    ],
    'fuel/fuel' => [
        '1.8.*'
    ],
    'yiisoft/yii' => [
        '1.1.*'
    ],
    'yiisoft/yii2' => [
        '2.0.*'
    ],
    'cakephp/cakephp' => [
        '3.3.*', '3.2.*',
    ]
];

function checkBuilds (array $builds) {
  foreach ($builds as $framework => $listedVersions) {
    if (!is_array($listedVersions)) {
      error_log('MALFORMED FRAMEWORKS MATRIX');
      exit(-1);
    }
  }
}

$outputLog = '';

function runFrameworkTest($sdkTarget, $testFramework, $testFrameworkVersion)
{

    $framework = "{$testFramework} {$testFrameworkVersion}";

  $composer = <<<EOF
{
  "name": "integration tester",
  "repositories": [
    {
      "type": "vcs",
      "url": "../"
    }
  ],
  "require": {
    "$testFramework": "$testFrameworkVersion"
  }
}
EOF;

    mkdir('framework');
    chdir('./framework');
    file_put_contents('composer.json', $composer);

    $returnCode = 0;
    $output = [];
    $outputLog = null;

    exec('composer install 2>&1', $output, $returnCode);
    if ($returnCode !== 0) {
        $outputLog .= "###################### Failure: Installing framework {$framework} ###################### \n\n" . implode("\n", $output);
    } else {
        $returnCode = 0;
        $output = [];
        exec('composer require blocktrail/blocktrail-sdk ' . $sdkTarget . ' 2>&1', $output, $returnCode);
        if ($returnCode !== 0) {
            $outputLog .= "###################### Failure: Installing SDK with {$framework} ###################### \n\n" . implode("\n", $output);
        }
    }

    cleanup();

    $ok = $returnCode === 0;
    echo "Testing {$framework} with target {$sdkTarget}:  " . ($ok ? 'pass' : 'FAIL') . PHP_EOL;
    if (!$ok) {
        echo $outputLog . PHP_EOL;
    }

    return $ok;
}

function cleanup() {
    chdir('..');
    exec('rm -rf framework');
}

function build($sdkTarget, array $builds) {
  checkBuilds($builds);
  $results = [];
  foreach ($builds as $framework => $testVersions) {
    foreach ($testVersions as $testVersion) {
      $results[$framework . ":" . $testVersion] = runFrameworkTest($sdkTarget, $framework, $testVersion);
    }
  }

  $ok = true;
  foreach ($results as $result) {
    $ok = $ok && $result;
  }

  exit($ok ? 0 : -1);
}

build($sdkTarget, $builds);
