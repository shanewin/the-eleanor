<?php
/**
 * Applicant-facing welcome email.
 *
 * Sent by job-runner.php's applicant_email step right after a Waitlist or
 * Unit Interest form submission. Goal: nudge the applicant to book a tour
 * at https://eleanor.nyc/tours. Claude writes the greeting + one personal
 * line; everything else (CTA button, footer, unsubscribe) is static so the
 * email stays on-brand and the link can't drift.
 *
 * Skipped automatically for:
 *   - Mailing list signups (different intent — they aren't lead-stage)
 *   - Emails already in the email_suppressions table
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/smtp-mail.php';

define('UNSUBSCRIBE_INCLUDE_ONLY', true);
require_once __DIR__ . '/unsubscribe.php';

/**
 * Public entry point — called from job-runner.php.
 *
 * Returns true if the email was sent (or intentionally skipped via
 * suppression). Throws on hard failures so the job step can retry.
 */
function sendApplicantEmail(array $payload, string $leadEmail): bool {
    global $sb;

    if (empty($leadEmail) || !filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $leadEmail = strtolower($leadEmail);

    // Mailing list = newsletter signup, not an active lead. Skip.
    if (!empty($payload['is_mailing_list'])) {
        return false;
    }

    // Owner master toggle — disable from the Automation tab.
    if (!isAutomationEnabled('applicant_email_enabled')) {
        return false;
    }

    // Honor suppression list.
    $suppressed = $sb->selectOne('email_suppressions', 'email', ['email=eq.' . urlencode($leadEmail)]);
    if ($suppressed) {
        $sb->insert('communications', [
            'lead_email' => $leadEmail,
            'direction'  => 'outbound',
            'channel'    => 'email',
            'subject'    => 'Applicant welcome email suppressed',
            'body'       => 'Email send skipped — recipient is on the unsubscribe list.',
            'sender'     => 'Eleanor AI',
            'recipient'  => $leadEmail,
            'status'     => 'suppressed',
        ]);
        return true;
    }

    $fields    = $payload['fields'] ?? [];
    $firstName = trim($fields['firstName'] ?? '');
    $unit      = trim($fields['unit'] ?? '');
    $moveIn    = trim($fields['moveInDate'] ?? '');

    // Form type: Unit Interest if a specific unit was named, else Waitlist.
    $formType = ($unit !== '') ? 'unit_interest' : 'waitlist';

    // Claude-authored opener (greeting + one personal line). Falls back to a
    // static opener if the API call fails so the send isn't blocked.
    $opener = generateApplicantOpener($firstName, $formType, $unit, $moveIn);

    // Lead row id — used for the CTA tracking param.
    $leadId = 0;
    foreach (['unit_inquiries', 'waitlist_submissions'] as $t) {
        $row = $sb->selectOne($t, 'id', ['email=eq.' . urlencode($leadEmail)], 'created_at.desc');
        if ($row && isset($row['id'])) { $leadId = (int) $row['id']; break; }
    }

    $tourUrl = 'https://eleanor.nyc/tours?src=email' . ($leadId ? '&lead_id=' . $leadId : '');
    $unsubUrl = unsubBuildLink($leadEmail);

    [$subject, $bodyHtml] = buildApplicantEmail($formType, $firstName, $unit, $opener, $tourUrl, $unsubUrl);

    // Reply-To: first active owner so any reply lands with the leasing team.
    $owners = function_exists('getOwnerEmails') ? getOwnerEmails() : [];
    $replyTo = !empty($owners) ? $owners[0] : null;

    $extraHeaders = [
        // RFC 8058 one-click unsubscribe — improves Gmail/Yahoo deliverability.
        'List-Unsubscribe'      => '<' . $unsubUrl . '>',
        'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        'List-Id'               => '<applicant-welcome.eleanorbk.com>',
    ];

    $sent = smtpSend($leadEmail, $subject, $bodyHtml, $replyTo, true, $extraHeaders);

    $sb->insert('communications', [
        'lead_email' => $leadEmail,
        'direction'  => 'outbound',
        'channel'    => 'email',
        'subject'    => $subject,
        'body'       => $bodyHtml,
        'sender'     => 'Eleanor AI',
        'recipient'  => $leadEmail,
        'status'     => $sent ? 'sent' : 'failed',
    ]);

    if (!$sent) {
        throw new RuntimeException('smtpSend returned false for applicant email');
    }
    return true;
}

/**
 * Ask Claude Haiku for a two-line opener. Always returns an array shaped
 * { greeting, personal_line } — falls back to deterministic copy if the API
 * is unavailable, rate-limited, or returns malformed JSON.
 */
function generateApplicantOpener(string $firstName, string $formType, string $unit, string $moveIn): array {
    $fallbackGreeting = $firstName !== '' ? "Hi $firstName," : 'Hi there,';
    $fallbackLine = $formType === 'unit_interest' && $unit !== ''
        ? "Thanks for reaching out about Unit $unit at The Eleanor."
        : "Thanks for joining the waitlist at The Eleanor.";

    if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY) {
        return ['greeting' => $fallbackGreeting, 'personal_line' => $fallbackLine];
    }

    $context = "First name: " . ($firstName !== '' ? $firstName : '(unknown)') . "\n"
             . "Form type: " . ($formType === 'unit_interest' ? 'Unit Interest' : 'Waitlist') . "\n";
    if ($unit !== '')   $context .= "Unit they're interested in: $unit\n";
    if ($moveIn !== '') $context .= "Target move-in: $moveIn\n";

    $settings = getAutomationSettings();
    $extraInstructions = trim($settings['applicant_email_instructions'] ?? '');

    $userPrompt = "Write a warm 2-line opener for a luxury rental follow-up email from The Eleanor "
        . "(a brand-new luxury building in Boerum Hill, Brooklyn).\n\n"
        . "Context:\n" . $context . "\n"
        . "Requirements:\n"
        . "- Line 1 is the greeting (e.g., 'Hi Alex,'). If first name is unknown, use 'Hi there,'.\n"
        . "- Line 2 is ONE sentence, max 22 words. Reference the unit if known, or the waitlist otherwise.\n"
        . "- No exclamation marks. No emojis. No 'I hope this finds you well.'\n"
        . "- Evoke a feeling, not a feature list — don't mention oak flooring, stone surfaces, square footage, etc.\n"
        . "- Sound like a real leasing agent, not a chatbot.\n";

    if ($extraInstructions !== '') {
        $userPrompt .= "\nADDITIONAL INSTRUCTIONS FROM THE LEASING TEAM (follow these when relevant):\n"
            . $extraInstructions . "\n";
    }

    $userPrompt .= "\nRespond with ONLY valid JSON in this exact shape:\n"
        . '{"greeting": "...", "personal_line": "..."}';

    $payload = [
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 220,
        'messages'   => [['role' => 'user', 'content' => $userPrompt]],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300 || !$response) {
        error_log("applicant-email: Claude opener failed ($httpCode)");
        return ['greeting' => $fallbackGreeting, 'personal_line' => $fallbackLine];
    }

    $data = json_decode($response, true);
    $text = $data['content'][0]['text'] ?? '';
    if (!$text) {
        return ['greeting' => $fallbackGreeting, 'personal_line' => $fallbackLine];
    }

    // Extract JSON from the response (Claude usually returns clean JSON, but
    // sometimes wraps it in a code fence).
    if (preg_match('/\{[^{}]*"greeting"[^{}]*"personal_line"[^{}]*\}/s', $text, $m)) {
        $parsed = json_decode($m[0], true);
    } else {
        $parsed = json_decode($text, true);
    }

    if (!is_array($parsed) || empty($parsed['greeting']) || empty($parsed['personal_line'])) {
        return ['greeting' => $fallbackGreeting, 'personal_line' => $fallbackLine];
    }

    return [
        'greeting'      => trim($parsed['greeting']),
        'personal_line' => trim($parsed['personal_line']),
    ];
}

/**
 * Build the subject + HTML body for the applicant email. Two variants —
 * unit_interest references the unit, waitlist talks generally about the
 * building.
 *
 * Returns [subject, html_body].
 */
function buildApplicantEmail(string $formType, string $firstName, string $unit, array $opener, string $tourUrl, string $unsubUrl): array {
    $name = $firstName !== '' ? $firstName : 'there';

    if ($formType === 'unit_interest' && $unit !== '') {
        $unitSafe = htmlspecialchars($unit, ENT_QUOTES, 'UTF-8');
        $subject = "{$name}, want to step inside Unit {$unit}?";
        $intro = "I'd love to show you Unit {$unitSafe} in person — it tends to feel even better than it photographs.";
        $detail = "Tours run about 30 minutes. Pick a time that works and I'll meet you there.";
    } else {
        $subject = "{$name}, come see The Eleanor";
        $intro = "I'd love to have you over for a quick look around. Photos only get you so far — the building tells its own story once you're inside.";
        $detail = "Tours run about 30 minutes. Pick a time that works and I'll meet you there.";
    }

    $greeting     = htmlspecialchars($opener['greeting'], ENT_QUOTES, 'UTF-8');
    $personalLine = htmlspecialchars($opener['personal_line'], ENT_QUOTES, 'UTF-8');
    $address      = htmlspecialchars(THE_ELEANOR_ADDRESS, ENT_QUOTES, 'UTF-8');
    $tourUrlSafe  = htmlspecialchars($tourUrl, ENT_QUOTES, 'UTF-8');
    $unsubUrlSafe = htmlspecialchars($unsubUrl, ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f3ef;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f5f3ef"><tr><td align="center" style="padding:32px 16px">
<table width="560" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="max-width:560px;border-radius:6px;overflow:hidden;border:1px solid #e8e4dc">

    <tr><td style="padding:36px 40px 8px;text-align:center">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:5px;color:#888">The Eleanor</div>
        <div style="width:32px;height:1px;background:#222;margin:16px auto 0"></div>
    </td></tr>

    <tr><td style="padding:28px 40px 8px;font-size:15px;color:#222;line-height:1.6">
        <p style="margin:0 0 18px">{$greeting}</p>
        <p style="margin:0 0 18px">{$personalLine}</p>
        <p style="margin:0 0 18px">{$intro}</p>
        <p style="margin:0 0 0">{$detail}</p>
    </td></tr>

    <tr><td style="padding:28px 40px 36px;text-align:center">
        <a href="{$tourUrlSafe}" style="display:inline-block;background:#1a1a1a;color:#ffffff;text-decoration:none;padding:14px 32px;font-size:14px;letter-spacing:2px;text-transform:uppercase;border-radius:2px;font-weight:500">Book a Showing</a>
    </td></tr>

    <tr><td style="padding:24px 40px 32px;border-top:1px solid #ebe7df;text-align:center;font-size:12px;color:#888;line-height:1.6">
        The Eleanor<br>
        {$address}<br>
        <a href="https://eleanor.nyc" style="color:#888;text-decoration:underline">eleanor.nyc</a>
        <div style="margin-top:14px;font-size:11px;color:#aaa">
            You're receiving this because you submitted a form on our site.<br>
            <a href="{$unsubUrlSafe}" style="color:#aaa;text-decoration:underline">Unsubscribe from these emails</a>
        </div>
    </td></tr>

</table>
</td></tr></table>
</body></html>
HTML;

    return [$subject, $body];
}
