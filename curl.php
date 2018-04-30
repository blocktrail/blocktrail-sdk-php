<?php


$sslVersions = [
    CURL_SSLVERSION_DEFAULT,
    CURL_SSLVERSION_TLSv1,
    CURL_SSLVERSION_TLSv1_0,
    CURL_SSLVERSION_TLSv1_1,
    CURL_SSLVERSION_TLSv1_2,
    CURL_SSLVERSION_SSLv2,
    CURL_SSLVERSION_SSLv3,
];

var_dump(curl_version());

foreach ($sslVersions as $sslVersion) {
    $ch = curl_init("https://api.blocktrail.com/v1/BTC/price?api_key=MY_APIKEY");

    curl_setopt_array($ch, [
        CURLOPT_VERBOSE => true,
        CURLOPT_CERTINFO => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FAILONERROR => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HEADER => true,
        CURLOPT_SSLVERSION => $sslVersion,
    ]);

    $result = curl_exec($ch);
    $info = curl_getinfo($ch);

    curl_close($ch);

    var_dump($result);
}
