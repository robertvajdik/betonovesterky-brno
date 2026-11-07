# Betonové stěrky Brno – web (PHP + MySQL, CZ / EN)

## Požadavky
PHP 8.1+ s `pdo_mysql`, `curl`, `iconv`. MySQL 5.7+ / MariaDB 10.3+. Apache s `mod_rewrite`, nebo nginx s přepisem na `index.php`.

## Instalace
1. `mysql -u root -p < database/schema.sql` vytvoří databázi, tabulky, texty CZ/EN a stránky.
2. `mysql -u root -p betonovesterky < database/import-original.sql` přiřadí fotky a grafiku z původního webu.
   Soubory už jsou v `uploads/orig/`. Znovu je stáhnete příkazem `php tools/import-original.php`.
3. V `config.php` nastavte přístup k DB a `notify_email`.
4. Složky `uploads/` a kořen webu musí být zapisovatelné (kvůli fotkám, robots.txt a sitemap.xml).
5. Otevřete `/admin/`. Při první návštěvě si vytvoříte účet.

Lokálně: `php -d extension=pdo_mysql -S localhost:8000 index.php`

## Jazyky
- Čeština je na `/…`, angličtina na `/en/…` s vlastními URL (např. `/en/kitchen/`).
- Přepínač jazyka je v horní liště i v mobilním menu. Stránky mají značky `hreflang` a canonical.
- Prázdné EN pole = zobrazí se česká verze.

## Administrace (`/admin/`)
| Záložka | Co umí |
|---|---|
| Poptávky | přehled odeslaných formulářů se všemi poli, označit jako vyřízené, smazat |
| Hodnocení | schvalování recenzí od zákazníků |
| Galerie | hromadné nahrávání, popisky CZ/EN, pořadí, úvodní fotka stránky nebo realizace |
| Stránky | texty služeb a podkategorií v CZ/EN, EN URL, meta popisy |
| Realizace | případové studie v CZ/EN |
| Formulář | editor polí poptávkového formuláře (typ, popisek CZ/EN, povinné, možnosti výběru, pořadí) |
| Texty CZ/EN | všechny texty šablon (tabulka `properties`) a vlastní nové texty |
| Nastavení | firma a kontakty, grafika (logo, favicon, hlavní fotka…), SEO, generování robots.txt a sitemap.xml |

## Strukturovaná data (schema.org)
Všechny formáty popisují stejnou firmu (`…/#business`), vyhledávač je spojí v jednu entitu. Každý formát lze vypnout v **Nastavení → SEO**.
- **JSON-LD** (v `<head>`): `HomeAndConstructionBusiness` (adresa, telefon, IČO, majitel, nabídka služeb), `WebSite`, `WebPage` / `ContactPage` / `CollectionPage`, na stránkách služeb `Service`, u realizací `CreativeWork` s fotkami.
- **Microdata**: hodnocení (`Review`, `AggregateRating`) a fotky v galeriích (`ImageObject`).
- **RDFa**: drobečková navigace (`BreadcrumbList`) a kontakt v patičce.

Ověření: https://validator.schema.org/ a https://search.google.com/test/rich-results

## Ochrana e-mailu
E-mailová adresa není v HTML čitelná: je zakódovaná a skládá ji až JavaScript. Bez JS se zobrazí `info [at] … [dot] cz`.
Ochranu vypnete v Nastavení → Firma. Když je zapnutá, e-mail se nevypisuje ani do strukturovaných dat.

## Aktualizace existující instalace
Stačí nahrát nové soubory a otevřít web. `includes/migrate.php` při prvním načtení sám doplní chybějící tabulky,
sloupce a texty z `database/migrate.sql` (vaše úpravy nepřepíše). Verze databáze je ve vlastnosti `sys.db_version`.

## Zákaznické účty
- Registrace `/registrace/`, přihlášení `/prihlaseni/`, zapomenuté heslo `/zapomenute-heslo/`, účet `/muj-ucet/` (EN: `/en/register/`, `/en/login/`, `/en/forgot-password/`, `/en/my-account/`).
- V účtu zákazník vidí své poptávky a jejich stav, může k nim psát zprávy a upravit profil, změnit heslo nebo účet smazat.
- Přihlášenému se poptávkový formulář předvyplní a poptávka se uloží do jeho účtu.
- Po potvrzení e-mailu se k účtu připojí i dřívější poptávky odeslané z téže adresy bez přihlášení.
- V administraci: **Poptávky** → stav a odpověď (uloží se do účtu a pošle e-mailem), **Zákazníci** → přehled, blokace, smazání.
- Zapnutí/vypnutí: Nastavení → Firma a kontakty → „Zákaznické účty“. Texty: Texty CZ/EN → Zákaznický účet, Stavy poptávek, E-maily zákazníkům.
- E-maily odchází přes PHP `mail()`. Odesílatele nastavíte v `config.php` (`mail_from`) – kvůli SPF by měl patřit doméně serveru.

## Ikony
Kontaktní ikony (telefon, mobil, e-mail, adresa, Facebook, Instagram) jsou inline SVG v `includes/icons.php` – `icon('phone')`.
Ikonu na plochu telefonu nahrajte v Nastavení → Grafika → „Ikona na plochu telefonu“ (čtverec min. 512 px). Velikosti 180/192/512 se vygenerují samy (PHP GD).

## Git
`config.php` (hesla k DB) a exporty databáze z hostingu se do gitu neukládají – viz `.gitignore`. Vzor konfigurace je v `config.example.php`.

## robots.txt a sitemap.xml
Dokud soubory nevygenerujete, web je servíruje dynamicky. Tlačítko v **Nastavení → SEO** je zapíše jako statické soubory.
Volba „Automaticky přegenerovat“ je pak obnoví při každé změně obsahu.
Sitemap obsahuje CZ i EN adresy s `hreflang`. Pro absolutní URL vyplňte doménu v Nastavení → Firma.
