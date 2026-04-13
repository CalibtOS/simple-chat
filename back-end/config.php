<?php
$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

$host = getenv('CHAT_DB_HOST') ?: ($host ?? "localhost");
$user = getenv('CHAT_DB_USER') ?: ($user ?? "root");
$password = getenv('CHAT_DB_PASSWORD') ?: ($password ?? "");
$database = getenv('CHAT_DB_NAME') ?: ($database ?? "chat");

// ─── SMTP (Mailtrap / any provider) ──────────────────────────────────────────
// Set real values in config.local.php — never commit secrets.
$smtpHost     = getenv('CHAT_SMTP_HOST')     ?: ($smtpHost     ?? 'sandbox.smtp.mailtrap.io');
$smtpPort     = (int) (getenv('CHAT_SMTP_PORT')     ?: ($smtpPort     ?? 2525));
$smtpUser     = getenv('CHAT_SMTP_USER')     ?: ($smtpUser     ?? '');
$smtpPass     = getenv('CHAT_SMTP_PASS')     ?: ($smtpPass     ?? '');
$smtpFrom     = getenv('CHAT_SMTP_FROM')     ?: ($smtpFrom     ?? 'noreply@chatty.local');
$smtpFromName    = getenv('CHAT_SMTP_FROM_NAME')    ?: ($smtpFromName    ?? 'Chatty');
// Dev redirect: if set, ALL outgoing mail goes to this address instead of the real recipient.
// Define $smtpDevRedirect in config.local.php or via env var CHAT_SMTP_DEV_REDIRECT.
$smtpDevRedirect = getenv('CHAT_SMTP_DEV_REDIRECT') ?: ($smtpDevRedirect ?? '');