<?php
// ── Pengiriman pesan WhatsApp (dipakai webhook bot & cron ringkasan) ──
// Wajib di-include SETELAH config/config.php dan includes/functions.php
// Mengembalikan true bila gateway merespons HTTP 2xx.

if (!function_exists('kirim_wa')) {
function kirim_wa(string $nomor, string $pesan): bool {
    $gateway = get_pengaturan('wa_gateway', 'fonnte');

    // ── WA Gateway sendiri (via elearning-bridge di X200MA lewat Tailscale) ──
    if ($gateway === 'wagateway') {
        $url   = get_pengaturan('wa_gateway_send_url', '');
        $token = get_pengaturan('wa_gateway_send_token', '');
        if ($url === '' || $token === '') {
            error_log('[kirim_wa] wa_gateway_send_url/token belum diset');
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['target' => $nomor, 'message' => $pesan]),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
    } elseif ($gateway === 'whacenter') {
        $ch = curl_init(WHACENTER_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'device_id' => WHACENTER_DEVICE_ID,
                'number'    => $nomor,
                'message'   => $pesan,
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
    } else {
        // Default: Fonnte
        $ch = curl_init(FONNTE_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . FONNTE_TOKEN],
            CURLOPT_POSTFIELDS     => ['target' => $nomor, 'message' => $pesan],
            CURLOPT_TIMEOUT        => 15,
        ]);
    }

    $resp = curl_exec($ch);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) error_log('[kirim_wa] ' . $gateway . ' CURL: ' . curl_error($ch));
    curl_close($ch);
    return $resp !== false && $kode >= 200 && $kode < 300;
}
}
