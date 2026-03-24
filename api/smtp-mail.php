<?php
/**
 * SMTP Email Sender using Hostinger's SMTP server.
 * Uses PHP's built-in stream_socket_client with SSL.
 */

function smtpSend($to, $subject, $body, $replyTo = null) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $from = SMTP_FROM;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'The Eleanor';

    $socket = @stream_socket_client(
        "ssl://$host:$port",
        $errno, $errstr, 30,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }

    $response = fgets($socket, 515);
    if (substr($response, 0, 3) !== '220') {
        error_log("SMTP unexpected greeting: $response");
        fclose($socket);
        return false;
    }

    // Helper to send command and check response
    $send = function($command, $expectCode) use ($socket) {
        fwrite($socket, $command . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== (string)$expectCode) {
            error_log("SMTP error on '$command': $response");
            return false;
        }
        return $response;
    };

    // EHLO
    if (!$send("EHLO $host", 250)) { fclose($socket); return false; }
    // Consume multi-line EHLO response
    while (substr($response ?? '', 3, 1) === '-') {
        $response = fgets($socket, 515);
    }

    // AUTH LOGIN
    if (!$send("AUTH LOGIN", 334)) { fclose($socket); return false; }
    if (!$send(base64_encode($user), 334)) { fclose($socket); return false; }
    if (!$send(base64_encode($pass), 235)) { fclose($socket); return false; }

    // MAIL FROM
    if (!$send("MAIL FROM:<$from>", 250)) { fclose($socket); return false; }

    // RCPT TO
    if (!$send("RCPT TO:<$to>", 250)) { fclose($socket); return false; }

    // DATA
    if (!$send("DATA", 354)) { fclose($socket); return false; }

    // Build headers and body
    $headers = "From: $fromName <$from>\r\n";
    $headers .= "To: $to\r\n";
    if ($replyTo) {
        $headers .= "Reply-To: $replyTo\r\n";
    }
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "\r\n";

    // Escape any lines that start with a dot
    $body = str_replace("\r\n.", "\r\n..", $body);

    fwrite($socket, $headers . $body . "\r\n.\r\n");
    $response = fgets($socket, 515);
    $success = (substr($response, 0, 3) === '250');

    if (!$success) {
        error_log("SMTP send failed: $response");
    }

    $send("QUIT", 221);
    fclose($socket);

    return $success;
}
