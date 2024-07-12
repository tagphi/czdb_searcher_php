<?php
require_once __DIR__ . '/vendor/autoload.php';

use Czdb\DbSearcher;

// Check if the correct number of arguments are passed
if ($argc !== 4) {
    echo "Usage: php " . $argv[0] . " <database_path> <query_type> <key>\n";
    exit(1);
}

// Read the database path, query type, and key from command line arguments
$databasePath = $argv[1];
$queryType = $argv[2];
$key = $argv[3];

// Initialize the DbSearcher with the command line arguments
$dbSearcher = new DbSearcher($databasePath, $queryType, $key);

while (true) {
    $ip = readline("Enter IP address (or type 'q' to quit): ");

    if (strtolower($ip) === 'q') {
        break;
    }

    $startTime = microtime(true);

    try {
        $region = $dbSearcher->search($ip);
        // Measure the end time and calculate the duration
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Print the search results and the duration
        echo "Search Results:\n";
        print_r($region);
        echo "\nQuery Duration: " . number_format($duration, 4) . " seconds\n";
    } catch (Exception $e) {
        // Handle the exception and inform the user
        echo "An error occurred during the search: " . $e->getMessage() . "\nPlease try again.\n";
    }
}

$dbSearcher->close();