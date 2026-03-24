<?php
/**
 * SMTP Email Sender via direct socket connection.
 * Uses SSL connection to smtp.hostinger.com:465.
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

    // Read greeting
    $greeting = smtpRead($socket);

    // EHLO
    fwrite($socket, "EHLO eleanorbk.com\r\n");
    smtpRead($socket); // Read full multi-line EHLO response

    // AUTH LOGIN
    fwrite($socket, "AUTH LOGIN\r\n");
    smtpRead($socket);

    fwrite($socket, base64_encode($user) . "\r\n");
    smtpRead($socket);

    fwrite($socket, base64_encode($pass) . "\r\n");
    $authResponse = smtpRead($socket);
    if (strpos($authResponse, '235') === false) {
        error_log("SMTP auth failed: $authResponse");
        fclose($socket);
        return false;
    }

    // MAIL FROM
    fwrite($socket, "MAIL FROM:<$from>\r\n");
    smtpRead($socket);

    // RCPT TO
    fwrite($socket, "RCPT TO:<$to>\r\n");
    smtpRead($socket);

    // DATA
    fwrite($socket, "DATA\r\n");
    smtpRead($socket);

    // Build message
    $message = "From: $fromName <$from>\r\n";
    $message .= "To: $to\r\n";
    $message .= "Subject: $subject\r\n";
    if ($replyTo) {
        $message .= "Reply-To: $replyTo\r\n";
    }
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Date: " . date('r') . "\r\n";
    $message .= "\r\n";
    // Escape dots at start of lines
    $message .= str_replace("\n.", "\n..", $body);
    $message .= "\r\n.\r\n";

    fwrite($socket, $message);
    $dataResponse = smtpRead($socket);
    $success = (strpos($dataResponse, '250') !== false);

    if (!$success) {
        error_log("SMTP send failed: $dataResponse");
    }

    fwrite($socket, "QUIT\r\n");
    @smtpRead($socket);
    fclose($socket);

    return $success;
}

/**
 * Read all available lines from SMTP socket.
 */
function smtpRead($socket) {
    $response = '';
    while ($line = @fgets($socket, 515)) {
        $response .= $line;
        // If 4th char is a space (not hyphen), this is the last line
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}
