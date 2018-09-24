<?php

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://wallet-api.btc.com");
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($handle, CURLOPT_STDERR, $verbose);

$output = curl_exec($ch);
if ($result === FALSE) {
    printf("cUrl error (#%d): %s<br>\n", curl_errno($handle),
           htmlspecialchars(curl_error($handle)));
} else {
    echo $output;
}
rewind($verbose);
$verboseLog = stream_get_contents($verbose);

echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
curl_close($ch);
