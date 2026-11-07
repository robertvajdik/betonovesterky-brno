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

    // Veřejná adresa webu bez lomítka na konci (odkazy v e-mailech, canonical, sitemap).
    // Prázdné = Nastavení → Firma → Doména webu, případně aktuální adresa.
    'domain' => '',

    // Kam posílat upozornění na novou poptávku (prázdné = jen uložit do DB)
    'notify_email' => 'info@betonovesterky-brno.cz',

    // Odesílatel e-mailů webu (null = e-mail firmy z Nastavení). Měl by patřit doméně serveru kvůli SPF.
    'mail_from' => null,

    // Odesílání e-mailů přes SMTP (doporučeno – e-maily nepadají do spamu). Prázdný host = PHP mail().
    // Údaje najdete u poskytovatele e-mailu (např. Seznam: smtp.seznam.cz, 465, ssl; Gmail: smtp.gmail.com, 587, tls + heslo aplikace).
    'smtp' => [
        'host'   => '',           // např. smtp.cesky-hosting.cz
        'port'   => 587,
        'secure' => 'tls',        // 'tls' (STARTTLS, port 587) | 'ssl' (port 465) | '' (bez šifrování – nedoporučeno)
        'user'   => '',
        'pass'   => '',
        'from'   => '',           // adresa odesílatele; prázdné = mail_from / e-mail firmy. Musí patřit k SMTP účtu.
    ],

    // Google reCAPTCHA v3 – ochrana formulářů proti spamu (https://www.google.com/recaptcha/admin, typ „v3“)
    // Prázdné klíče = ochrana vypnutá. min_score: 0.0 (vše propustit) … 1.0 (nejpřísnější), doporučeno 0.5.
    'recaptcha' => [
        'site_key'  => '',
        'secret'    => '',
        'min_score' => 0.5,
    ],

    // Příspěvky z Facebooku na úvodní stránce (web je stahuje sám, 1× za hodinu). Postup získání tokenu: README → Facebook.
    // Prázdné = místo příspěvků se zobrazí tlačítko, které načte oficiální plugin Facebooku až po kliknutí.
    'facebook' => [
        'page_id' => '100063858848240',
        'token'   => '',          // Page access token (dlouhodobý); nikomu ho neposílejte
    ],
];
