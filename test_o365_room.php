<?php

$tenantId = '79c62162-b85e-4b0e-a863-ebe7817ad70d';
$clientId = '91ed54f9-d6d9-4d0b-b7aa-b4beb8b97320';
$clientSecret = 'HWG8Q~63npk2DP6RLrqQscbPXlNDETxp1Xib-a9H';
$roomEmail = 'room.mexicocity@flydenver.com';

echo "Testing Microsoft Graph API room calendar access...\n\n";

// Step 1: Get app access token
echo "Step 1: Getting app access token...\n";
$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
$tokenData = http_build_query([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'scope' => 'https://graph.microsoft.com/.default',
    'grant_type' => 'client_credentials',
]);

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $tokenData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$tokenResponse = curl_exec($ch);
$tokenStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tokenStatus !== 200) {
    echo "ERROR: Failed to get access token\n";
    echo "Status: $tokenStatus\n";
    echo "Response: $tokenResponse\n";
    exit(1);
}

$tokenJson = json_decode($tokenResponse, true);
$token = $tokenJson['access_token'];
echo "✓ Access token obtained\n\n";

// Step 2: Get room calendar
echo "Step 2: Getting room calendar info...\n";
$calendarUrl = "https://graph.microsoft.com/v1.0/users/{$roomEmail}/calendar";

$ch = curl_init($calendarUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    'Content-Type: application/json'
]);
$calendarResponse = curl_exec($ch);
$calendarStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($calendarStatus !== 200) {
    echo "ERROR: Failed to get room calendar\n";
    echo "Status: $calendarStatus\n";
    echo "Response: $calendarResponse\n";
    exit(1);
}

$calendar = json_decode($calendarResponse, true);
echo "✓ Room calendar retrieved\n";
echo "  ID: " . ($calendar['id'] ?? 'N/A') . "\n";
echo "  Name: " . ($calendar['name'] ?? 'N/A') . "\n";
echo "  Owner: " . ($calendar['owner']['name'] ?? 'N/A') . "\n\n";

// Step 3: Get room events
echo "Step 3: Getting room events...\n";
$start = (new DateTime())->format('Y-m-d\TH:i:s\Z');
$end = (new DateTime())->modify('+30 days')->format('Y-m-d\TH:i:s\Z');

$eventsUrl = "https://graph.microsoft.com/v1.0/users/{$roomEmail}/calendar/calendarView?startDateTime={$start}&endDateTime={$end}&\$orderby=start/dateTime";

$ch = curl_init($eventsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    'Content-Type: application/json'
]);
$eventsResponse = curl_exec($ch);
$eventsStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($eventsStatus !== 200) {
    echo "ERROR: Failed to get room events\n";
    echo "Status: $eventsStatus\n";
    echo "Response: $eventsResponse\n";
    exit(1);
}

$eventsJson = json_decode($eventsResponse, true);
$events = $eventsJson['value'] ?? [];
echo "✓ Events retrieved: " . count($events) . " events found\n\n";

if (count($events) > 0) {
    echo "Sample events:\n";
    foreach (array_slice($events, 0, 3) as $event) {
        echo "  - " . $event['subject'] . "\n";
        echo "    Start: " . $event['start']['dateTime'] . "\n";
        echo "    End: " . $event['end']['dateTime'] . "\n\n";
    }
}

echo "\n✓ All tests passed! The O365 room calendar integration is working.\n";
