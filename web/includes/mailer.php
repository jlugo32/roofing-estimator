<?php
/**
 * Outbound email. PHPMailer over SMTP (Hostinger creds in config/config.php);
 * falls back to PHP mail() if the vendor autoloader is missing, and logs
 * every failure to storage/logs/mail.log so silent drops are visible.
 */

declare(strict_types=1);

require_once __DIR__ . '/estimator-engine.php'; // money() lives in bootstrap; materials map here

function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $autoload = PROJECT_ROOT . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = SMTP_PORT;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom(SMTP_FROM_EMAIL, APP_NAME);
                $mail->addAddress($toEmail, $toName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
                $mail->send();
                return true;
            } catch (Throwable $e) {
                app_log('mail.log', 'PHPMailer FAIL to=' . $toEmail . ' subj="' . $subject . '" err=' . $e->getMessage());
                return false;
            }
        }
    }

    // Fallback: local mail(). Good enough for dev; production must have PHPMailer.
    $headers = 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
        . 'From: ' . APP_NAME . ' <' . SMTP_FROM_EMAIL . '>' . "\r\n";
    $ok = @mail($toEmail, $subject, $htmlBody, $headers);
    if (!$ok) {
        app_log('mail.log', 'mail() FAIL to=' . $toEmail . ' subj="' . $subject . '"');
    }
    return $ok;
}

/** Shared branded wrapper. $brandName is the sender identity shown to the reader. */
function email_shell(string $brandName, string $inner): string
{
    $safeBrand = htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8');
    return '<div style="background:#f4f5f7;padding:24px 12px;font-family:Arial,Helvetica,sans-serif;color:#1f2937">'
        . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden">'
        . '<div style="background:#1e3a5f;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold">'
        . $safeBrand . '</div>'
        . '<div style="padding:24px">' . $inner . '</div>'
        . '<div style="padding:14px 24px;background:#f9fafb;color:#6b7280;font-size:12px">'
        . 'Sent by ' . $safeBrand . '</div>'
        . '</div></div>';
}

/** Welcome email to a newly signed-up company owner. */
function send_welcome_email(array $company, array $user): bool
{
    $inner = '<h2 style="margin-top:0">Welcome to ' . e(APP_NAME) . ', ' . e($user['name']) . '!</h2>'
        . '<p>Your account for <strong>' . e($company['name']) . '</strong> is ready. Your '
        . TRIAL_LENGTH_DAYS . '-day free trial has started.</p>'
        . '<p>Your public estimator link — share it or embed it on your website to start capturing leads:</p>'
        . '<p><a href="' . e(SITE_URL . '/estimate/' . $company['slug']) . '" style="color:#1e3a5f">'
        . e(SITE_URL . '/estimate/' . $company['slug']) . '</a></p>'
        . '<p><a href="' . e(SITE_URL . '/dashboard/') . '" style="display:inline-block;background:#c2410c;color:#fff;'
        . 'padding:10px 20px;border-radius:6px;text-decoration:none">Open your dashboard</a></p>';
    return send_mail($user['email'], $user['name'], 'Welcome to ' . APP_NAME, email_shell(APP_NAME, $inner));
}

/** New-lead notification to the roofing company. */
function send_lead_notification(array $company, array $client, array $estimate): bool
{
    $inner = '<h2 style="margin-top:0">New lead: ' . e($client['name']) . '</h2>'
        . '<p>A homeowner just completed your roofing estimator.</p>'
        . '<table style="width:100%;font-size:14px;border-collapse:collapse">'
        . '<tr><td style="padding:4px 0;color:#6b7280">Name</td><td>' . e($client['name']) . '</td></tr>'
        . '<tr><td style="padding:4px 0;color:#6b7280">Email</td><td>' . e($client['email']) . '</td></tr>'
        . '<tr><td style="padding:4px 0;color:#6b7280">Phone</td><td>' . e($client['phone']) . '</td></tr>'
        . '<tr><td style="padding:4px 0;color:#6b7280">Location</td><td>' . e($client['zip_code'] . ', ' . $client['state']) . '</td></tr>'
        . '<tr><td style="padding:4px 0;color:#6b7280">Estimate</td><td><strong>'
        . money((int) $estimate['estimate_low_cents']) . ' – ' . money((int) $estimate['estimate_high_cents'])
        . '</strong></td></tr></table>'
        . '<p><a href="' . e(SITE_URL . '/dashboard/clients.php') . '" style="display:inline-block;background:#1e3a5f;color:#fff;'
        . 'padding:10px 20px;border-radius:6px;text-decoration:none">View lead in dashboard</a></p>';
    return send_mail(
        $company['contact_email'],
        $company['name'],
        'New roofing lead: ' . $client['name'],
        email_shell(APP_NAME, $inner)
    );
}

/** Branded estimate email to the homeowner, with the gated results link. */
function send_homeowner_estimate(array $company, array $client, array $estimate): bool
{
    $resultsUrl = SITE_URL . '/estimate/results.php?token=' . urlencode($estimate['results_token']);
    $inner = '<h2 style="margin-top:0">Your roofing estimate from ' . e($company['name']) . '</h2>'
        . '<p>Hi ' . e($client['name']) . ', based on the details you provided, your estimated project range is:</p>'
        . '<p style="font-size:24px;font-weight:bold;color:#1e3a5f">'
        . money((int) $estimate['estimate_low_cents']) . ' – ' . money((int) $estimate['estimate_high_cents']) . '</p>'
        . ((int) ($estimate['financing_monthly_cents'] ?? 0) > 0
            ? '<p>Financing available from about <strong>' . money((int) $estimate['financing_monthly_cents']) . '/month</strong>.</p>'
            : '')
        . '<p><a href="' . e($resultsUrl) . '" style="display:inline-block;background:#c2410c;color:#fff;'
        . 'padding:10px 20px;border-radius:6px;text-decoration:none">View your full estimate</a></p>'
        . '<p style="color:#6b7280;font-size:13px">This is a preliminary range, not a final quote. '
        . e($company['name']) . ' will contact you to schedule a free inspection.'
        . ($company['contact_phone'] ? ' Questions? Call ' . e($company['contact_phone']) . '.' : '') . '</p>';
    return send_mail(
        $client['email'],
        $client['name'],
        'Your roofing estimate from ' . $company['name'],
        email_shell($company['name'], $inner)
    );
}

/** Password reset link for a company user. */
function send_password_reset(string $email, string $name, string $resetUrl): bool
{
    $inner = '<h2 style="margin-top:0">Reset your password</h2>'
        . '<p>Hi ' . e($name) . ', click below to choose a new password. '
        . 'This link expires in ' . PASSWORD_RESET_TTL_MINUTES . ' minutes.</p>'
        . '<p><a href="' . e($resetUrl) . '" style="display:inline-block;background:#1e3a5f;color:#fff;'
        . 'padding:10px 20px;border-radius:6px;text-decoration:none">Reset password</a></p>'
        . '<p style="color:#6b7280;font-size:13px">If you did not request this, ignore this email.</p>';
    return send_mail($email, $name, APP_NAME . ' password reset', email_shell(APP_NAME, $inner));
}
