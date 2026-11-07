<?php
declare(strict_types=1);

/*
 * Chybová stránka 404 ve vzhledu webu.
 * Apache ji použije přes „ErrorDocument 404 /404.php“ (.htaccess) i pro chybějící
 * obrázky, PDF a jiné soubory. Jazyk se pozná z původní adresy (/en/… = angličtina).
 */
define('FORCE_404', true);
require __DIR__ . '/index.php';
