<?php
declare(strict_types=1);

if (!function_exists('send_json_response')) {
    /**
     * Emit JSON response safely even when output has started elsewhere.
     *
     * @param array $payload      Data yang akan dikirim sebagai JSON.
     * @param int   $status       HTTP status code.
     * @param array $extraHeaders Header tambahan dalam bentuk ['Header' => 'Value'].
     * @param bool  $terminate    Menghentikan eksekusi script setelah mengirim respon.
     */
    function send_json_response(array $payload, int $status = 200, array $extraHeaders = [], bool $terminate = true): void
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $hasBuffer = ob_get_level() > 0;

        if ($hasBuffer && ob_get_length() > 0) {
            ob_clean();
        }

        if (!headers_sent($file, $line)) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

            foreach ($extraHeaders as $name => $value) {
                header($name . ': ' . $value);
            }

            http_response_code($status);
        } else {
            error_log(sprintf(
                'send_json_response: headers already sent at %s:%d, unable to modify headers',
                (string) $file,
                (int) $line
            ));
        }

        echo json_encode($payload, $flags);

        if ($hasBuffer) {
            ob_end_flush();
        }

        if ($terminate) {
            exit;
        }
    }
}
