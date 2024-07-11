<?php
require_once __DIR__ . '/vendor/autoload.php';

use Czdb\DbSearcher;

// Initialize the DbSearcher with the database path and mode.
$dbSearcher = new DbSearcher("/Users/liucong/Downloads/tony/ipv4.czdb", "BTREE", "UBN0Iz3juX2qjK3sWbwcHQ==");

while (true) {
    $ip = readline("Enter IP address (or type 'q' to quit): ");

    if (strtolower($ip) === 'q') {
        break;
    }

    $startTime = microtime(true);

    try {
        $region = $dbSearcher->search($ip);
        // Measure the end time and calculate the duration.
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Print the search results and the duration.
        echo "Search Results:\n";
        print_r($region);
        echo "\nQuery Duration: " . number_format($duration, 4) . " seconds\n";
    } catch (Exception $e) {
        // Handle the exception and inform the user
        echo "An error occurred during the search: " . $e->getMessage() . "\nPlease try again.\n";
    }
}

$dbSearcher->close();