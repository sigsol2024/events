<?php

declare(strict_types=1);

/**
 * Generate a QR PNG for a URL using a public chart API fallback-free approach:
 * we build SVG locally so we don't depend on Composer packages.
 * For scannable QR we use the Google Charts-free alternative via phpqrcode-less
 * pure SVG encoding with a simple matrix from an embedded minimal encoder.
 *
 * Practical approach for shared hosting without Composer:
 * serve QR via https://api.qrserver.com only as img src for display,
 * and for download we proxy a PNG through qr-download.php.
 */

function ticket_qr_url(array $booking): string
{
    return app_url('ticket.php?code=' . urlencode((string) $booking['ticket_code']));
}

function qr_image_src(string $data, int $size = 280): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
        . '&data=' . rawurlencode($data)
        . '&margin=8';
}

function qr_download_endpoint(array $booking): string
{
    return app_url(
        'qr-download.php?ref=' . urlencode((string) $booking['reference'])
        . '&token=' . urlencode((string) $booking['access_token'])
    );
}
