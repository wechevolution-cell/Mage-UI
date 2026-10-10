<?php
declare(strict_types=1);

/**
 * Mail delivery without Composer: PHP mail(), a minimal SMTP client (Hostinger SMTP: smtp.hostinger.com:465 SSL),
 * or "log" (writes .eml files to data/mail — useful locally and in tests).
 */
final class Mailer
{
    public static function config(): array
    {
        $p = DB::platform();
        return [
            'driver' => setting_get($p, 'mail_driver', 'log'),
            'host' => setting_get($p, 'smtp_host', 'smtp.hostinger.com'),
            'port' => (int) setting_get($p, 'smtp_port', 465),
            'secure' => setting_get($p, 'smtp_secure', 'ssl'),
            'user' => setting_get($p, 'smtp_user', ''),
            'pass' => setting_get($p, 'smtp_pass', ''),
            'from' => setting_get($p, 'mail_from', 'no-reply@' . host_without_port(req_host())),
            'from_name' => setting_get($p, 'mail_from_name', 'wechevo e-shop'),
        ];
    }

    /** @param array{shop_id?:int,from_name?:string,reply_to?:string} $opt */
    public static function send(string $to, string $subject, string $html, array $opt = []): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        $c = self::config();
        $fromName = $opt['from_name'] ?? $c['from_name'];
        $boundary = 'b' . bin2hex(random_bytes(8));
        $text = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>|<\/h\d>|<\/li>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encode($fromName) . ' <' . $c['from'] . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encode($subject),
            'MIME-Version: 1.0',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $c['from'])[1] ?? 'localhost') . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if (!empty($opt['reply_to']) && filter_var($opt['reply_to'], FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $opt['reply_to'];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$boundary--\r\n";

        $ok = false;
        $err = null;
        try {
            if ($c['driver'] === 'smtp' && $c['user']) {
                $ok = self::smtp($c, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
            } elseif ($c['driver'] === 'mail') {
                $h = array_filter($headers, fn($l) => !str_starts_with($l, 'To:') && !str_starts_with($l, 'Subject:'));
                $ok = @mail($to, self::encode($subject), $body, implode("\r\n", $h), '-f' . $c['from']);
            } else {
                $f = ensure_dir(DATA_DIR . '/mail') . '/' . date('Ymd-His') . '-' . substr(md5($to . microtime()), 0, 6) . '.eml';
                $ok = file_put_contents($f, implode("\r\n", $headers) . "\r\n\r\n" . $body) !== false;
            }
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
        try {
            db_insert(DB::platform(), 'mail_log', ['shop_id' => $opt['shop_id'] ?? null, 'to_email' => $to, 'subject' => mb_substr($subject, 0, 250),
                'driver' => $c['driver'], 'status' => $ok ? 'sent' : 'failed', 'error' => $err, 'created_at' => now()]);
        } catch (Throwable) {
        }
        return $ok;
    }

    private static function encode(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

    private static function smtp(array $c, string $to, string $data): bool
    {
        $remote = ($c['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
        if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr");
        stream_set_timeout($fp, 15);
        $read = function () use ($fp) {
            $res = '';
            while (($line = fgets($fp, 515)) !== false) { $res .= $line; if (isset($line[3]) && $line[3] === ' ') break; }
            return $res;
        };
        $cmd = function (string $c, array $expect) use ($fp, $read) {
            if ($c !== '') fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $expect, true)) throw new RuntimeException('SMTP error: ' . trim($r));
            return $r;
        };
        $cmd('', [220]);
        $ehloHost = host_without_port(req_host()) ?: 'localhost';
        $cmd('EHLO ' . $ehloHost, [250]);
        if ($c['secure'] === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('STARTTLS failed');
            $cmd('EHLO ' . $ehloHost, [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user']), [334]);
        $cmd(base64_encode($c['pass']), [235]);
        $cmd('MAIL FROM:<' . $c['from'] . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $data = preg_replace('/^\./m', '..', $data);
        $cmd($data . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }

    /** Wrap content in a simple branded email layout. */
    public static function layout(string $title, string $bodyHtml, string $brand = 'wechevo e-shop', string $color = '#111827'): string
    {
        return '<!doctype html><html lang="el"><body style="margin:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#111">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden">'
            . '<tr><td style="background:' . e($color) . ';color:#fff;padding:18px 24px;font-size:18px;font-weight:bold">' . e($brand) . '</td></tr>'
            . '<tr><td style="padding:24px;font-size:15px;line-height:1.6"><h1 style="font-size:20px;margin:0 0 12px">' . e($title) . '</h1>' . $bodyHtml . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
