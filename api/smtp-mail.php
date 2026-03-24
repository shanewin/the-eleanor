<?php
/**
 * Email sender for Hostinger shared hosting.
 * Uses PHP mail() with From address matching the Hostinger mailbox.
 * On Hostinger, mail() works when the From address is a real mailbox on the server.
 */

function smtpSend($to, $subject, $body, $replyTo = null) {
    $from = defined('SMTP_FROM') ? SMTP_FROM : 'info@eleanorbk.com';
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'The Eleanor';

    $headers = "From: $fromName <$from>\r\n";
    if ($replyTo) {
        $headers .= "Reply-To: $replyTo\r\n";
    }
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // The -f flag sets the envelope sender, which Hostinger requires to match a real mailbox
    $result = @mail($to, $subject, $body, $headers, "-f $from");

    if (!$result) {
        error_log("mail() failed for $to from $from");
    }

    return $result;
}
