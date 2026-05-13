<?php
/**
 * /includes/mailer.php
 * Простая обёртка для отправки писем через PHP mail()
 */
function send_neirolinks_mail(string $to, string $subject, string $message): bool {
    // Кодируем тему в UTF-8
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    
    // Формируем заголовки
    $host = $_SERVER['HTTP_HOST'] ?? 'nlpartner.ru';
    $domain = preg_replace('/:\d+$/', '', $host);
    $from = "NEIROLINKS <noreply@{$domain}>";
    
    $headers = "From: {$from}\r\n"
             . "Reply-To: {$from}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "X-Mailer: PHP/" . phpversion();
             
    return @mail($to, $subject, $message, $headers);
}
?>