<?php
require_once 'config.php';

echo "<h2>API Key Test</h2>";
echo "API Key: " . substr(AMADEUS_API_KEY_DB, 0, 10) . "...<br>";
echo "API Secret: " . substr(AMADEUS_API_SECRET_DB, 0, 10) . "...<br>";

// Test authentication
$auth_url = AMADEUS_BASE_URL . '/v1/security/oauth2/token';
$auth_data = [
    'grant_type' => 'client_credentials',
    'client_id' => AMADEUS_API_KEY_DB,
    'client_secret' => AMADEUS_API_SECRET_DB
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $auth_url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($auth_data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Auth Response Code: $http_code<br>";
echo "Auth Response: <pre>" . htmlspecialchars($response) . "</pre>";
?>