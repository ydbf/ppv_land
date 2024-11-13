<?php

// Step 1: Fetch and decode the data from ppv.land API to get the 4-digit IDs
$url = "https://ppv.land/api/streams";
$response = file_get_contents($url);
$data = json_decode($response, true);

// Initialize an array to hold the 4-digit IDs
$ids = [];

// Loop through the 'streams' array to extract 4-digit ids
foreach ($data['streams'] as $category) {
    foreach ($category['streams'] as $stream) {
        if (isset($stream['id']) && strlen((string)$stream['id']) === 4) {
            // Collect 4-digit IDs
            $ids[] = $stream['id'];
        }
    }
}

// Step 2: Create the links using the 4-digit IDs and scrape data from each link
$linkInfo = [];
foreach ($ids as $id) {
    // Fetch the JSON data from the URL
    $jsonData = file_get_contents("https://ppv.land/api/streams/$id");

    // Check if data was retrieved
    if ($jsonData === false) {
        echo "Failed to fetch data from: https://ppv.land/api/streams/$id\n";
        continue; // Skip this link and continue to the next one
    }

    // Decode the JSON data
    $data = json_decode($jsonData, true);

    // Check if the data is valid JSON
    if ($data === null) {
        echo "Invalid JSON data from: https://ppv.land/api/streams/$id\n";
        continue; // Skip this link if the data is not valid JSON
    }

    // Append the data to the linkInfo array
    $linkInfo[] = $data;
}

// Step 3: Create an associative array to map each stream ID to its category
$categoryMap = [];
foreach ($data['streams'] as $streamGroup) {
    foreach ($streamGroup['streams'] as $stream) {
        $categoryMap[$stream['id']] = $streamGroup['category'];
    }
}

// Set the default timezone to Australia/Sydney
date_default_timezone_set('Australia/Sydney');

// Open the M3U file for writing
$m3uFile = fopen('streams.m3u', 'w') or die('Error opening M3U file for writing.');

// Write the M3U header
fwrite($m3uFile, "#EXTM3U\n");

// Define the User-Agent string
$userAgent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15";

// Define the origin and referrer
$origin = "https://ppv.land";
$referrer = "https://ppv.land";

// Loop through each entry in the link_info and create M3U entries
foreach ($linkInfo as $entry) {
    $data = $entry['data'];
    $name = $data['name'];
    $poster = $data['poster'];
    $m3u8Link = $data['m3u8'];
    $id = $data['id'];

    // Get the category for this stream based on its ID
    $category = isset($categoryMap[$id]) ? $categoryMap[$id] : 'Uncategorized';

    // Convert start timestamp to the desired format: "Time AM/PM (d/m/y)"
    $startTime = date('h:i A', $data['start_timestamp']) . ' (' . date('d/m/y', $data['start_timestamp']) . ')';

    // Create and write the M3U entry with tvg-logo, group-title, and User-Agent (as EXTVLCOPT)
    $m3uEntry = "#EXTINF:-1 tvg-logo=\"$poster\" group-title=\"$category\", $name - $startTime\n";
    $m3uEntry .= "#EXTVLCOPT:http-user-agent=$userAgent\n";  // Correct User-Agent format using http-user-agent
    $m3uEntry .= "#EXTVLCOPT:http-origin=$origin\n";  // Add the http-origin
    $m3uEntry .= "#EXTVLCOPT:http-referrer=$referrer\n";  // Add the http-referrer
    $m3uEntry .= "$m3u8Link\n";

    // Write the M3U entry to the file
    fwrite($m3uFile, $m3uEntry);
}

// Close the M3U file
fclose($m3uFile);

echo "M3U file created successfully as streams.m3u.\n";

?>
