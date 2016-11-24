<?php

$sdkTarget = '@dev';
if ($argc > 1) {
  $sdkTarget = $argv[1];
}

$builds = [
  'laravel/framework' => ['5.3', '5.2']
];

function checkBuilds (array $builds) {
  foreach ($builds as $framework => $listedVersions) {
    if (!is_array($listedVersions)) {
      error_log('MALFORMED FRAMEWORKS MATRIX');
      exit(-1);
    }
  }
}

function runFrameworkTest($sdkTarget, $testFramework, $testFrameworkVersion)
{
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

    $output = null;
    $ret = 0;
    mkdir('framework');
    chdir('./framework');
    file_put_contents('composer.json', $composer);
    exec('composer install', $output);

    $output = null;
    exec('composer require blocktrail/blocktrail-sdk ' . $sdkTarget, $output, $ret);
    chdir('..');
    exec('rm -rf framework');

    return $ret === 0;
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
    echo "Some tests failed!";
    exit(-1);
  }

  exit(0);
}

build($sdkTarget, $builds);
