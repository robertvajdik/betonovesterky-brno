<?php
// ------------------------------------------------------------
// Technická konfigurace. Údaje o firmě, texty (CZ/EN), grafika,
// formulář a SEO se upravují v administraci (/admin/) → Nastavení.
// ------------------------------------------------------------
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'basketubcz06',
        'user'    => 'basketubcz007',
        'pass'    => 'xfagvbentonIlly',
        'charset' => 'utf8mb4',
    ],

    // Necháte-li null, cesta se zjistí automaticky (např. '' nebo '/betonovesterky')
    'base_url' => null,

    // Kam posílat upozornění na novou poptávku (prázdné = jen uložit do DB)
    'notify_email' => 'info@betonovesterky-brno.cz',
];
