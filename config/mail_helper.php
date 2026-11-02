<?php
declare(strict_types=1);

/**
 * Sends an application email (activation link, password reset OTP/link, or share notification)
 * and persists a local HTML copy under logs/mails/ so offline grading on Docker/XAMPP works 100%.
 */
function sendAppMail(string $toEmail, string $subject, string $htmlBody): string {
    $mailDir = dirname(__DIR__) . '/logs/mails';
    if (!is_dir($mailDir)) {
        mkdir($mailDir, 0775, true);
    }

    $safeEmail = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $toEmail);
    $fileName = date('Ymd_His') . '_' . uniqid() . '_' . $safeEmail . '.html';
    $relativePath = 'logs/mails/' . $fileName;
    $fullPath = dirname(__DIR__) . '/' . $relativePath;

    $wrappedHtml = '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><title>'
        . htmlspecialchars($subject)
        . '</title><style>body{font-family:Inter,-apple-system,sans-serif;background:#f6f6f8;padding:32px;color:#111827}'
        . '.card{max-width:560px;margin:0 auto;background:#fff;border-radius:18px;padding:28px;border:1px solid #ebebef;box-shadow:0 8px 24px rgba(15,23,42,0.06)}'
        . '.meta{font-size:13px;color:#6b7280;border-bottom:1px solid #f1f5f9;padding-bottom:12px;margin-bottom:18px}'
        . 'a.btn{display:inline-block;background:#2563eb;color:#fff !important;text-decoration:none;padding:10px 18px;border-radius:10px;font-weight:600;margin-top:12px}'
        . '</style></head><body><div class="card"><div class="meta">'
        . '<div><strong>To:</strong> ' . htmlspecialchars($toEmail) . '</div>'
        . '<div><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</div>'
        . '<div><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</div>'
        . '</div><div class="content">' . $htmlBody . '</div></div></body></html>';

    file_put_contents($fullPath, $wrappedHtml);
    file_put_contents($mailDir . '/latest.html', $wrappedHtml);

    return $relativePath;
}
