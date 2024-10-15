<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
} catch (Exception $e) {
    die('Could not load .env file');
}

$ENCRYPTION_KEY_RAW = trim($_ENV['ENCRYPTION_KEY']);
$ENCRYPTION_IV_RAW = trim($_ENV['ENCRYPTION_IV']);

$ENCRYPTION_KEY_RAW = ensureEvenHexLength($ENCRYPTION_KEY_RAW);
$ENCRYPTION_IV_RAW = ensureEvenHexLength($ENCRYPTION_IV_RAW);

$ENCRYPTION_KEY = hex2bin($ENCRYPTION_KEY_RAW);
$ENCRYPTION_IV = hex2bin($ENCRYPTION_IV_RAW);

if ($ENCRYPTION_KEY === false || $ENCRYPTION_IV === false) {
    die('Invalid encryption key or IV format.');
}
function ensureEvenHexLength($hex) {
    if (strlen($hex) % 2 != 0) {
        return '0' . $hex;
    }
    return $hex;
}
/**
 * @throws Exception
 */
function decrypt_data($encrypted_data): string
{
    global $ENCRYPTION_KEY, $ENCRYPTION_IV;
    $cipher = 'aes-256-cbc';
    $decoded_data = base64_decode($encrypted_data);
    $decrypted_data = openssl_decrypt($decoded_data, $cipher, $ENCRYPTION_KEY, 3, $ENCRYPTION_IV);

    if ($decrypted_data === false) {
        throw new Exception('Decryption failed.');
    }

    $pad = ord($decrypted_data[strlen($decrypted_data) - 1]);
    return substr($decrypted_data, 0, -$pad);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/send-message') {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $encrypted_message = $data['message'] ?? "";
        $encrypted_images = $data['images'] ?? [];
        $encrypted_videos = $data['videos'] ?? [];
        $encrypted_pdfs = $data['pdfs'] ?? [];

        var_dump($encrypted_message);
        $decrypted_message = "";
        if ($encrypted_message) {
            $decrypted_message = decrypt_data($encrypted_message);
        }

        $decrypted_images = [];
        $decrypted_videos = [];
        $decrypted_pdfs = [];

        foreach ($encrypted_images as $image) {
            $decrypted_images[] = decrypt_data($image);
        }

        foreach ($encrypted_videos as $video) {
            $decrypted_videos[] = decrypt_data($video);
        }

        foreach ($encrypted_pdfs as $pdf) {
            $decrypted_pdfs[] = decrypt_data($pdf);
        }

        $current_date = date('Y-m-d_H:i:s');
        $upload_path = __DIR__ . '/upload/' . $current_date;

        if (!file_exists($upload_path)) {
            if (!mkdir($upload_path, 0777, true)) {
                throw new Exception('Failed to create directory: ' . $upload_path);
            }
        }
        if ($decrypted_message) {
            file_put_contents($upload_path . '/message.txt', $decrypted_message);
        }

        foreach ($decrypted_images as $index => $image) {
            file_put_contents($upload_path . '/image_' . ($index + 1) . '.jpeg', $image);
        }

        foreach ($decrypted_videos as $index => $video) {
            file_put_contents($upload_path . '/video_' . ($index + 1) . '.mkv', $video);
        }

        foreach ($decrypted_pdfs as $index => $pdf) {
            file_put_contents($upload_path . '/file_' . ($index + 1) . '.pdf', $pdf);
        }

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }

    exit;
}

if (php_sapi_name() == 'cli-server') {
    if (!in_array($_SERVER['REQUEST_URI'], ['/', '/send-message'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        exit;
    }
}


