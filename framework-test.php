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
    global $outputLog;
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
    exec('composer install 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        $outputLog .= explode("\n", $output);
        cleanup();
        return false;
    }

    exec('composer require blocktrail/blocktrail-sdk ' . $sdkTarget . ' 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        $outputLog .= explode("\n", $output);
    }

    cleanup();

    return $returnCode === 0;
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
  foreach ($results as $title => $result) {
    echo "Test of $title was " . ($result ? 'successful' : 'UNSUCCESSFUL') . PHP_EOL;
    $ok = $ok && $result;
  }

  if (!$ok) {
      global $errorLog;
      echo "[DEBUG LOG]\n";
      echo $errorLog . PHP_EOL;
      exit(-1);
  }

  exit(0);
}

build($sdkTarget, $builds);
