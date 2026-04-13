<?php
// Copy this file to config.local.php for local machine overrides.
// Do NOT commit real secrets.

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'chat';

// ─── Mailtrap SMTP — paste your credentials from mailtrap.io ─────────────────
$smtpHost     = 'sandbox.smtp.mailtrap.io';
$smtpPort     = 2525;
$smtpUser     = 'YOUR_MAILTRAP_USERNAME';   // ← paste from Mailtrap dashboard
$smtpPass     = 'YOUR_MAILTRAP_PASSWORD';   // ← paste from Mailtrap dashboard
$smtpFrom     = 'noreply@chatty.local';
$smtpFromName = 'Chatty';

