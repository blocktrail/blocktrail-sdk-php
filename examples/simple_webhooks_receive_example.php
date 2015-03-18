<?php

use Blocktrail\SDK\BlocktrailSDK;

require_once __DIR__ . "/../vendor/autoload.php";

/*
    This simple example shows how to get the payload when your webhook is called.

    Set up the webhook before hand (see simple_webhooks_setup_example.php) with the webhook url pointing to this script,
    either through an ngrok tunnel or with a publically accessible development server.
    -----
    $client = new BlocktrailSDK("YOUR_APIKEY_HERE", "YOUR_APISECRET_HERE", "BTC", true, 'v1');
    $client->setupWebhook("http://oisin.ngrok.com/examples/simple_webhooks_receive_example.php", "oisin-ngrok-webhooks-example");


    Then you can either use the BlockTrail webhook tester (access from the dashboard when you login), or you could
    subscribe to block events, address transactions or a particular transaction hash and have your webhook fire naturally.
    -----
    $client->subscribeAddressTransactions("oisin-ngrok-webhooks-example", "2N6Fg6T74Fcv1JQ8FkPJMs8mYmbm9kitTxy");
    $client->subscribeNewBlocks("oisin-ngrok-webhooks-example");

    (Alternatively you can create a wallet webhook and receive notifications on your wallet activity.)
*/


// get the data POSTed to us when this webhook is fired
$payload = BlocktrailSDK::getWebhookPayload();

// we now have access to a block payload or transaction payload. See the API Docs for the full schemas.
if ($payload['event_type'] == "block") {
    //do stuff with the newly found block data
} elseif ($payload['event_type'] == "address-transactions") {
    //do stuff with the transaction data for the subscribed address(es)
} elseif ($payload['event_type'] == "transaction") {
    //do stuff with the transaction data for the subscribed transaction
}

// use the BlockTrail webhook tester and you'll see the full payload printed out
print_r($payload);



