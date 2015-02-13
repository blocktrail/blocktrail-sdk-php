<?php

/**
 * source from http://ocramius.github.io/blog/automated-code-coverage-check-for-github-pull-requests-with-travis/
 */

// for w/e reason HHVM reports a lot lower coverage (probably because of the JIT) so we're skipping this
if (defined('HHVM_VERSION')) {
    exit(0);
}

$inputFile  = $argv[1];
$percentage = min(100, max(0, (int) $argv[2]));

if (!file_exists($inputFile)) {
    throw new InvalidArgumentException('Invalid input file provided');
}

if (!$percentage) {
    throw new InvalidArgumentException('An integer checked percentage must be given as second parameter');
}

$xml = new SimpleXMLElement(file_get_contents($inputFile));

$totalElements   = 0;
$checkedElements = 0;

$excludes = [
    "/^Blocktrail\\\\SDK\\\\Console/",  // just wrappers and a pain in the ass to test
    "/Compiler\\.php/",                 // just a tool and copy paste from Composer
    "/BackupGenerator\\.php/",          // tmp excluding, it's rather hard to test the whole process
    "/WalletSweeper\\.php/",            // because we're running it's test without coverage (because xdebug is REALLY slow)
];

foreach ($xml->xpath("//package") as $package) {
    echo " + PACKAGE [[ {$package['name']} ]] ";

    $excluded = false;
    foreach ($excludes as $exclude) {
        if (preg_match($exclude, (string)$package['name'])) {
            $excluded = true;
        }
    }

    if ($excluded) {
        echo "** EXCLUDED! \n";
        continue;
    } else {
        echo "\n";
    }

    $packageXML = new SimpleXMLElement($package->asXML());

    foreach ($packageXML->xpath("//file") as $file) {
        echo " - FILE [[ {$file['name']} ]] ";

        $excluded = false;
        foreach ($excludes as $exclude) {
            if (preg_match($exclude, (string)$file['name'])) {
                $excluded = true;
            }
        }

        if ($excluded) {
            echo "** EXCLUDED! \n";
            continue;
        }

        $fileXML = new SimpleXMLElement($file->asXML());

        $metric = $fileXML->xpath("//file/metrics")[0];
        $elements = (int)$metric['elements'];
        $checked = (int)$metric['coveredelements'];

        $coverage = $elements > 0 ? round(($checked / $elements) * 100, 2) : '-';

        echo "{$coverage}% \n";

        $totalElements += (int)$elements;
        $checkedElements += (int)$checked;
    }
}

foreach ($xml->xpath("//project/file/metrics") as $metric) {
    $totalElements   += (int) $metric['elements'];
    $checkedElements += (int) $metric['coveredelements'];
}

var_dump($totalElements);
var_dump((int)$xml->xpath("//project/metrics")[0]['elements']);

$coverage = ($checkedElements / $totalElements) * 100;

if ($coverage < $percentage) {
    echo "Code coverage is {$coverage}%, which is below the accepted {$percentage}% \n";
    exit(1);
}

echo "Code coverage is {$coverage}% - OK! \n";
