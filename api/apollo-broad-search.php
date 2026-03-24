<?php
require_once 'enrichment.php';
require_once 'config.php';

header('Content-Type: application/json');

$name = $_GET['name'] ?? 'Shane Winter';
$email = $_GET['email'] ?? 'shanewin@gmail.com';
$phone = $_GET['phone'] ?? '6317596760';

echo "--- Broad Search for Name: $name ---\n";
$nameSearch = apolloRequest("https://api.apollo.io/api/v1/mixed_people/api_search", [
    'q_person_name' => $name,
    'per_page' => 10
]);

echo "--- Keyword Search for Email: $email ---\n";
$emailSearch = apolloRequest("https://api.apollo.io/api/v1/mixed_people/api_search", [
    'q_keywords' => $email,
    'per_page' => 10
]);

echo "--- Keyword Search for Phone: $phone ---\n";
$phoneSearch = apolloRequest("https://api.apollo.io/api/v1/mixed_people/api_search", [
    'q_keywords' => $phone,
    'per_page' => 10
]);

echo json_encode([
    'name_results' => $nameSearch['data']['people'] ?? [],
    'email_results' => $emailSearch['data']['people'] ?? [],
    'phone_results' => $phoneSearch['data']['people'] ?? []
], JSON_PRETTY_PRINT);
