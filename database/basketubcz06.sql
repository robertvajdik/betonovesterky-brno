-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: irene.ch.inthin
-- Erstellungszeit: 07. Nov 2026 um 13:38
-- Server-Version: 11.8.6-MariaDB-0+deb13u1 from Debian-log
-- PHP-Version: 8.2.35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `basketubcz06`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `slug` varchar(120) NOT NULL,
  `slug_en` varchar(120) DEFAULT NULL,
  `area` enum('service','interior','exterior') NOT NULL DEFAULT 'interior',
  `title` varchar(160) NOT NULL,
  `title_en` varchar(160) DEFAULT NULL,
  `menu_title` varchar(80) DEFAULT NULL,
  `menu_title_en` varchar(80) DEFAULT NULL,
  `headline` varchar(200) DEFAULT NULL,
  `headline_en` varchar(200) DEFAULT NULL,
  `intro` text DEFAULT NULL,
  `intro_en` text DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `body_en` mediumtext DEFAULT NULL,
  `meta_desc` varchar(300) DEFAULT NULL,
  `meta_desc_en` varchar(300) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

--
-- Daten für Tabelle `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `slug`, `slug_en`, `area`, `title`, `title_en`, `menu_title`, `menu_title_en`, `headline`, `headline_en`, `intro`, `intro_en`, `body`, `body_en`, `meta_desc`, `meta_desc_en`, `cover_image`, `sort`, `is_active`) VALUES
(1, NULL, 'betonove-sterky', 'concrete-finishes', 'service', 'Betonové stěrky', 'Concrete finishes', 'Betonové stěrky', 'Concrete finishes', 'Betonové stěrky pro interiér i exteriér', 'Concrete finishes for interiors and exteriors', 'Bezespárý povrch s vzhledem pohledového betonu, který zvládne stěny, podlahy, schody i nábytek – bez bourání a s minimální tloušťkou vrstvy.', 'A seamless surface with the look of exposed concrete for walls, floors, stairs and furniture – no demolition and only a few millimetres thick.', '<p>Stěrka se nanáší ručně v několika tenkých vrstvách přímo na stávající podklad – omítku, sádrokarton, dlažbu, dřevo i beton. Každá plocha je tak originál s jemnou kresbou, kterou u průmyslově vyráběných materiálů nenajdete.</p><p>Na závěr povrch ošetříme impregnací a lakem podle zátěže: v koupelně proti vodě, na podlaze proti otěru, v kuchyni proti mastnotě.</p>', '<p>The finish is applied by hand in several thin layers directly onto the existing substrate – plaster, plasterboard, tiles, wood or concrete. Every surface is one of a kind, with a subtle pattern you will not find in mass-produced materials.</p><p>Finally, we seal and lacquer the surface according to its use: against water in bathrooms, against wear on floors and against grease in kitchens.</p>', NULL, NULL, 'orig/tpl-sekce-4-betonove-sterky-1.jpg', 10, 1),
(2, NULL, 'dekorativni-omitky', 'decorative-plasters', 'service', 'Dekorativní omítky', 'Decorative plasters', 'Dekorativní omítky', 'Decorative plasters', 'Dekorativní omítky s charakterem', 'Decorative plasters with character', 'Metalické efekty, struktura kamene, rez nebo jemný štuk – vzorky vám rádi ukážeme naživo, protože fotka strukturu nikdy úplně nevystihne.', 'Metallic effects, stone textures, rust or fine stucco – we will gladly show you samples in person, because a photo never fully captures the texture.', '<p>Dekorativní omítky jsou ideální na akcentní stěnu v obývacím pokoji, do recepce nebo restaurace. Pomůžeme vám vybrat odstín i strukturu tak, aby ladila s nábytkem a světlem v místnosti.</p>', '<p>Decorative plasters are ideal for a feature wall in a living room, reception area or restaurant. We will help you choose a shade and texture that match your furniture and the light in the room.</p>', NULL, NULL, 'orig/tpl-sekce-4-dekorativni_omitky.jpg', 20, 1),
(3, NULL, 'malirske-prace', 'painting', 'service', 'Malířské práce', 'Painting', 'Malířské práce', 'Painting', 'Malířské práce bez starostí', 'Hassle-free painting', 'Vymalujeme byt, dům i kanceláře. Poradíme, jakou barvu zvolit do které místnosti, a po práci po sobě uklidíme.', 'We paint flats, houses and offices. We advise which paint suits which room, and we clean up after ourselves.', '<p><strong>Klasické (nedisperzní) barvy</strong> jsou cenově dostupné a prodyšné, hodí se do ložnic a obytných místností s nízkou zátěží.</p><p><strong>Disperzní a latexové barvy</strong> jsou omyvatelné a odolnější – volte je do kuchyní, chodeb, dětských pokojů nebo koupelen.</p>', '<p><strong>Traditional (non-dispersion) paints</strong> are affordable and breathable, suitable for bedrooms and low-traffic living spaces.</p><p><strong>Dispersion and latex paints</strong> are washable and more durable – choose them for kitchens, hallways, children\'s rooms and bathrooms.</p>', NULL, NULL, 'orig/tpl-sekce-4-malirske_prace.jpg', 30, 1),
(10, 1, 'do-kuchyne', 'kitchen', 'interior', 'Betonová stěrka do kuchyně', 'Concrete finish for kitchens', 'Do kuchyně', 'Kitchen', 'Stěrka do kuchyně', 'Kitchen finishes', 'Zástěna za linkou, pracovní deska i stěny – povrch odolný vůči mastnotě a snadno omyvatelný.', 'Splashbacks, worktops and walls – a grease-resistant surface that is easy to clean.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-4-kuchyne.jpg', 10, 1),
(11, 1, 'do-koupelny', 'bathroom', 'interior', 'Betonová stěrka do koupelny', 'Concrete finish for bathrooms', 'Do koupelny', 'Bathroom', 'Stěrka do koupelny', 'Bathroom finishes', 'Koupelna bez spár a obkladů. Speciální vodoodpudivý systém i do sprchového koutu.', 'A bathroom without joints or tiles. A special water-repellent system, even for showers.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-4-koupelna2.jpg', 20, 1),
(12, 1, 'do-obyvaciho-pokoje', 'living-room', 'interior', 'Betonová stěrka do obývacího pokoje', 'Concrete finish for living rooms', 'Do obývacího pokoje', 'Living room', 'Stěrka do obývacího pokoje', 'Living room finishes', 'Akcentní stěna nebo celý prostor v industriálním či minimalistickém stylu.', 'A feature wall or the whole space in an industrial or minimalist style.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-4-obyvak.jpg', 30, 1),
(13, 1, 'na-podlahu', 'floors', 'interior', 'Betonová stěrka na podlahu', 'Concrete finish for floors', 'Na podlahu', 'Floors', 'Stěrka na podlahu', 'Floor finishes', 'Litý vzhled bez spár – i přes stávající dlažbu, s vrchním lakem pro vysokou zátěž.', 'A poured, seamless look – even over existing tiles, with a heavy-duty top coat.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-4-podlahy.jpg', 40, 1),
(14, 1, 'na-schody', 'stairs', 'interior', 'Betonová stěrka na schody', 'Concrete finish for stairs', 'Na schody', 'Stairs', 'Stěrka na schody', 'Stair finishes', 'Renovace schodiště bez bourání. Protiskluzová úprava a pevné hrany.', 'Staircase renovation without demolition. Anti-slip finish and solid edges.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-4-schody2.jpg', 50, 1),
(15, 1, 'na-nabytek', 'furniture', 'interior', 'Betonová stěrka na nábytek', 'Concrete finish for furniture', 'Na nábytek', 'Furniture', 'Stěrka na nábytek', 'Furniture finishes', 'Stoly, komody, pulty i dvířka – starý kus nábytku získá nový, moderní povrch.', 'Tables, chests, counters and doors – give old furniture a new, modern surface.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-4-koupelna.jpg', 60, 1),
(16, 1, 'na-krb', 'fireplace', 'interior', 'Betonová stěrka na krb', 'Concrete finish for fireplaces', 'Na krb', 'Fireplace', 'Stěrka na krb', 'Fireplace finishes', 'Obklad krbu a krbové stěny materiálem, který snese teplo a vypadá jako masivní beton.', 'Cladding for fireplaces and chimney walls that withstands heat and looks like solid concrete.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-4-krby.jpg', 70, 1),
(20, 1, 'fasada', 'facade', 'exterior', 'Betonová stěrka na fasádu', 'Concrete finish for facades', 'Fasáda', 'Facade', 'Stěrka na fasádu', 'Facade finishes', 'Mrazuvzdorný systém pro vnější stěny, sokly a vstupy do domu.', 'A frost-resistant system for external walls, plinths and entrances.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-7-fasada.jpg', 80, 1),
(21, 1, 'plot', 'fence', 'exterior', 'Betonová stěrka na plot', 'Concrete finish for fences', 'Plot', 'Fence', 'Stěrka na plot', 'Fence finishes', 'Zděné ploty a podezdívky s vzhledem pohledového betonu, odolné vůči povětrnosti.', 'Masonry fences and footings with an exposed-concrete look, resistant to weather.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-7-plot.jpg', 90, 1),
(22, 1, 'venkovni-betonova-sterka', 'outdoor', 'exterior', 'Venkovní betonová stěrka', 'Outdoor concrete finishes', 'Terasy a exteriér', 'Terraces & outdoor', 'Venkovní stěrka', 'Outdoor finishes', 'Terasy, balkony a venkovní schodiště – opravy i nové povrchy odolné vůči vodě a mrazu.', 'Terraces, balconies and outdoor stairs – repairs and new surfaces resistant to water and frost.', NULL, NULL, NULL, NULL, 'orig/tpl-sekce-7-betonove-sterky-2.jpg', 100, 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `form_fields`
--

CREATE TABLE `form_fields` (
  `id` int(10) UNSIGNED NOT NULL,
  `fkey` varchar(40) NOT NULL,
  `type` enum('text','email','tel','number','textarea','select','checkbox','date') NOT NULL DEFAULT 'text',
  `label_cs` varchar(160) NOT NULL,
  `label_en` varchar(160) DEFAULT NULL,
  `placeholder_cs` varchar(200) DEFAULT NULL,
  `placeholder_en` varchar(200) DEFAULT NULL,
  `options_cs` text DEFAULT NULL,
  `options_en` text DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_wide` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

--
-- Daten für Tabelle `form_fields`
--

INSERT INTO `form_fields` (`id`, `fkey`, `type`, `label_cs`, `label_en`, `placeholder_cs`, `placeholder_en`, `options_cs`, `options_en`, `is_required`, `is_wide`, `is_active`, `sort`) VALUES
(1, 'name', 'text', 'Jméno a příjmení', 'Full name', NULL, NULL, NULL, NULL, 1, 0, 1, 10),
(2, 'email', 'email', 'E-mail', 'Email', NULL, NULL, NULL, NULL, 1, 0, 1, 20),
(3, 'phone', 'tel', 'Telefon', 'Phone', NULL, NULL, NULL, NULL, 0, 0, 1, 30),
(4, 'location', 'text', 'Místo realizace', 'Job location', 'např. Brno – Žabovřesky', 'e.g. Brno – Žabovřesky', NULL, NULL, 0, 0, 1, 40),
(5, 'service', 'select', 'Služba', 'Service', NULL, NULL, 'Betonová stěrka – interiér\nBetonová stěrka – exteriér\nDekorativní omítka\nMalířské práce\nJiné', 'Concrete finish – interior\nConcrete finish – exterior\nDecorative plaster\nPainting\nOther', 0, 0, 1, 50),
(6, 'area', 'number', 'Přibližná plocha (m²)', 'Approximate area (m²)', NULL, NULL, NULL, NULL, 0, 0, 1, 60),
(7, 'message', 'textarea', 'Co plánujete?', 'What are you planning?', 'Jaký prostor, podklad, termín…', 'Which room, substrate, timing…', NULL, NULL, 1, 1, 1, 70);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `gallery_images`
--

CREATE TABLE `gallery_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `file` varchar(255) NOT NULL,
  `caption` varchar(200) DEFAULT NULL,
  `caption_en` varchar(200) DEFAULT NULL,
  `in_references` tinyint(1) NOT NULL DEFAULT 1,
  `sort` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

--
-- Daten für Tabelle `gallery_images`
--

INSERT INTO `gallery_images` (`id`, `category_id`, `project_id`, `file`, `caption`, `caption_en`, `in_references`, `sort`, `created_at`) VALUES
(1, 1, NULL, 'orig/betonove-sterky_737.png', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(2, 1, NULL, 'orig/betonove-sterky_736.png', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(3, 1, NULL, 'orig/betonove-sterky_735.png', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(4, 1, NULL, 'orig/betonove-sterky_566.jpg', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(5, 1, NULL, 'orig/betonove-sterky_578.jpg', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(6, 1, NULL, 'orig/betonove-sterky_572.jpg', NULL, NULL, 1, 6, '2026-11-07 13:25:20'),
(7, 1, NULL, 'orig/betonove-sterky_574.jpg', NULL, NULL, 1, 7, '2026-11-07 13:25:20'),
(8, 1, NULL, 'orig/betonove-sterky_565.jpg', NULL, NULL, 1, 8, '2026-11-07 13:25:20'),
(9, 1, NULL, 'orig/betonove-sterky_576.jpg', NULL, NULL, 1, 9, '2026-11-07 13:25:20'),
(10, 1, NULL, 'orig/betonove-sterky_573.jpg', NULL, NULL, 1, 10, '2026-11-07 13:25:20'),
(11, 1, NULL, 'orig/betonove-sterky_567.jpg', NULL, NULL, 1, 11, '2026-11-07 13:25:20'),
(12, 1, NULL, 'orig/betonove-sterky_568.jpg', NULL, NULL, 1, 12, '2026-11-07 13:25:20'),
(13, 1, NULL, 'orig/betonove-sterky_575.jpg', NULL, NULL, 1, 13, '2026-11-07 13:25:20'),
(14, 1, NULL, 'orig/betonove-sterky_570.jpg', NULL, NULL, 1, 14, '2026-11-07 13:25:20'),
(15, 1, NULL, 'orig/betonove-sterky_577.jpg', NULL, NULL, 1, 15, '2026-11-07 13:25:20'),
(16, 1, NULL, 'orig/betonove-sterky_571.jpg', NULL, NULL, 1, 16, '2026-11-07 13:25:20'),
(17, 1, NULL, 'orig/betonove-sterky_569.jpg', NULL, NULL, 1, 17, '2026-11-07 13:25:20'),
(18, 10, NULL, 'orig/betonova-sterka-na-pracovni-desce-i-za-ni_595.jpg', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(19, 10, NULL, 'orig/betonova-sterka-na-pracovni-desce-i-za-ni_604.jpg', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(20, 10, NULL, 'orig/elegantni-seda-kuchyne-s-betonovou-sterkou_602.jpg', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(21, 10, NULL, 'orig/betonovou-sterku-za-kuchynskou-desku-i-na-podlahu_601.jpg', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(22, 10, NULL, 'orig/betonovou-sterku-za-kuchynskou-desku-i-na-podlahu_599.jpg', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(23, 10, NULL, 'orig/betonova-sterka-za-kuchynskou-deskou_600.jpg', NULL, NULL, 1, 6, '2026-11-07 13:25:20'),
(24, 10, NULL, 'orig/betonova-sterka-za-kuchynskou-deskou_596.jpg', NULL, NULL, 1, 7, '2026-11-07 13:25:20'),
(25, 10, NULL, 'orig/jak-kombinovat-betonovou-sterku-v-kuchyni_598.jpg', NULL, NULL, 1, 8, '2026-11-07 13:25:20'),
(26, 10, NULL, 'orig/betonova-sterka-za-kuchynskou-deskou_675.jpg', NULL, NULL, 1, 9, '2026-11-07 13:25:20'),
(27, 10, NULL, 'orig/betonova-sterka-za-kuchynskou-deskou_674.jpg', NULL, NULL, 1, 10, '2026-11-07 13:25:20'),
(28, 10, NULL, 'orig/betonova-sterka-za-kuchynskou-deskou_676.jpg', NULL, NULL, 1, 11, '2026-11-07 13:25:20'),
(29, 10, NULL, 'orig/elegantni-betonova-sterka-za-kuchynskou-deskou_597.jpg', NULL, NULL, 1, 12, '2026-11-07 13:25:20'),
(30, 10, NULL, 'orig/realizace-betonove-sterky-v-kuchyni_603.jpg', NULL, NULL, 1, 13, '2026-11-07 13:25:20'),
(31, 10, NULL, 'orig/betonova-sterka-za-kuchynskou-deskou_605.jpg', NULL, NULL, 1, 14, '2026-11-07 13:25:20'),
(32, 10, NULL, 'orig/betonova-sterka-za-kuchynskou-deskou_606.jpg', NULL, NULL, 1, 15, '2026-11-07 13:25:20'),
(33, 11, NULL, 'orig/do-koupelny_672.jpg', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(34, 11, NULL, 'orig/do-koupelny_673.jpg', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(35, 12, NULL, 'orig/do-obyvaciho-pokoje_607.webp', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(36, 12, NULL, 'orig/do-obyvaciho-pokoje_608.jpg', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(37, 12, NULL, 'orig/do-obyvaciho-pokoje_611.jpg', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(38, 12, NULL, 'orig/do-obyvaciho-pokoje_609.jpg', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(39, 13, NULL, 'orig/na-podlahu_613.jpg', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(40, 13, NULL, 'orig/na-podlahu_614.jpg', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(41, 13, NULL, 'orig/na-podlahu_612.jpg', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(42, 13, NULL, 'orig/na-podlahu_615.jpg', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(43, 14, NULL, 'orig/na-schody_732.png', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(44, 14, NULL, 'orig/na-schody_733.png', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(45, 14, NULL, 'orig/na-schody_734.png', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(46, 14, NULL, 'orig/na-schody_677.jpg', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(47, 14, NULL, 'orig/na-schody_679.jpg', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(48, 14, NULL, 'orig/na-schody_678.jpg', NULL, NULL, 1, 6, '2026-11-07 13:25:20'),
(49, 14, NULL, 'orig/na-schody_616.jpg', NULL, NULL, 1, 7, '2026-11-07 13:25:20'),
(50, 14, NULL, 'orig/na-schody_617.jpg', NULL, NULL, 1, 8, '2026-11-07 13:25:20'),
(51, 16, NULL, 'orig/na-krb_618.jpg', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(52, 16, NULL, 'orig/na-krb_619.jpg', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(53, 16, NULL, 'orig/na-krb_728.png', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(54, 16, NULL, 'orig/na-krb_729.png', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(55, 16, NULL, 'orig/na-krb_730.png', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(56, 16, NULL, 'orig/na-krb_731.png', NULL, NULL, 1, 6, '2026-11-07 13:25:20'),
(57, 22, NULL, 'orig/oprava-terasy-v-beskydech_738.png', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(58, 22, NULL, 'orig/oprava-terasy-v-beskydech_739.png', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(59, 22, NULL, 'orig/oprava-terasy-v-beskydech_740.png', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(60, 2, NULL, 'orig/dekorativni-omitky_581.jpg', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(61, 2, NULL, 'orig/dekorativni-omitky_580.jpg', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(62, 2, NULL, 'orig/dekorativni-omitky_579.jpg', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(63, 2, NULL, 'orig/dekorativni-omitky_586.jpg', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(64, 2, NULL, 'orig/dekorativni-omitky_583.jpg', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(65, 2, NULL, 'orig/dekorativni-omitky_584.jpg', NULL, NULL, 1, 6, '2026-11-07 13:25:20'),
(66, 2, NULL, 'orig/dekorativni-omitky_582.jpg', NULL, NULL, 1, 7, '2026-11-07 13:25:20'),
(67, 2, NULL, 'orig/dekorativni-omitky_585.jpg', NULL, NULL, 1, 8, '2026-11-07 13:25:20'),
(68, 3, NULL, 'orig/malirske-prace_588.jpg', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(69, 3, NULL, 'orig/malirske-prace_591.jpg', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(70, 3, NULL, 'orig/malirske-prace_593.jpg', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(71, 3, NULL, 'orig/malirske-prace_587.jpg', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(72, 3, NULL, 'orig/malirske-prace_592.jpg', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(73, 3, NULL, 'orig/malirske-prace_590.jpg', NULL, NULL, 1, 6, '2026-11-07 13:25:20'),
(74, 3, NULL, 'orig/malirske-prace_594.jpg', NULL, NULL, 1, 7, '2026-11-07 13:25:20'),
(75, 3, NULL, 'orig/malirske-prace_589.jpg', NULL, NULL, 1, 8, '2026-11-07 13:25:20'),
(76, NULL, 2, 'orig/oprava-terasy-v-beskydech_707.png', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(77, NULL, 2, 'orig/oprava-terasy-v-beskydech_704.png', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(78, NULL, 2, 'orig/oprava-terasy-v-beskydech_708.png', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(79, NULL, 2, 'orig/oprava-betonove-terasy_706.png', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(80, NULL, 2, 'orig/oprava-terasy-v-beskydech_701.png', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(81, NULL, 2, 'orig/oprava-terasy-v-beskydech_702.png', NULL, NULL, 1, 6, '2026-11-07 13:25:20'),
(82, NULL, 2, 'orig/oprava-terasy-v-beskydech_703.png', NULL, NULL, 1, 7, '2026-11-07 13:25:20'),
(83, NULL, 3, 'orig/betonova-sterka-na-krb-s-mandalami_710.png', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(84, NULL, 3, 'orig/betonova-sterka-na-krb-s-mandalami_709.png', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(85, NULL, 3, 'orig/betonova-sterka-na-krb-s-mandalami_711.png', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(86, NULL, 3, 'orig/betonova-sterka-na-krb-s-mandalami_712.png', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(87, NULL, 3, 'orig/betonova-sterka-na-krb-s-mandalami_713.png', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(88, NULL, 4, 'orig/realizace-mikrocementove-sterky-a-schodech_715.png', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(89, NULL, 4, 'orig/realizace-mikrocementove-sterky-a-schodech_716.png', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(90, NULL, 4, 'orig/realizace-mikrocementove-sterky-a-schodech_717.png', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(91, NULL, 4, 'orig/realizace-mikrocementove-sterky-a-schodech_718.png', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(92, NULL, 4, 'orig/realizace-mikrocementove-sterky-a-schodech_719.png', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(93, NULL, 5, 'orig/betonova-sterka-na-stenach-kolem-schodiste_720.png', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(94, NULL, 5, 'orig/betonova-sterka-na-stenach-kolem-schodiste_721.png', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(95, NULL, 5, 'orig/betonova-sterka-na-stenach-kolem-schodiste_722.png', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(96, NULL, 5, 'orig/betonova-sterka-na-stenach-kolem-schodiste_723.png', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(97, NULL, 5, 'orig/betonova-sterka-na-stenach-kolem-schodiste_724.png', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(98, NULL, 6, 'orig/uprava-schodu-betonovou-sterkou_725.png', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(99, NULL, 6, 'orig/uprava-schodu-betonovou-sterkou_727.png', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(100, NULL, 6, 'orig/uprava-schodu-betonovou-sterkou_726.png', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(101, NULL, NULL, 'orig/reference_668.jpg', NULL, NULL, 1, 1, '2026-11-07 13:25:20'),
(102, NULL, NULL, 'orig/reference_652.jpg', NULL, NULL, 1, 2, '2026-11-07 13:25:20'),
(103, NULL, NULL, 'orig/reference_649.jpg', NULL, NULL, 1, 3, '2026-11-07 13:25:20'),
(104, NULL, NULL, 'orig/stena-v-obyvacim-pokoji-od-oikos_714.jpg', NULL, NULL, 1, 4, '2026-11-07 13:25:20'),
(105, NULL, NULL, 'orig/reference_667.jpg', NULL, NULL, 1, 5, '2026-11-07 13:25:20'),
(106, NULL, NULL, 'orig/reference_658.jpg', NULL, NULL, 1, 6, '2026-11-07 13:25:20'),
(107, NULL, NULL, 'orig/reference_671.jpg', NULL, NULL, 1, 7, '2026-11-07 13:25:20'),
(108, NULL, NULL, 'orig/reference_669.jpg', NULL, NULL, 1, 8, '2026-11-07 13:25:20'),
(109, NULL, NULL, 'orig/reference_670.jpg', NULL, NULL, 1, 9, '2026-11-07 13:25:20'),
(110, NULL, NULL, 'orig/reference_663.jpg', NULL, NULL, 1, 10, '2026-11-07 13:25:20'),
(111, NULL, NULL, 'orig/reference_650.jpg', NULL, NULL, 1, 11, '2026-11-07 13:25:20'),
(112, NULL, NULL, 'orig/reference_666.webp', NULL, NULL, 1, 12, '2026-11-07 13:25:20'),
(113, NULL, NULL, 'orig/reference_665.jpg', NULL, NULL, 1, 13, '2026-11-07 13:25:20'),
(114, NULL, NULL, 'orig/reference_654.jpg', NULL, NULL, 1, 14, '2026-11-07 13:25:20'),
(115, NULL, NULL, 'orig/reference_656.jpg', NULL, NULL, 1, 15, '2026-11-07 13:25:20'),
(116, NULL, NULL, 'orig/reference_659.jpg', NULL, NULL, 1, 16, '2026-11-07 13:25:20'),
(117, NULL, NULL, 'orig/reference_664.jpg', NULL, NULL, 1, 17, '2026-11-07 13:25:20'),
(118, NULL, NULL, 'orig/reference_662.jpg', NULL, NULL, 1, 18, '2026-11-07 13:25:20'),
(119, NULL, NULL, 'orig/reference_661.jpg', NULL, NULL, 1, 19, '2026-11-07 13:25:20'),
(120, NULL, NULL, 'orig/reference_651.jpg', NULL, NULL, 1, 20, '2026-11-07 13:25:20'),
(121, NULL, NULL, 'orig/reference_647.jpg', NULL, NULL, 1, 21, '2026-11-07 13:25:20'),
(122, NULL, NULL, 'orig/reference_648.jpg', NULL, NULL, 1, 22, '2026-11-07 13:25:20'),
(123, NULL, NULL, 'orig/reference_657.jpg', NULL, NULL, 1, 23, '2026-11-07 13:25:20'),
(124, NULL, NULL, 'orig/reference_660.jpg', NULL, NULL, 1, 24, '2026-11-07 13:25:20'),
(125, NULL, NULL, 'orig/reference_646.jpg', NULL, NULL, 1, 25, '2026-11-07 13:25:20');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `location` varchar(160) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `data` text DEFAULT NULL,
  `source` varchar(40) DEFAULT NULL,
  `lang` char(2) NOT NULL DEFAULT 'cs',
  `ip` varchar(45) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `projects`
--

CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(160) NOT NULL,
  `slug_en` varchar(160) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `title_en` varchar(200) DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `summary` varchar(400) DEFAULT NULL,
  `summary_en` varchar(400) DEFAULT NULL,
  `body` mediumtext DEFAULT NULL,
  `body_en` mediumtext DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

--
-- Daten für Tabelle `projects`
--

INSERT INTO `projects` (`id`, `slug`, `slug_en`, `title`, `title_en`, `location`, `summary`, `summary_en`, `body`, `body_en`, `cover_image`, `is_published`, `created_at`) VALUES
(2, 'oprava-betonove-terasy-v-beskydech', NULL, 'Oprava betonové terasy v Beskydech', NULL, NULL, NULL, NULL, NULL, NULL, 'orig/oprava-terasy-v-beskydech_707.png', 1, '2026-11-07 13:25:20'),
(3, 'betonova-sterka-na-krb-s-mandalami', NULL, 'Betonová stěrka na krb s mandalami', NULL, NULL, NULL, NULL, NULL, NULL, 'orig/betonova-sterka-na-krb-s-mandalami_710.png', 1, '2026-11-07 13:25:20'),
(4, 'realizace-mikrocementove-sterky-na-schodech', NULL, 'Realizace mikrocementové stěrky na schodech', NULL, NULL, NULL, NULL, NULL, NULL, 'orig/realizace-mikrocementove-sterky-a-schodech_715.png', 1, '2026-11-07 13:25:20'),
(5, 'betonova-sterka-na-stenach-kolem-schodiste', NULL, 'Betonová stěrka na stěnách kolem schodiště', NULL, NULL, NULL, NULL, NULL, NULL, 'orig/betonova-sterka-na-stenach-kolem-schodiste_720.png', 1, '2026-11-07 13:25:20'),
(6, 'uprava-schodu-betonovou-sterkou', NULL, 'Úprava schodů betonovou stěrkou', NULL, NULL, NULL, NULL, NULL, NULL, 'orig/uprava-schodu-betonovou-sterkou_725.png', 1, '2026-11-07 13:25:20');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `properties`
--

CREATE TABLE `properties` (
  `pkey` varchar(80) NOT NULL,
  `grp` varchar(40) NOT NULL,
  `type` enum('line','text','html','image','bool') NOT NULL DEFAULT 'line',
  `label` varchar(160) DEFAULT NULL,
  `value_cs` text DEFAULT NULL,
  `value_en` text DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

--
-- Daten für Tabelle `properties`
--

INSERT INTO `properties` (`pkey`, `grp`, `type`, `label`, `value_cs`, `value_en`, `sort`) VALUES
('404.button', 'misc', 'line', NULL, 'Zpět na úvod', 'Back to home', 4),
('404.eyebrow', 'misc', 'line', NULL, 'Chyba 404', 'Error 404', 1),
('404.lead', 'misc', 'line', NULL, 'Možná byla přesunuta. Zkuste začít na úvodní stránce.', 'It may have been moved. Try starting from the home page.', 3),
('404.title', 'misc', 'line', NULL, 'Tahle stránka neexistuje', 'This page does not exist', 2),
('cat.cta_button', 'category', 'line', NULL, 'Chci nezávaznou nabídku', 'Get a free quote', 2),
('cat.default_body', 'category', 'text', NULL, 'Každou plochu připravujeme individuálně podle podkladu a způsobu používání. Rádi vám přivezeme vzorky a ukážeme, jak bude výsledný povrch vypadat.', 'Every surface is prepared individually according to the substrate and how it will be used. We are happy to bring samples and show you what the finished surface will look like.', 1),
('cat.gallery_empty', 'category', 'line', NULL, 'Fotografie z realizací připravujeme.', 'Project photos coming soon.', 5),
('cat.gallery_title', 'category', 'line', NULL, 'Fotogalerie', 'Gallery', 4),
('cat.children_title', 'category', 'line', NULL, 'Kde všude stěrku využijete', 'Where you can use it', 3),
('contact.address', 'contact', 'line', NULL, 'Adresa', 'Address', 6),
('contact.email', 'contact', 'line', NULL, 'E-mail', 'Email', 5),
('contact.form_title', 'contact', 'line', NULL, 'Nezávazná konzultace', 'Free consultation', 7),
('contact.lead', 'contact', 'text', NULL, 'Zavolejte nebo napište. Domluvíme si schůzku, přivezeme vzorky a zaměříme prostor – zdarma.', 'Call or write. We will arrange a visit, bring samples and measure the space – free of charge.', 3),
('contact.map_title', 'contact', 'line', NULL, 'Mapa', 'Map', 8),
('contact.meta_desc', 'contact', 'text', NULL, 'Kontaktujte nás pro nezávaznou konzultaci a zaměření zdarma.', 'Contact us for a free consultation and measuring.', 2),
('contact.phone', 'contact', 'line', NULL, 'Telefon', 'Phone', 4),
('contact.title', 'contact', 'line', NULL, 'Kontakt', 'Contact', 1),
('cta.button', 'cta', 'line', NULL, 'Napište nám', 'Get in touch', 3),
('cta.text', 'cta', 'text', NULL, 'Přijedeme, změříme a ukážeme vzorky. Konzultace i cenová nabídka jsou zdarma a nezávazné.', 'We will come over, measure up and show you samples. The consultation and quote are free and without obligation.', 2),
('cta.title', 'cta', 'line', NULL, 'Chystáte rekonstrukci?', 'Planning a renovation?', 1),
('footer.contact', 'nav', 'line', NULL, 'Kontakt', 'Contact', 11),
('footer.ico', 'nav', 'line', NULL, 'IČ', 'Company ID', 13),
('footer.navigation', 'nav', 'line', NULL, 'Navigace', 'Navigation', 10),
('footer.privacy', 'nav', 'line', NULL, 'Ochrana osobních údajů', 'Privacy policy', 12),
('form.err_csrf', 'form', 'line', NULL, 'Platnost formuláře vypršela, odešlete ho prosím znovu.', 'The form has expired, please submit it again.', 12),
('form.err_email', 'form', 'line', NULL, 'Zadejte platný e-mail.', 'Please enter a valid email address.', 14),
('form.err_gdpr', 'form', 'line', NULL, 'Potvrďte prosím souhlas se zpracováním osobních údajů.', 'Please confirm your consent to data processing.', 16),
('form.err_required', 'form', 'line', NULL, 'Vyplňte prosím pole „{field}“.', 'Please fill in “{field}”.', 2),
('form.gdpr', 'form', 'line', NULL, 'Souhlasím se zpracováním osobních údajů za účelem vyřízení poptávky', 'I agree to the processing of my personal data in order to handle my enquiry', 8),
('form.gdpr_more', 'form', 'line', NULL, 'více informací', 'more information', 9),
('form.choose', 'form', 'line', NULL, '— vyberte —', '— choose —', 1),
('form.submit', 'form', 'line', NULL, 'Odeslat nezávaznou poptávku', 'Send enquiry', 10),
('form.thanks', 'form', 'line', NULL, 'Děkujeme, zpráva byla odeslána. Ozveme se vám co nejdříve.', 'Thank you, your message has been sent. We will get back to you shortly.', 11),
('home.about_eyebrow', 'home', 'line', NULL, 'Kdo za prací stojí', 'Who does the work', 11),
('home.about_link', 'home', 'line', NULL, 'Domluvit schůzku →', 'Arrange a visit →', 13),
('home.about_text', 'home', 'html', NULL, '<p>Betonovým stěrkám a dekorativním povrchům se věnuji přes {years} let, v Česku i v zahraničí. Každou zakázku dělám osobně – od prvního zaměření až po finální lak.</p><p>Neprodávám katalogové řešení. Společně vybereme odstín, strukturu i ochranu povrchu podle toho, jak bude prostor skutečně používán.</p>', '<p>I have been working with concrete-look finishes and decorative surfaces for over {years} years, in the Czech Republic and abroad. I handle every job personally – from the first measurement to the final coat of sealer.</p><p>There are no off-the-shelf solutions. Together we choose the shade, texture and protection based on how the space will really be used.</p>', 12),
('home.b1_text', 'home', 'text', NULL, 'Desítky dokončených koupelen, podlah i fasád. Víme, jak se materiál chová na různých podkladech.', 'Dozens of finished bathrooms, floors and facades. We know how the material behaves on different substrates.', 18),
('home.b1_title', 'home', 'line', NULL, 'Zkušenost z praxe', 'Hands-on experience', 17),
('home.b2_text', 'home', 'text', NULL, 'Po zaměření dostanete závaznou nabídku. Žádné vícepráce, o kterých byste nevěděli.', 'After measuring you get a binding quote. No surprise extras.', 20),
('home.b2_title', 'home', 'line', NULL, 'Jasná cena předem', 'A clear price up front', 19),
('home.b3_text', 'home', 'text', NULL, 'Přivezeme vzorky, posoudíme podklad a poradíme – zdarma a bez závazku.', 'We bring samples, assess the substrate and advise you – free and without obligation.', 22),
('home.b3_title', 'home', 'line', NULL, 'Vzorky přímo u vás', 'Samples at your place', 21),
('home.badge_strong', 'home', 'line', NULL, 'Zdarma', 'Free', 7),
('home.badge_text', 'home', 'line', NULL, 'zaměření a vzorky', 'measuring and samples', 8),
('home.cta_primary', 'home', 'line', NULL, 'Nezávazná poptávka', 'Request a quote', 5),
('home.cta_secondary', 'home', 'line', NULL, 'Prohlédnout realizace', 'See our work', 6),
('home.exterior_text', 'home', 'line', NULL, 'Mrazuvzdorné systémy pro fasády, ploty, terasy a schodiště.', 'Frost-resistant systems for facades, fences, terraces and staircases.', 15),
('home.exterior_title', 'home', 'line', NULL, 'Betonové stěrky pro exteriér', 'Concrete finishes for exteriors', 14),
('home.eyebrow', 'home', 'line', NULL, 'Ruční práce · {years}+ let praxe', 'Handcrafted · {years}+ years of experience', 2),
('home.interior_text', 'home', 'line', NULL, 'Vyberte místo, kde si nový povrch umíte představit.', 'Choose where you can picture your new surface.', 10),
('home.interior_title', 'home', 'line', NULL, 'Betonové stěrky do interiéru', 'Concrete finishes for interiors', 9),
('home.latest_link', 'home', 'line', NULL, 'Všechny reference →', 'All references →', 24),
('home.latest_title', 'home', 'line', NULL, 'Poslední realizace', 'Latest projects', 23),
('home.lead', 'home', 'text', NULL, 'Navrhneme a realizujeme betonové stěrky na stěny, podlahy, schody i nábytek – v bytě, v domě i venku. Bez bourání, přímo na stávající podklad.', 'We design and apply concrete-look finishes on walls, floors, stairs and furniture – in flats, houses and outdoors. No demolition, applied directly to the existing surface.', 4),
('home.meta_desc', 'home', 'text', NULL, 'Realizace betonových stěrek, dekorativních omítek a malířské práce v Brně a okolí. Zaměření a konzultace zdarma.', 'Concrete-look finishes, decorative plasters and painting in Brno and the surrounding area. Free measuring and consultation.', 1),
('home.title', 'home', 'html', NULL, 'Povrchy z betonu,<br>které nemají spáry.', 'Concrete surfaces<br>without a single joint.', 3),
('home.why_title', 'home', 'line', NULL, 'Proč spolupracovat s námi', 'Why work with us', 16),
('img.about', 'images', 'image', 'Fotka majitele', '', '', 4),
('img.benefit_icon', 'images', 'image', 'Ikona výhod', 'orig/tpl-sekce-5-vyhoda.png', '', 6),
('img.favicon', 'images', 'image', 'Favicon', 'orig/tpl-img-favicon.ico', '', 2),
('img.hero', 'images', 'image', 'Hlavní fotka úvodu', 'orig/tpl-sekce-3-slide1.jpg', '', 3),
('img.logo', 'images', 'image', 'Logo', 'orig/tpl-logo-logo.png', '', 1),
('img.why_bg', 'images', 'image', 'Pozadí sekce „Proč my“', 'orig/tpl-sekce-6-background.jpg', '', 5),
('nav.contact', 'nav', 'line', NULL, 'Kontakt', 'Contact', 5),
('nav.home', 'nav', 'line', NULL, 'Úvod', 'Home', 1),
('nav.menu', 'nav', 'line', NULL, 'Menu', 'Menu', 6),
('nav.pricing', 'nav', 'line', NULL, 'Ceník', 'Pricing', 4),
('nav.references', 'nav', 'line', NULL, 'Reference', 'References', 2),
('nav.reviews', 'nav', 'line', NULL, 'Hodnocení', 'Reviews', 3),
('pricing.factors', 'pricing', 'html', NULL, '<ul class=\"checklist\"><li><strong>Stav podkladu</strong> – zda je potřeba vyrovnání, penetrace nebo armovací síťka.</li><li><strong>Velikost a členitost plochy</strong> – rovná stěna vs. schodiště či krb.</li><li><strong>Zvolený materiál a ochrana</strong> – interiér, koupelna, podlaha nebo exteriér.</li><li><strong>Lokalita a termín</strong> realizace.</li></ul><p>Po zaměření a výběru vzorku vám připravíme <strong>závaznou cenovou nabídku</strong>. Zaměření i konzultace jsou zdarma.</p>', '<ul class=\"checklist\"><li><strong>Condition of the substrate</strong> – whether levelling, primer or reinforcing mesh is needed.</li><li><strong>Size and complexity</strong> – a flat wall vs. a staircase or fireplace.</li><li><strong>Material and protection</strong> – interior, bathroom, floor or exterior.</li><li><strong>Location and timing</strong> of the job.</li></ul><p>After measuring and choosing a sample, we prepare a <strong>binding quote</strong>. Measuring and consultation are free.</p>', 5),
('pricing.factors_title', 'pricing', 'line', NULL, 'Na čem cena závisí', 'What the price depends on', 4),
('pricing.form_title', 'pricing', 'line', NULL, 'Poptat cenu', 'Ask for a price', 6),
('pricing.lead', 'pricing', 'text', NULL, 'Kvalitní povrch za rozumnou cenu – vždy připravenou přesně pro váš prostor.', 'A quality surface at a fair price – always calculated for your specific space.', 3),
('pricing.meta_desc', 'pricing', 'text', NULL, 'Cena betonové stěrky a dekorativních omítek – nezávazná cenová nabídka zdarma.', 'Prices for concrete finishes and decorative plasters – free, no-obligation quote.', 2),
('pricing.title', 'pricing', 'line', NULL, 'Ceník', 'Pricing', 1),
('project.others_title', 'references', 'line', NULL, 'Další realizace', 'More projects', 7),
('ref.gallery_empty', 'references', 'line', NULL, 'Fotografie brzy doplníme.', 'Photos coming soon.', 6),
('ref.gallery_title', 'references', 'line', NULL, 'Fotogalerie', 'Gallery', 5),
('ref.lead', 'references', 'text', NULL, 'Výběr z dokončených zakázek – koupelny, kuchyně, podlahy, schodiště i exteriéry.', 'A selection of completed jobs – bathrooms, kitchens, floors, staircases and exteriors.', 3),
('ref.meta_desc', 'references', 'text', NULL, 'Fotografie a popisy dokončených realizací betonových stěrek a dekorativních omítek.', 'Photos and descriptions of completed concrete finish and decorative plaster projects.', 2),
('ref.projects_title', 'references', 'line', NULL, 'Vybrané realizace', 'Selected projects', 4),
('ref.title', 'references', 'line', NULL, 'Reference', 'References', 1),
('reviews.empty', 'reviews', 'text', NULL, 'Zatím zde nejsou žádná hodnocení. Budeme rádi, když se podělíte o svou zkušenost jako první.', 'There are no reviews yet. We would be glad if you shared your experience first.', 4),
('reviews.err_name', 'reviews', 'line', NULL, 'Vyplňte prosím jméno.', 'Please enter your name.', 12),
('reviews.err_text', 'reviews', 'line', NULL, 'Napište prosím alespoň pár slov.', 'Please write at least a few words.', 13),
('reviews.f_location', 'reviews', 'line', NULL, 'Obec / město', 'Town / city', 7),
('reviews.f_name', 'reviews', 'line', NULL, 'Jméno *', 'Name *', 6),
('reviews.f_rating', 'reviews', 'line', NULL, 'Hodnocení', 'Rating', 8),
('reviews.f_text', 'reviews', 'line', NULL, 'Vaše zkušenost *', 'Your experience *', 9),
('reviews.form_title', 'reviews', 'line', NULL, 'Napište nám hodnocení', 'Write a review', 5),
('reviews.meta_desc', 'reviews', 'text', NULL, 'Zkušenosti a hodnocení zákazníků.', 'Customer experiences and reviews.', 2),
('reviews.of5', 'reviews', 'line', NULL, 'z 5', 'out of 5', 14),
('reviews.submit', 'reviews', 'line', NULL, 'Odeslat hodnocení', 'Submit review', 10),
('reviews.summary', 'reviews', 'line', NULL, '{avg} z 5 · {count} hodnocení', '{avg} out of 5 · {count} reviews', 3),
('reviews.thanks', 'reviews', 'line', NULL, 'Děkujeme! Hodnocení se zobrazí po schválení.', 'Thank you! Your review will appear once approved.', 11),
('reviews.title', 'reviews', 'line', NULL, 'Hodnocení zákazníků', 'Customer reviews', 1),
('seo.indexing', 'seo', 'bool', 'Povolit indexaci vyhledávači', '1', '', 1),
('seo.jsonld', 'seo', 'bool', 'Strukturovaná data JSON-LD (firma, web, služby, realizace)', '1', '', 4),
('seo.microdata', 'seo', 'bool', 'Strukturovaná data Microdata (hodnocení, fotky v galeriích)', '1', '', 5),
('seo.rdfa', 'seo', 'bool', 'Strukturovaná data RDFa (drobečková navigace, kontakt v patičce)', '1', '', 6),
('seo.robots_extra', 'seo', 'text', 'Další řádky robots.txt', 'Disallow: /admin/', '', 2),
('seo.sitemap_auto', 'seo', 'bool', 'Automaticky přegenerovat sitemap při uložení obsahu', '1', '', 3),
('site.address', 'site', 'line', 'Adresa', 'Viniční 4349/82, 615 00 Brno', '', 6),
('site.domain', 'site', 'line', 'Doména webu (pro sitemap)', 'https://www.betonovesterky-brno.cz', '', 12),
('site.email', 'site', 'line', 'E-mail', 'info@betonovesterky-brno.cz', '', 5),
('site.email_protect', 'site', 'bool', 'Chránit e-mail před spamboty (zakódovat v HTML)', '1', '', 13),
('site.facebook', 'site', 'line', 'Facebook URL', '', '', 9),
('site.gdpr_url', 'site', 'line', 'Odkaz na GDPR', '', '', 11),
('site.ico', 'site', 'line', 'IČ', '01774557', '', 7),
('site.instagram', 'site', 'line', 'Instagram URL', '', '', 10),
('site.name', 'site', 'line', 'Název webu', 'Betonové stěrky Brno', '', 1),
('site.owner', 'site', 'line', 'Majitel', 'Tomáš Rücker', '', 3),
('site.phone', 'site', 'line', 'Telefon', '+420 776 365 411', '', 4),
('site.tagline', 'site', 'line', 'Podtitul', 'Betonové stěrky, dekorativní omítky a malířské práce', 'Concrete finishes, decorative plasters and painting', 2),
('site.years', 'site', 'line', 'Roky praxe', '20', '', 8),
('ui.close', 'misc', 'line', NULL, 'Zavřít', 'Close', 5),
('ui.next', 'misc', 'line', NULL, 'Další', 'Next', 7),
('ui.prev', 'misc', 'line', NULL, 'Předchozí', 'Previous', 6);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `author` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `text` text NOT NULL,
  `lang` char(2) NOT NULL DEFAULT 'cs',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indizes für die Tabelle `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `slug_en` (`slug_en`),
  ADD KEY `fk_cat_parent` (`parent_id`);

--
-- Indizes für die Tabelle `form_fields`
--
ALTER TABLE `form_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fkey` (`fkey`);

--
-- Indizes für die Tabelle `gallery_images`
--
ALTER TABLE `gallery_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_img_cat` (`category_id`),
  ADD KEY `fk_img_proj` (`project_id`);

--
-- Indizes für die Tabelle `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `slug_en` (`slug_en`);

--
-- Indizes für die Tabelle `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`pkey`);

--
-- Indizes für die Tabelle `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT für Tabelle `form_fields`
--
ALTER TABLE `form_fields`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `gallery_images`
--
ALTER TABLE `gallery_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT für Tabelle `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `gallery_images`
--
ALTER TABLE `gallery_images`
  ADD CONSTRAINT `fk_img_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_img_proj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
