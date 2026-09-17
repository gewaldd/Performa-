<?php
/**
 * Transactional email via the Brevo REST API (free tier: 300/day, no
 * card required, no expiry — https://www.brevo.com).
 *
 * Configure in your .env (same file firebase_init.php already loads):
 *   BREVO_API_KEY=xkeysib-xxxxxxxx
 *   BREVO_SENDER_EMAIL=noreply@yourdomain.com   (must be a verified sender in Brevo)
 *   BREVO_SENDER_NAME=Performa
 *
 * send_transactional_email() never throws. A failed send should not
 * block whatever triggered it (e.g. account creation still succeeds even
 * if the welcome email doesn't go out) — it returns false so the caller
 * can decide how to degrade.
 */

function send_transactional_email(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $apiKey = getenv('BREVO_API_KEY') ?: null;
    $senderEmail = getenv('BREVO_SENDER_EMAIL') ?: null;
    $senderName = getenv('BREVO_SENDER_NAME') ?: 'Performa';

    if (!$apiKey || !$senderEmail) {
        error_log('send_transactional_email: BREVO_API_KEY / BREVO_SENDER_EMAIL not configured in .env — email NOT sent to ' . $toEmail);
        return false;
    }

    $payload = [
        'sender' => ['name' => $senderName, 'email' => $senderEmail],
        'to' => [['email' => $toEmail, 'name' => $toName]],
        'subject' => $subject,
        'htmlContent' => $htmlBody,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'api-key: ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        return true;
    }

    error_log('send_transactional_email failed (HTTP ' . $code . '): ' . ($curlErr ?: $resp));
    return false;
}

/**
 * Build the current app's base URL (scheme + host) from the request,
 * so emailed links work regardless of whether this is localhost, a
 * staging box, or production. login.php sits at the project web root.
 */
function app_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function welcome_email_html(string $name, string $email, string $password, string $loginUrl): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES);
    $safeEmail = htmlspecialchars($email, ENT_QUOTES);
    $safePassword = htmlspecialchars($password, ENT_QUOTES);
    $safeUrl = htmlspecialchars($loginUrl, ENT_QUOTES);

    return <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2940;">
  <h2 style="margin:0 0 16px;">Welcome to Performa, {$safeName}</h2>
  <p style="line-height:1.5;">An account has been created for you. Here are your sign-in details:</p>
  <table style="width:100%;border-collapse:collapse;margin:16px 0;">
    <tr>
      <td style="padding:6px 0;color:#6f7c95;">Email</td>
      <td style="padding:6px 0;font-weight:600;">{$safeEmail}</td>
    </tr>
    <tr>
      <td style="padding:6px 0;color:#6f7c95;">Temporary password</td>
      <td style="padding:6px 0;font-weight:600;font-family:'Courier New',monospace;">{$safePassword}</td>
    </tr>
  </table>
  <p style="line-height:1.5;"><strong>For security, please sign in and change this password right away.</strong></p>
  <p>
    <a href="{$safeUrl}" style="display:inline-block;padding:10px 20px;background:#2f6df6;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">
      Sign in to Performa
    </a>
  </p>
  <p style="color:#6f7c95;font-size:13px;margin-top:24px;">If you weren't expecting this account, you can ignore this email.</p>
</div>
HTML;
}
