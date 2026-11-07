<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$CONFIG = require __DIR__ . '/../config.php';

// Základní URL webu (podpora umístění v podadresáři)
if ($CONFIG['base_url'] !== null) {
    define('BASE_URL', rtrim($CONFIG['base_url'], '/'));
} else {
    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $root    = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $base    = ($docRoot !== '' && stripos($root, $docRoot) === 0) ? substr($root, strlen($docRoot)) : '';
    define('BASE_URL', rtrim($base, '/'));
}

define('ROOT_DIR', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_DIR . '/uploads');

// Nedostupná databáze → srozumitelná chybová stránka místo fatální chyby
set_exception_handler(function (Throwable $ex) {
    error_log((string) $ex);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $db = $ex instanceof PDOException;
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . ($db ? 'Chyba databáze' : 'Chyba') . '</title><p style="font-family:sans-serif;padding:2rem;max-width:40rem">'
       . ($db ? 'Nepodařilo se připojit k databázi. Zkontrolujte nastavení v <code>config.php</code> a import <code>database/schema.sql</code>.'
              : 'Omlouváme se, došlo k chybě. Zkuste to prosím později.')
       . '</p>';
});

require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/schema.php';
