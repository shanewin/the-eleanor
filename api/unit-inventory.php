<?php
/**
 * Unit Inventory — fetches the Google Sheet of available units.
 * Cached for 5 minutes on disk. Used by SMS AI, lead grading, and anywhere
 * else that needs to know current pricing/availability.
 */

if (!function_exists('fetchAvailableUnits')) {
    function fetchAvailableUnits() {
        $cacheFile = sys_get_temp_dir() . '/eleanor_units_cache.json';
        $cacheTTL = 300; // 5 minutes

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) return $cached;
        }

        $endpoint = 'https://script.google.com/macros/s/AKfycbz_-tiYBDHaMa4O4Rk6bdgJagBMLHZDf5R3SJmuZyymEUXp5ipfA8q7QHT-kS8WkbLfxQ/exec';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || empty($response)) {
            error_log("Failed to fetch Google Sheet units ($httpCode)");
            if (file_exists($cacheFile)) {
                return json_decode(file_get_contents($cacheFile), true) ?: [];
            }
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) return [];

        $units = [];
        foreach ($data as $unit) {
            $units[] = [
                'unit'     => $unit['unit'] ?? '',
                'type'     => $unit['type'] ?? '',
                'bedbath'  => $unit['bedbath'] ?? $unit['bedBath'] ?? '',
                'rent'     => preg_replace('/[^0-9]/', '', $unit['rent'] ?? ''),
                'sqft'     => $unit['squarefootage'] ?? $unit['sqft'] ?? '',
                'outdoor'  => $unit['outdoor'] ?? '',
                'view'     => $unit['view'] ?? '',
                'isleased' => !empty($unit['isleased']),
            ];
        }

        file_put_contents($cacheFile, json_encode($units));
        return $units;
    }
}

/**
 * Compute an implied monthly budget for a lead when they didn't submit one.
 *
 * Priority:
 *   1. If lead has a specific unit (Unit Interest form) → that unit's rent
 *   2. If lead has a unit_type preference → minimum rent in that type
 *   3. Otherwise → minimum rent in the portfolio (most conservative)
 *
 * Returns: ['budget' => float, 'source' => string] or null if no inventory.
 */
if (!function_exists('getImpliedBudgetForLead')) {
    function getImpliedBudgetForLead($lead) {
        $units = fetchAvailableUnits();
        if (empty($units)) return null;

        $available = array_filter($units, function($u) {
            return !$u['isleased'] && !empty($u['rent']) && is_numeric($u['rent']);
        });
        if (empty($available)) {
            // Fall back to all units if nothing marked available
            $available = array_filter($units, function($u) {
                return !empty($u['rent']) && is_numeric($u['rent']);
            });
            if (empty($available)) return null;
        }

        // 1. Specific unit on the lead
        $unitNumber = trim((string)($lead['unit'] ?? ''));
        if ($unitNumber !== '') {
            foreach ($units as $u) {
                if (strcasecmp((string)$u['unit'], $unitNumber) === 0 && !empty($u['rent'])) {
                    return [
                        'budget' => (float)$u['rent'],
                        'source' => "Unit {$unitNumber}'s listed rent"
                    ];
                }
            }
        }

        // 2. Unit type preference (Waitlist usually)
        $typePref = strtolower(trim((string)($lead['unit_type'] ?? '')));
        if ($typePref !== '') {
            $matches = array_filter($available, function($u) use ($typePref) {
                return stripos(strtolower($u['type']), $typePref) !== false
                    || stripos(strtolower($u['bedbath']), $typePref) !== false;
            });
            if (!empty($matches)) {
                $minRent = min(array_map(fn($u) => (float)$u['rent'], $matches));
                return [
                    'budget' => $minRent,
                    'source' => "lowest {$typePref} in portfolio"
                ];
            }
        }

        // 3. Portfolio minimum (most conservative — can they afford the cheapest unit?)
        $minRent = min(array_map(fn($u) => (float)$u['rent'], $available));
        return [
            'budget' => $minRent,
            'source' => "lowest available unit in portfolio"
        ];
    }
}
