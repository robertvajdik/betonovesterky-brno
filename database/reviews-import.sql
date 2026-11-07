-- Hodnocení z nejremeslnici.cz – import do tabulky reviews (phpMyAdmin → SQL, nebo: mysql betonovesterky < database/reviews-import.sql)
-- Bezpečné spustit opakovaně: hodnocení se stejným autorem a datem se nevloží podruhé.
-- Pozn.: web tyto kroky dělá sám při aktualizaci databáze na verzi 8 – tento soubor je jen pro ruční import.
SET NAMES utf8mb4;

-- 1) Sloupec pro nadpis a hodnocení bez hvězd. Pokud sloupec title už existuje, první příkaz skončí chybou „Duplicate column“ – to nevadí, pokračujte.
ALTER TABLE reviews ADD title VARCHAR(160) NULL AFTER author;
ALTER TABLE reviews MODIFY rating TINYINT UNSIGNED NULL DEFAULT 5;

-- 2) Hodnocení (bez hvězd – rating NULL, rovnou schválená)
INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Lukas P.', 'Betonová stěrka na krb', NULL, 'Jsme moc spokojení s odvedenou prací od pana Tomáše Rückera. Přesně nám vytvořil dílo, dle našich požadavků a představ. Můžeme doporučit!', 'cs', 1, '2023-06-27 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Lukas P.' AND DATE(created_at) = '2023-06-27');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Marta K.', 'Aplikace dekorativní stěrky', NULL, 'S výsledkem práce jsme spokojení. S panem Rückerem výborná komunikace, skvěle odvedená práce. Spolupráce bude pokračovat.', 'cs', 1, '2019-08-13 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Marta K.' AND DATE(created_at) = '2019-08-13');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Vítězslav S.', 'Malířské práce 1+1', NULL, 'Profesionální přístup. Před vlastní realizací pan Rücker obhlídl stav a na základě toho stanovil cenu, která byla dodržena. Provedení dle dohody, z prací jsem maximálně spokojen včetně čistoty po skončení prací.\nTermíny byly dodrženy.', 'cs', 1, '2018-06-10 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Vítězslav S.' AND DATE(created_at) = '2018-06-10');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Zuzana M.', 'Malířské práce', NULL, 'Pan Rücker navrhl vhodný postup, jak vyřešit náročnější zadání tak, aby se cena příliš nenavyšovala. Jednalo se o byt v pronájmu, kterého nejsem majitelka, kde už bylo několik vrstev malby po bývalých nájemnících a stop po neodborných zásazích včetně různých nerovností po odstraněných poličkách a odlupující se omítky. Přípravu stěn měl hotovou rychle a zbyl i čas na další opravu drolícího se schodku mezi místnostmi (rekonstrukce a vhodný nátěr) a nezbytnou korekci stropu ve druhém bytě, jak jsem žádala. Celá práce zabrala 4 dny.', 'cs', 1, '2018-06-07 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Zuzana M.' AND DATE(created_at) = '2018-06-07');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Miroslav S.', 'Vymalování 2+kk (50m2)', NULL, 'Naprosto profesionální přístup. Pan Rücker přijel i na vlastní náklady byt obhlédnout, domluvit se na postupu, či zda bude třeba oškrábat stěny. Také navrhl místa která bylo vhodné speciálně ošetřit, aby nedošlo k umaštění (okolí sporáku).', 'cs', 1, '2017-10-26 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Miroslav S.' AND DATE(created_at) = '2017-10-26');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT '', 'Malířské práce (rodinný domek)', NULL, 'S realizací naprostá spokojenost, sjednaný termín i dohodnutá cena dodrženy, komunikace při sjednávání zakázky i v průběhu realizace věcná a bez problémů.', 'cs', 1, '2017-06-19 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = '' AND DATE(created_at) = '2017-06-19');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Magda S.', 'Výmalba celého bytu 3+kk', NULL, 'Velmi dobrá komunikace, dodržen termín dodání, závady jsme neobjevili, dobré poradenství ohledně údržby.', 'cs', 1, '2017-06-14 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Magda S.' AND DATE(created_at) = '2017-06-14');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Jan P.', 'Realizace stěrky v interiéru bytu', NULL, 'Celkově spokojenost s výsledkem, pan Rucker je vstřícný, komunikuje a snaží se řešit operativně i potíže v našem případě s uchycením krytek vypínačů po nánosu vícevrstevné stěrky.\nUrčitě mohu doporučit.', 'cs', 1, '2017-05-11 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Jan P.' AND DATE(created_at) = '2017-05-11');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Veronika T.', 'Malování pokoje a chodby', NULL, 'Pan Rucker je velmi profesionální řemeslník a milý člověk. Vedle výmalby byl také nad rámec sjednané práce schopný a ochotný opravit práci po zedníkovi, tak aby konečný výsledek mohl být dokonalý. VELKÝ DÍK, VŘELE DOPORUČUJI!', 'cs', 1, '2017-01-13 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Veronika T.' AND DATE(created_at) = '2017-01-13');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Haitmarová P.', 'Vymalování pokojů', NULL, 'Pan Rucker jednal velmi vstřícně. Vše proběhlo přesně dle domluvy. Práce je provedena velice precizně, zejména opravy zdí před finálním nátěrem. Z důvodu velkého množství oprav hrozilo zpoždění termínu dokončení zakázky, ale pan Rucker okamžitě domluvil spolupráci s kolegou a vše proběhlo báječně. Místnost nám předal čistou s minimální nutností úklidu.\nMůžu jen doporučit.', 'cs', 1, '2016-02-18 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Haitmarová P.' AND DATE(created_at) = '2016-02-18');

INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at)
SELECT 'Vlastimil S.', 'Vyrovnání stěn a vymalování', NULL, 'Dohodnutý termín byl dodržen, komunikace s dodavatelem naplnila moje očekávání a po realizaci celého díla se zatím žádná viditelná vada neobjevila, takže tuhle firmu mohu určitě doporučit :-).', 'cs', 1, '2015-11-19 12:00:00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE author = 'Vlastimil S.' AND DATE(created_at) = '2015-11-19');

