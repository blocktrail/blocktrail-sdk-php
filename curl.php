<?php

$ch = curl_init("https://api.blocktrail.com/v1/BTC/price?api_key=MY_APIKEY");

curl_setopt_array($ch, [
    CURLOPT_VERBOSE        => true,
    CURLOPT_CERTINFO       => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FAILONERROR    => false,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HEADER         => true,
]);

$result = curl_exec($ch);
$info   = curl_getinfo($ch);

curl_close($ch);

var_dump($result);
