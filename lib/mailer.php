<?php

declare(strict_types=1);

function send_booking_emails(array $booking): void
{
    $fresh = booking_find_by_id((int) $booking['id']) ?: $booking;

    if (empty($fresh['admin_notified_at'])) {
        $ok = send_html_mail(
            (string) config('mail.admin_notify_email'),
            'New paid booking — ' . $fresh['reference'],
            render_email_template('admin-booking', ['booking' => $fresh])
        );
        if ($ok) {
            booking_mark_notified((int) $fresh['id'], 'admin');
        }
    }

    if (empty($fresh['buyer_notified_at'])) {
        $ok = send_html_mail(
            (string) $fresh['email'],
            'Your Beach House ticket — ' . $fresh['reference'],
            render_email_template('buyer-booking', ['booking' => $fresh])
        );
        if ($ok) {
            booking_mark_notified((int) $fresh['id'], 'buyer');
        }
    }
}

function render_email_template(string $name, array $vars): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include dirname(__DIR__) . '/emails/' . $name . '.php';
    return (string) ob_get_clean();
}

function send_html_mail(string $to, string $subject, string $html): bool
{
    if ($to === '') {
        return false;
    }

    $fromEmail = (string) config('mail.from_email', 'noreply@localhost');
    $fromName = (string) config('mail.from_name', 'Events');
    $smtpHost = trim((string) config('mail.smtp_host', ''));

    if ($smtpHost !== '') {
        return smtp_send($to, $subject, $html, $fromEmail, $fromName);
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . sprintf('%s <%s>', $fromName, $fromEmail),
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . phpversion(),
    ];
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, implode("\r\n", $headers));
}

function smtp_send(string $to, string $subject, string $html, string $fromEmail, string $fromName): bool
{
    $host = (string) config('mail.smtp_host');
    $port = (int) config('mail.smtp_port', 587);
    $user = (string) config('mail.smtp_user');
    $pass = (string) config('mail.smtp_pass');
    $enc = strtolower((string) config('mail.smtp_encryption', 'tls'));

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @fsockopen($remote, $port, $errno, $errstr, 20);
    if (!$fp) {
        return false;
    }

    $read = function () use ($fp) {
        $data = '';
        while ($str = fgets($fp, 515)) {
            $data .= $str;
            if (isset($str[3]) && $str[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = function (string $cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
    };

    $read();
    $write('EHLO localhost');
    $read();

    if ($enc === 'tls') {
        $write('STARTTLS');
        $read();
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $write('EHLO localhost');
        $read();
    }

    if ($user !== '') {
        $write('AUTH LOGIN');
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $read();
    }

    $write('MAIL FROM:<' . $fromEmail . '>');
    $read();
    $write('RCPT TO:<' . $to . '>');
    $read();
    $write('DATA');
    $read();

    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=utf-8',
    ];
    $write(implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n.");
    $read();
    $write('QUIT');
    fclose($fp);
    return true;
}
