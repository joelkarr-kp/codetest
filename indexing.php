<?php
require_once 'google-api-php-client/vendor/autoload.php';

$client = new Google_Client();

// service_account_file.json is the private key that you created for your service account.
$client->setAuthConfig('indexing-377804-979f1201593a.json');
$client->addScope('https://www.googleapis.com/auth/indexing');

// Get a Guzzle HTTP Client
$httpClient = $client->authorize();
$endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

// Define contents here. The structure of the content is described in the next step.
$content = '{
  "url": "https://www.beckershospitalreview.com/public-health.html",
  "type": "URL_UPDATED"
}';

$response = $httpClient->post($endpoint, [ 'body' => $content ]);
$status_code = $response->getStatusCode();

?>