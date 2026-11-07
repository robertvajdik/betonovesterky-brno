# Betonové stěrky Brno – web (PHP + MySQL, CZ / EN)

Web řemeslníka – betonové stěrky, dekorativní omítky, malířské práce. Dvojjazyčný (čeština / angličtina),
s administrací pro poptávky, smlouvy, faktury, hodnocení a obsah.

---

# Co web umí

## Pro návštěvníky webu
- **Úvodní stránka** – hlavní fotka „pod bočním světlem“ (3D efekt: světlo sleduje myš a ukáže reliéf stěrky,
  na mobilu pomalu krouží), služby, výhody, poslední realizace, novinky z Facebooku, výzva ke kontaktu.
- **Služby a podkategorie** (betonové stěrky do kuchyně, koupelny, na krb, podlahy…, dekorativní omítky, malířské práce)
  s texty a galeriemi fotek, **Reference** (všechny fotky), **Realizace** (případové studie), **Ceník**.
- **Hodnocení** – převzatá hodnocení z nejremeslnici.cz s odkazem na zdroj a formulář pro nové hodnocení
  (zobrazí se až po schválení v administraci).
- **Kontakt** – zavolat, napsat, WhatsApp, mapa (OpenStreetMap, bez cookies), navigace do Google Maps, kroky „jak to probíhá“.
- **Poptávkový formulář** (Kontakt a Ceník) – pole upravitelná v administraci, preferovaný termín od–do,
  **až 5 fotek** toho, co chce zákazník natřít (v prohlížeči se zmenší a zbaví GPS), souhlas s GDPR, ochrana proti spamu.
- **Soukromý odkaz na poptávku** – po odeslání přijde zákazníkovi e-mail s odkazem `/poptavka/…`: stav poptávky
  (Přijato → Řešíme → Nabídka odeslána → Dokončeno), co napsal, jeho fotky, odpovědi, **smlouva o dílo** a **faktury**
  k zobrazení a tisku. Bez registrace.
- **Zákaznický účet** (volitelný) – registrace, přihlášení, přehled poptávek, zprávy, úprava profilu.
- **Angličtina** na `/en/…` s vlastními adresami, přepínač jazyka.
- **WhatsApp a Facebook** v horní liště, patičce a na kontaktu.
- **Mobil** – jednořádková horní lišta, vysouvací menu, plovoucí tlačítko Zavolat, velké dotykové plochy.
- **GDPR** – stránka Ochrana osobních údajů, lišta souhlasu s cookies (jen pokud je zapnutý Google Analytics),
  mapa a příspěvky z Facebooku bez cookies třetích stran, chráněný e-mail proti spamobotům.

## Administrace (`/admin/`)
Menu je v levém sloupci (na mobilu nahoře) na všech stránkách administrace.

| Záložka | Co umí |
|---|---|
| **Poptávky** | seznam i **kalendář** (termíny ze smluv a přání zákazníků); u každé poptávky všechna pole, přiložené fotky, cena a záloha ze smlouvy, faktury a jejich stav (zaplaceno / splatnost / po splatnosti); změna stavu, odpověď zákazníkovi (e-mailem i do jeho stránky), **Smlouva o dílo**, **Faktura**, **Poslat zákazníkovi odkaz**, smazání |
| **Zákazníci** | registrované účty, ověření e-mailu, blokace, smazání (poptávky zůstanou) |
| **Hodnocení** | schválení / skrytí, **úprava** (jméno, nadpis, lokalita, datum, hvězdy nebo bez hvězd, jazyk, text), smazání |
| **Galerie** | hromadné nahrávání fotek, popisky CZ/EN, pořadí, úvodní fotka stránky nebo realizace |
| **Stránky** | texty služeb a podkategorií CZ/EN, anglické URL, meta popisy |
| **Realizace** | případové studie CZ/EN |
| **Formulář** | pole poptávkového formuláře (typ, popisek CZ/EN, povinné, možnosti, pořadí) |
| **Texty CZ/EN** | všechny texty webu (tabulka `properties`), e-maily zákazníkům, lišta cookies, texty smlouvy |
| **Faktury** | přehled faktur, zaplacené / po splatnosti |
| **Nastavení** | záložky Firma · Grafika · SEO · robots.txt a sitemap · Google Analytics · Faktury; zkušební e-mail |

### Smlouva o dílo (`admin/contract.php`)
Z poptávky jedním tlačítkem – předvyplní zákazníka, předmět díla, plochu a termíny. Doplníte adresu, cenu bez DPH,
sazbu DPH (21 / 12 / 0 %), zálohu, splatnosti, záruku a zvláštní ujednání. Číslování `SoD-2026-001`.
Tisk / uložení do PDF z prohlížeče. Články V–IX (součinnost, předání, záruka, odstoupení…) upravíte v Texty CZ/EN → Smlouva o dílo.
**Text smlouvy je šablona – před použitím ho nechte zkontrolovat právníkem.**

### Faktury (`admin/invoice.php`)
- Z poptávky nebo smlouvy: **faktura – daňový doklad** nebo **zálohová faktura** (výzva k platbě, není daňový doklad).
- Položky s množstvím, jednotkou a sazbou DPH, rekapitulace DPH, odečet zaplacené zálohy (doplní se sama).
- **QR Platba** (standard ČBA) – IBAN se spočítá z čísla účtu. Číslo faktury = variabilní symbol (maska v Nastavení, např. `RRRR###` → 2026001).
- Tisk / PDF, označení zaplaceno, **odeslání e-mailem** (platební údaje + odkaz na fakturu s QR kódem).
- Neplátce DPH: v Nastavení → Faktury vypněte „Jsem plátce DPH“.
- **Postup u přijatých záloh u plátce DPH si ověřte s účetní** (daňový doklad k přijaté platbě).

### SEO
- Meta popisy, canonical, `hreflang`, Open Graph (obrázek pro sdílení), klíčová slova CZ/EN.
- Strukturovaná data schema.org: JSON-LD (firma, web, služby, realizace), Microdata (hodnocení s hvězdami, fotky), RDFa (drobečková navigace, kontakt).
  Každý formát lze vypnout v Nastavení → SEO. Ověření: https://validator.schema.org/
- `robots.txt` a `sitemap.xml` (CZ i EN) – dynamicky, nebo vygenerované do souborů (Nastavení → robots.txt a sitemap).
  Soukromé stránky (účet, poptávka) jsou vyloučené z indexace.

---

# Technická část

## Požadavky
PHP 8.1+ s `pdo_mysql`, `curl`, `openssl`, `iconv`; doporučeno `gd` (zmenšování fotek, ikony). MySQL 5.7+ / MariaDB 10.3+.
Apache 2.4 s `mod_rewrite`, nebo nginx s přepisem na `index.php` (viz níže).

## Instalace
1. `mysql -u root -p < database/schema.sql` vytvoří databázi, tabulky, texty CZ/EN a stránky.
2. `mysql -u root -p betonovesterky < database/import-original.sql` přiřadí fotky a grafiku z původního webu
   (soubory jsou v `uploads/orig/`, znovu je stáhne `php tools/import-original.php`).
3. Zkopírujte `config.example.php` → `config.php` a vyplňte (viz další oddíl).
4. Složky `uploads/` a kořen webu musí být zapisovatelné (fotky, robots.txt, sitemap.xml).
5. Otevřete `/admin/` – při první návštěvě si vytvoříte účet administrátora.

Lokálně: `php -d extension=pdo_mysql -S localhost:8000 index.php`

## config.php
`config.php` obsahuje hesla, do gitu se neukládá (`.gitignore`). Vzor je v `config.example.php` – **do vzoru nepište skutečná hesla**.

| Klíč | Význam |
|---|---|
| `db` | přístup k databázi |
| `base_url` | `null` = zjistí se samo (web v podadresáři) |
| `notify_email` | kam chodí upozornění na novou poptávku; zákazníci na něj odpovídají (Reply-To) |
| `mail_from` | odesílatel e-mailů (`null` = e-mail firmy z Nastavení) |
| `smtp` | SMTP server (`host`, `port`, `secure` `tls`/`ssl`, `user`, `pass`, `from`). Prázdný `host` = PHP `mail()`. **Odesílatel musí patřit k SMTP účtu / existující doméně**, jinak server e-mail odmítne. Test: Nastavení → Firma → „Poslat zkušební e-mail“. |
| `recaptcha` | Google reCAPTCHA v3 (`site_key`, `secret`, `min_score`); prázdné = vypnuto |
| `facebook` | `page_id` a `token` pro příspěvky z Facebooku (viz níže) |
| `app_key` | volitelné – tajný klíč pro soukromé odkazy zákazníků; bez něj se jednou vytvoří `includes/.app_key` (**soubor nemazat**, jinak přestanou fungovat odeslané odkazy) |

## Aktualizace existující instalace
Stačí nahrát nové soubory a otevřít web. `includes/migrate.php` při prvním načtení sám doplní chybějící tabulky, sloupce
a texty z `database/migrate.sql` (vaše úpravy textů nepřepíše). Verze databáze je ve vlastnosti `sys.db_version`.
Hodnocení z nejremeslnici.cz lze vložit i ručně: `database/reviews-import.sql` (phpMyAdmin → SQL, lze spustit opakovaně).

## E-maily
Všechny e-maily (nová poptávka, odkaz zákazníkovi, odpovědi, faktury, potvrzení účtu, obnova hesla) jdou přes `send_mail()`
v `includes/customer.php` – se SMTP z `config.php` (`includes/mailer.php`), jinak přes `mail()`. Texty: Texty CZ/EN → E-maily zákazníkům.

## Ochrana formulářů
Honeypot, CSRF token, omezení pokusů o přihlášení a volitelně **reCAPTCHA v3** (formuláře poptávky, hodnocení, registrace,
zapomenuté heslo). Skript Googlu se načte až při vyplňování formuláře. Když je Google nedostupný, formulář projde (chyba se zapíše do logu).

## Google Analytics
Nastavení → Google Analytics: zapnout a vložit Measurement ID `G-…`. Měření se spustí až po kliknutí na „Povolit měření“
v liště cookies (volba platí 180 dní, změna přes „Nastavení cookies“ v patičce).

## Facebook – příspěvky na úvodní stránce
Web si posledních 6 příspěvků stahuje sám (Graph API, 1× za hodinu) a fotky ukládá do `uploads/fb/`.
Návštěvník se k Facebooku nepřipojuje, takže není potřeba souhlas s cookies. Sekci vypnete v Nastavení → Firma.
**Bez tokenu** se zobrazí jen karta „Novinky z Facebooku“ s tlačítkem Sledovat (vložený plugin Facebooku u této stránky příspěvky neukazuje).

**Získání tokenu (jednorázově, dělá správce Facebook stránky):**
1. Na https://developers.facebook.com/apps vytvořte aplikaci (typ „Business“ / „Jiné“).
2. V Nástrojích → **Graph API Explorer** vyberte aplikaci, „Get Page Access Token“ a oprávnění
   `pages_show_list`, `pages_read_engagement`. Povolte přístup ke stránce.
3. Krátkodobý token prodlužte v **Access Token Debugger** („Extend Access Token“). Z dlouhodobého
   uživatelského tokenu pak v Explorer zavolejte `me/accounts` – u stránky je `access_token`, který nevyprší.
4. Token vložte do `config.php` → `'facebook' => ['page_id' => '100063858848240', 'token' => '…']`.

Token je tajný: patří jen do `config.php`. Když přestane platit, web dál ukazuje naposledy stažené příspěvky a chybu zapíše do error logu.

## Fotky k poptávkám (soukromé)
Ukládají se do `uploads/private/inquiries/{id}/` pod náhodnými názvy, přeuložené přes GD (bez metadat), a zobrazují se jen
přes PHP (administrace `admin/file.php` a soukromý odkaz zákazníka). Apache přímý přístup blokuje v `uploads/.htaccess`.
**nginx** `.htaccess` nečte – do konfigurace serveru přidejte: `location ^~ /uploads/private/ { deny all; }`
Se smazáním poptávky se smažou i její fotky.

## Jazyky
Čeština na `/…`, angličtina na `/en/…` s vlastními URL (např. `/en/kitchen/`). Prázdné EN pole = zobrazí se česká verze.
Administrace je česky; její texty jsou ve skupině `adm` (záloha v `includes/admin-texts.php`).

## Ochrana e-mailu
E-mail firmy není v HTML čitelný: je zakódovaný a skládá ho až JavaScript (bez JS `info [at] … [dot] cz`).
Vypnete v Nastavení → Firma; když je zapnutá, e-mail se nevypisuje ani do strukturovaných dat.

## Ikony
Ikony (telefon, e-mail, adresa, WhatsApp, Facebook, Instagram…) jsou inline SVG v `includes/icons.php` – `icon('phone')`.
Ikonu na plochu telefonu nahrajte v Nastavení → Grafika (čtverec min. 512 px); velikosti 180/192/512 se vygenerují (PHP GD).

## Přehled souborů
| Soubor | Účel |
|---|---|
| `index.php` | směrování, šablona `includes/layout.php`, stránky v `pages/` |
| `includes/functions.php` | texty `t()`, URL, poptávkový formulář |
| `includes/customer.php` | účty, e-maily, soukromé odkazy na poptávku |
| `includes/invoice.php` | IBAN, QR Platba, číslování faktur |
| `includes/mailer.php` | SMTP klient |
| `includes/recaptcha.php` | reCAPTCHA v3 |
| `includes/facebook.php` | stahování příspěvků z Facebooku |
| `includes/inquiry-files.php` | fotky k poptávkám |
| `includes/admin-nav.php`, `includes/admin-calendar.php` | menu a kalendář administrace |
| `admin/index.php`, `admin/contract.php`, `admin/invoice.php`, `admin/file.php` | administrace |
| `assets/js/main.js`, `assets/js/hero-light.js` | skripty webu, světelný efekt úvodní fotky |
| `database/schema.sql`, `database/migrate.sql` | nová instalace / automatické aktualizace |
