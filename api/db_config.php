<?php
/**
 * Supabase REST API Client
 * Replaces direct PDO connection with HTTP requests to Supabase PostgREST.
 */
require_once __DIR__ . '/config.php';

class Supabase {
    private $url;
    private $key;

    public function __construct() {
        $this->url = SUPABASE_URL . '/rest/v1';
        $this->key = SUPABASE_SECRET_KEY;
    }

    private function request($method, $endpoint, $body = null, $headers = []) {
        $ch = curl_init($this->url . '/' . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $defaultHeaders = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        if ($headers) {
            $defaultHeaders = array_merge($defaultHeaders, $headers);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $httpCode, 'data' => json_decode($response, true), 'raw' => $response];
    }

    /**
     * SELECT from a table.
     * @param string $table
     * @param string $select Columns (default '*')
     * @param array $filters ['column=eq.value', 'name=ilike.*shane*']
     * @param string $order 'created_at.desc'
     * @param int $limit
     */
    public function select($table, $select = '*', $filters = [], $order = null, $limit = null) {
        $params = ['select=' . urlencode($select)];
        foreach ($filters as $f) $params[] = $f;
        if ($order) $params[] = 'order=' . $order;
        if ($limit) $params[] = 'limit=' . $limit;

        $endpoint = $table . '?' . implode('&', $params);
        $res = $this->request('GET', $endpoint);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            error_log("Supabase SELECT $table failed ({$res['code']}): {$res['raw']}");
            return [];
        }
        return is_array($res['data']) ? $res['data'] : [];
    }

    /**
     * SELECT single row.
     */
    public function selectOne($table, $select = '*', $filters = []) {
        $rows = $this->select($table, $select, $filters, null, 1);
        return $rows[0] ?? null;
    }

    /**
     * INSERT into a table.
     */
    public function insert($table, $data) {
        $res = $this->request('POST', $table, $data);
        if ($res['code'] >= 200 && $res['code'] < 300) {
            return $res['data'][0] ?? $res['data'] ?? true;
        }
        error_log("Supabase INSERT $table failed ({$res['code']}): {$res['raw']}");
        return false;
    }

    /**
     * UPDATE rows in a table.
     * @param string $table
     * @param array $data Key-value pairs to update
     * @param array $filters PostgREST filters
     */
    public function update($table, $data, $filters = []) {
        $endpoint = $table . '?' . implode('&', $filters);
        $res = $this->request('PATCH', $endpoint, $data);
        if ($res['code'] >= 200 && $res['code'] < 300) {
            return $res['data'] ?? true;
        }
        error_log("Supabase UPDATE $table failed ({$res['code']}): {$res['raw']}");
        return false;
    }

    /**
     * DELETE rows from a table.
     */
    public function delete($table, $filters = []) {
        $endpoint = $table . '?' . implode('&', $filters);
        $res = $this->request('DELETE', $endpoint);
        return $res['code'] >= 200 && $res['code'] < 300;
    }

    /**
     * UPSERT — insert or update on conflict.
     */
    public function upsert($table, $data, $onConflict = 'id') {
        $res = $this->request('POST', $table, $data, [
            'Prefer: return=representation,resolution=merge-duplicates',
            'on-conflict: ' . $onConflict  // Note: this doesn't work as header; see below
        ]);
        // PostgREST uses query param for on_conflict
        $endpoint = $table . '?on_conflict=' . $onConflict;
        $res = $this->request('POST', $endpoint, $data, [
            'Prefer: return=representation,resolution=merge-duplicates'
        ]);
        if ($res['code'] >= 200 && $res['code'] < 300) {
            return $res['data'][0] ?? true;
        }
        error_log("Supabase UPSERT $table failed ({$res['code']}): {$res['raw']}");
        return false;
    }

    /**
     * Call a PostgreSQL function via RPC.
     */
    public function rpc($functionName, $params = []) {
        $url = SUPABASE_URL . '/rest/v1/rpc/' . $functionName;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return json_decode($response, true);
    }
}

$sb = new Supabase();
