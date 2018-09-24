<?php

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://wallet-api.btc.com");
$output = curl_exec($ch);
curl_close($ch);
echo $output;
