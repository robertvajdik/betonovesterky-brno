<?php
declare(strict_types=1);
// Fotka přiložená k poptávce – jen pro přihlášeného admina. ?id=ID souboru
require_once __DIR__ . '/../includes/bootstrap.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    exit;
}
$row = q("SELECT * FROM inquiry_files WHERE id = ?", [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$row) {
    http_response_code(404);
    exit;
}
inquiry_send_photo($row);
