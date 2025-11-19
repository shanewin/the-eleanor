<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Your Google Apps Script URL - already returns formatted JSON
$googleAppsScriptUrl = 'https://script.google.com/macros/s/AKfycbz8HBkvSlt7Z2oyWCfjUPj9KQ1mBxtiNN5kfzveliN3SgWYKJ8FbdFTEYMUjYdPXaFfCQ/exec';

try {
    // Fetch the JSON data directly from your Google Apps Script
    $jsonData = file_get_contents($googleAppsScriptUrl);
    
    if ($jsonData === false) {
        throw new Exception('Unable to fetch data from Google Apps Script');
    }
    
    // Decode the JSON to validate it and potentially process it
    $units = json_decode($jsonData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received from Google Apps Script');
    }
    
    // Process the units to ensure data consistency
    foreach ($units as &$unit) {
        // Ensure building is string for consistency
        $unit['building'] = (string)($unit['building'] ?? '');
        
        // Ensure isleased is boolean
        if (isset($unit['isleased'])) {
            $unit['isleased'] = (bool)$unit['isleased'];
        } else {
            $unit['isleased'] = false;
        }
        
        // Ensure images is an array
        if (!isset($unit['images']) || !is_array($unit['images'])) {
            $unit['images'] = [];
        }
        
        // Ensure description is an array
        if (!isset($unit['description']) || !is_array($unit['description'])) {
            $unit['description'] = [];
        }
        
        // Add "Unit " prefix if not present in unit field
        if (isset($unit['unit']) && !empty($unit['unit']) && !str_starts_with($unit['unit'], 'Unit ')) {
            $unit['unit'] = 'Unit ' . $unit['unit'];
        }
    }
    
    // Return the processed data
    echo json_encode($units);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load unit data: ' . $e->getMessage()]);
}
