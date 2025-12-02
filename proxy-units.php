<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$googleAppsScriptUrl = 'https://script.google.com/macros/s/AKfycbz_-tiYBDHaMa4O4Rk6bdgJagBMLHZDf5R3SJmuZyymEUXp5ipfA8q7QHT-kS8WkbLfxQ/exec';

try {
    $jsonData = file_get_contents($googleAppsScriptUrl);

    if ($jsonData === false) {
        throw new Exception('Unable to fetch data from Google Apps Script');
    }

    $units = json_decode($jsonData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received from Google Apps Script');
    }

    foreach ($units as &$unit) {
        $unit['building'] = (string)($unit['building'] ?? '');
        $unit['isleased'] = isset($unit['isleased']) ? (bool)$unit['isleased'] : false;
        if (!isset($unit['images']) || !is_array($unit['images'])) {
            $unit['images'] = [];
        }
        if (!isset($unit['description']) || !is_array($unit['description'])) {
            $unit['description'] = [];
        }
        if (isset($unit['unit']) && !empty($unit['unit']) && !str_starts_with($unit['unit'], 'Unit ')) {
            $unit['unit'] = 'Unit ' . $unit['unit'];
        }
    }

    echo json_encode($units);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load unit data: ' . $e->getMessage()]);
}
