<?php
require_once 'config.php';

header('Content-Type: text/plain');

echo "=== Email Debug ===\n\n";

// Test 1: Basic mail()
echo "Test 1: Basic mail()...\n";
$r1 = @mail(NOTIFICATION_EMAIL, 'Test 1 Basic', 'Basic mail test');
echo "Result: " . ($r1 ? 'OK' : 'FAIL') . "\n\n";

// Test 2: mail() with From header
echo "Test 2: mail() with From header...\n";
$r2 = @mail(NOTIFICATION_EMAIL, 'Test 2 From', 'Mail with From header', "From: info@eleanorbk.com\r\n");
echo "Result: " . ($r2 ? 'OK' : 'FAIL') . "\n\n";

// Test 3: mail() with -f flag
echo "Test 3: mail() with -f flag...\n";
$r3 = @mail(NOTIFICATION_EMAIL, 'Test 3 Flag', 'Mail with -f flag', "From: info@eleanorbk.com\r\n", "-f info@eleanorbk.com");
echo "Result: " . ($r3 ? 'OK' : 'FAIL') . "\n\n";

// Test 4: Check if sendmail path exists
echo "Test 4: PHP mail config...\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "sendmail_from: " . ini_get('sendmail_from') . "\n\n";

// Test 5: Check socket connectivity
echo "Test 5: Can connect to smtp.hostinger.com:465...\n";
$sock = @stream_socket_client("ssl://smtp.hostinger.com:465", $errno, $errstr, 10);
if ($sock) {
    echo "Connected! Server says: " . fgets($sock, 512) . "\n";
    fclose($sock);
} else {
    echo "FAILED: $errstr ($errno)\n";
}

echo "\nDone.\n";
