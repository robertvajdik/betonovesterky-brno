<?php
// ------------------------------------------------------------
// Vzor konfigurace. Zkopírujte jako config.php a vyplňte.
// config.php se do gitu neukládá (obsahuje hesla).
// Údaje o firmě, texty (CZ/EN), grafika, formulář a SEO se upravují v administraci (/admin/).
// ------------------------------------------------------------
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'betonovesterky',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // Necháte-li null, cesta se zjistí automaticky (např. '' nebo '/betonovesterky')
    'base_url' => null,

    // Kam posílat upozornění na novou poptávku (prázdné = jen uložit do DB)
    'notify_email' => 'info@betonovesterky-brno.cz',

    // Odesílatel e-mailů webu (null = e-mail firmy z Nastavení). Měl by patřit doméně serveru kvůli SPF.
    'mail_from' => null,

    // Příspěvky z Facebooku na úvodní stránce (web je stahuje sám, 1× za hodinu). Postup získání tokenu: README → Facebook.
    // Prázdné = místo příspěvků se zobrazí tlačítko, které načte oficiální plugin Facebooku až po kliknutí.
    'facebook' => [
        'page_id' => '100063858848240',
        'token'   => '',          // Page access token (dlouhodobý); nikomu ho neposílejte
    ],
];
