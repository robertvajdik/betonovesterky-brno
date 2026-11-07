-- Betonové stěrky Brno – databázové schéma + výchozí obsah (CZ / EN)
-- Import: mysql -u root -p < database/schema.sql   (nebo přes phpMyAdmin)

CREATE DATABASE IF NOT EXISTS betonovesterky CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
USE betonovesterky;
SET NAMES utf8mb4;

DROP TABLE IF EXISTS gallery_images;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS inquiry_messages;
DROP TABLE IF EXISTS inquiries;
DROP TABLE IF EXISTS customers;
-- Tabulky customers, inquiry_messages a nové texty doplní web sám při prvním načtení (includes/migrate.php + migrate.sql).
DROP TABLE IF EXISTS properties;
DROP TABLE IF EXISTS form_fields;
DROP TABLE IF EXISTS admin_users;

-- ---------------------------------------------------------------
-- Vlastnosti webu: kontakty + všechny texty šablon v CZ a EN.
-- Prázdná EN hodnota = použije se česká.
-- ---------------------------------------------------------------
CREATE TABLE properties (
  pkey      VARCHAR(80) NOT NULL PRIMARY KEY,
  grp       VARCHAR(40) NOT NULL,
  type      ENUM('line','text','html','image','bool') NOT NULL DEFAULT 'line',
  label     VARCHAR(160) NULL,
  value_cs  TEXT NULL,
  value_en  TEXT NULL,
  sort      INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE categories (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id     INT UNSIGNED NULL,
  slug          VARCHAR(120) NOT NULL UNIQUE,
  slug_en       VARCHAR(120) NULL UNIQUE,
  area          ENUM('service','interior','exterior') NOT NULL DEFAULT 'interior',
  title         VARCHAR(160) NOT NULL,
  title_en      VARCHAR(160) NULL,
  menu_title    VARCHAR(80)  NULL,
  menu_title_en VARCHAR(80)  NULL,
  headline      VARCHAR(200) NULL,
  headline_en   VARCHAR(200) NULL,
  intro         TEXT NULL,
  intro_en      TEXT NULL,
  body          MEDIUMTEXT NULL,
  body_en       MEDIUMTEXT NULL,
  meta_desc     VARCHAR(300) NULL,
  meta_desc_en  VARCHAR(300) NULL,
  cover_image   VARCHAR(255) NULL,
  sort          INT NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE projects (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(160) NOT NULL UNIQUE,
  slug_en      VARCHAR(160) NULL UNIQUE,
  title        VARCHAR(200) NOT NULL,
  title_en     VARCHAR(200) NULL,
  location     VARCHAR(120) NULL,
  summary      VARCHAR(400) NULL,
  summary_en   VARCHAR(400) NULL,
  body         MEDIUMTEXT NULL,
  body_en      MEDIUMTEXT NULL,
  cover_image  VARCHAR(255) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE gallery_images (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id   INT UNSIGNED NULL,
  project_id    INT UNSIGNED NULL,
  file          VARCHAR(255) NOT NULL,
  caption       VARCHAR(200) NULL,
  caption_en    VARCHAR(200) NULL,
  in_references TINYINT(1) NOT NULL DEFAULT 1,
  sort          INT NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_img_cat  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_img_proj FOREIGN KEY (project_id)  REFERENCES projects(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE reviews (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author      VARCHAR(100) NOT NULL,
  location    VARCHAR(100) NULL,
  rating      TINYINT UNSIGNED NOT NULL DEFAULT 5,
  text        TEXT NOT NULL,
  lang        CHAR(2) NOT NULL DEFAULT 'cs',
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE inquiries (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(160) NOT NULL,
  phone       VARCHAR(40)  NULL,
  location    VARCHAR(160) NULL,
  message     TEXT NULL,
  data        TEXT NULL,               -- JSON se všemi vyplněnými poli formuláře
  source      VARCHAR(40)  NULL,
  lang        CHAR(2) NOT NULL DEFAULT 'cs',
  ip          VARCHAR(45)  NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- Pole poptávkového formuláře (upravitelná v administraci)
-- name / email / phone / location / message jsou systémové klíče ukládané i do samostatných sloupců
CREATE TABLE form_fields (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fkey           VARCHAR(40) NOT NULL UNIQUE,
  type           ENUM('text','email','tel','number','textarea','select','checkbox','date') NOT NULL DEFAULT 'text',
  label_cs       VARCHAR(160) NOT NULL,
  label_en       VARCHAR(160) NULL,
  placeholder_cs VARCHAR(200) NULL,
  placeholder_en VARCHAR(200) NULL,
  options_cs     TEXT NULL,             -- pro select: jedna možnost na řádek
  options_en     TEXT NULL,
  is_required    TINYINT(1) NOT NULL DEFAULT 0,
  is_wide        TINYINT(1) NOT NULL DEFAULT 0,   -- přes celou šířku formuláře
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  sort           INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO form_fields (fkey, type, label_cs, label_en, placeholder_cs, placeholder_en, options_cs, options_en, is_required, is_wide, sort) VALUES
('name',     'text',     'Jméno a příjmení', 'Full name', NULL, NULL, NULL, NULL, 1, 0, 10),
('email',    'email',    'E-mail', 'Email', NULL, NULL, NULL, NULL, 1, 0, 20),
('phone',    'tel',      'Telefon', 'Phone', NULL, NULL, NULL, NULL, 0, 0, 30),
('location', 'text',     'Místo realizace', 'Job location', 'např. Brno – Žabovřesky', 'e.g. Brno – Žabovřesky', NULL, NULL, 0, 0, 40),
('service',  'select',   'Služba', 'Service', NULL, NULL,
 'Betonová stěrka – interiér\nBetonová stěrka – exteriér\nDekorativní omítka\nMalířské práce\nJiné',
 'Concrete finish – interior\nConcrete finish – exterior\nDecorative plaster\nPainting\nOther', 0, 0, 50),
('area',     'number',   'Přibližná plocha (m²)', 'Approximate area (m²)', NULL, NULL, NULL, NULL, 0, 0, 60),
('term_from', 'date', 'Preferovaný termín od', 'Preferred date from', NULL, NULL, NULL, NULL, 0, 0, 62),
('term_to',   'date', 'Preferovaný termín do', 'Preferred date to', NULL, NULL, NULL, NULL, 0, 0, 64),
('message',  'textarea', 'Co plánujete?', 'What are you planning?', 'Jaký prostor, podklad, termín…', 'Which room, substrate, timing…', NULL, NULL, 1, 1, 70);

CREATE TABLE admin_users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- ---------------------------------------------------------------
-- Vlastnosti (proměnné v textech: {years}, {avg}, {count})
-- ---------------------------------------------------------------
INSERT INTO properties (pkey, grp, type, label, value_cs, value_en, sort) VALUES
-- Firma
('site.name',      'site', 'line', 'Název webu',        'Betonové stěrky Brno', '', 1),
('site.tagline',   'site', 'line', 'Podtitul',          'Betonové stěrky, dekorativní omítky a malířské práce', 'Concrete finishes, decorative plasters and painting', 2),
('site.owner',     'site', 'line', 'Majitel',           'Tomáš Rücker', '', 3),
('site.phone',     'site', 'line', 'Telefon',           '+420 776 365 411', '', 4),
('site.email',     'site', 'line', 'E-mail',            'info@betonovesterky-brno.cz', '', 5),
('site.address',   'site', 'line', 'Adresa',            'Viniční 4349/82, 615 00 Brno', '', 6),
('site.ico',       'site', 'line', 'IČ',                '01774557', '', 7),
('site.years',     'site', 'line', 'Roky praxe',        '20', '', 8),
('site.facebook',  'site', 'line', 'Facebook URL',      '', '', 9),
('site.instagram', 'site', 'line', 'Instagram URL',     '', '', 10),
('site.gdpr_url',  'site', 'line', 'Externí odkaz na zásady GDPR (prázdné = vlastní stránka webu)', '', '', 11),
('site.domain',    'site', 'line', 'Doména webu (pro sitemap)', 'https://www.betonovesterky-brno.cz', '', 12),
('site.email_protect', 'site', 'bool', 'Chránit e-mail před spamboty (zakódovat v HTML)', '1', '', 13),
('site.hours',     'site', 'line', 'Kdy volat (např. Po–Pá 7–17 h; prázdné = skryto)', '', '', 14),

-- Grafika (soubory v /uploads – nahrávají se v Nastavení)
('img.logo',         'images', 'image', 'Logo', '', '', 1),
('img.favicon',      'images', 'image', 'Favicon', '', '', 2),
('img.hero',         'images', 'image', 'Hlavní fotka úvodu', '', '', 3),
('img.about',        'images', 'image', 'Fotka majitele', '', '', 4),
('img.why_bg',       'images', 'image', 'Pozadí sekce „Proč my“', '', '', 5),
('img.benefit_icon', 'images', 'image', 'Ikona výhod', '', '', 6),

-- SEO / robots / sitemap
('seo.indexing',     'seo', 'bool', 'Povolit indexaci vyhledávači', '1', '', 1),
('seo.robots_extra', 'seo', 'text', 'Další řádky robots.txt', 'Disallow: /admin/', '', 2),
('seo.sitemap_auto', 'seo', 'bool', 'Automaticky přegenerovat sitemap při uložení obsahu', '1', '', 3),
('seo.jsonld',       'seo', 'bool', 'Strukturovaná data JSON-LD (firma, web, služby, realizace)', '1', '', 4),
('seo.microdata',    'seo', 'bool', 'Strukturovaná data Microdata (hodnocení, fotky v galeriích)', '1', '', 5),
('seo.rdfa',         'seo', 'bool', 'Strukturovaná data RDFa (drobečková navigace, kontakt v patičce)', '1', '', 6),

-- Navigace a patička
('nav.home',       'nav', 'line', NULL, 'Úvod', 'Home', 1),
('nav.references', 'nav', 'line', NULL, 'Reference', 'References', 2),
('nav.reviews',    'nav', 'line', NULL, 'Hodnocení', 'Reviews', 3),
('nav.pricing',    'nav', 'line', NULL, 'Ceník', 'Pricing', 4),
('nav.contact',    'nav', 'line', NULL, 'Kontakt', 'Contact', 5),
('nav.menu',       'nav', 'line', NULL, 'Menu', 'Menu', 6),
('footer.navigation', 'nav', 'line', NULL, 'Navigace', 'Navigation', 10),
('footer.contact',    'nav', 'line', NULL, 'Kontakt', 'Contact', 11),
('footer.privacy',    'nav', 'line', NULL, 'Ochrana osobních údajů', 'Privacy policy', 12),
('footer.ico',        'nav', 'line', NULL, 'IČ', 'Company ID', 13),

-- CTA blok
('cta.title',  'cta', 'line', NULL, 'Chystáte rekonstrukci?', 'Planning a renovation?', 1),
('cta.text',   'cta', 'text', NULL, 'Přijedeme, změříme a ukážeme vzorky. Konzultace i cenová nabídka jsou zdarma a nezávazné.', 'We will come over, measure up and show you samples. The consultation and quote are free and without obligation.', 2),
('cta.button', 'cta', 'line', NULL, 'Napište nám', 'Get in touch', 3),

-- Úvodní stránka
('home.meta_desc',     'home', 'text', NULL, 'Realizace betonových stěrek, dekorativních omítek a malířské práce v Brně a okolí. Zaměření a konzultace zdarma.', 'Concrete-look finishes, decorative plasters and painting in Brno and the surrounding area. Free measuring and consultation.', 1),
('home.eyebrow',       'home', 'line', NULL, 'Ruční práce · {years}+ let praxe', 'Handcrafted · {years}+ years of experience', 2),
('home.title',         'home', 'html', NULL, 'Povrchy z betonu,<br>které nemají spáry.', 'Concrete surfaces<br>without a single joint.', 3),
('home.lead',          'home', 'text', NULL, 'Navrhneme a realizujeme betonové stěrky na stěny, podlahy, schody i nábytek – v bytě, v domě i venku. Bez bourání, přímo na stávající podklad.', 'We design and apply concrete-look finishes on walls, floors, stairs and furniture – in flats, houses and outdoors. No demolition, applied directly to the existing surface.', 4),
('home.cta_primary',   'home', 'line', NULL, 'Nezávazná poptávka', 'Request a quote', 5),
('home.cta_secondary', 'home', 'line', NULL, 'Prohlédnout realizace', 'See our work', 6),
('home.badge_strong',  'home', 'line', NULL, 'Zdarma', 'Free', 7),
('home.badge_text',    'home', 'line', NULL, 'zaměření a vzorky', 'measuring and samples', 8),
('home.interior_title','home', 'line', NULL, 'Betonové stěrky do interiéru', 'Concrete finishes for interiors', 9),
('home.interior_text', 'home', 'line', NULL, 'Vyberte místo, kde si nový povrch umíte představit.', 'Choose where you can picture your new surface.', 10),
('home.about_eyebrow', 'home', 'line', NULL, 'Kdo za prací stojí', 'Who does the work', 11),
('home.about_text',    'home', 'html', NULL, '<p>Betonovým stěrkám a dekorativním povrchům se věnuji přes {years} let, v Česku i v zahraničí. Každou zakázku dělám osobně – od prvního zaměření až po finální lak.</p><p>Neprodávám katalogové řešení. Společně vybereme odstín, strukturu i ochranu povrchu podle toho, jak bude prostor skutečně používán.</p>', '<p>I have been working with concrete-look finishes and decorative surfaces for over {years} years, in the Czech Republic and abroad. I handle every job personally – from the first measurement to the final coat of sealer.</p><p>There are no off-the-shelf solutions. Together we choose the shade, texture and protection based on how the space will really be used.</p>', 12),
('home.about_link',    'home', 'line', NULL, 'Domluvit schůzku →', 'Arrange a visit →', 13),
('home.exterior_title','home', 'line', NULL, 'Betonové stěrky pro exteriér', 'Concrete finishes for exteriors', 14),
('home.exterior_text', 'home', 'line', NULL, 'Mrazuvzdorné systémy pro fasády, ploty, terasy a schodiště.', 'Frost-resistant systems for facades, fences, terraces and staircases.', 15),
('home.why_title',     'home', 'line', NULL, 'Proč spolupracovat s námi', 'Why work with us', 16),
('home.b1_title',      'home', 'line', NULL, 'Zkušenost z praxe', 'Hands-on experience', 17),
('home.b1_text',       'home', 'text', NULL, 'Desítky dokončených koupelen, podlah i fasád. Víme, jak se materiál chová na různých podkladech.', 'Dozens of finished bathrooms, floors and facades. We know how the material behaves on different substrates.', 18),
('home.b2_title',      'home', 'line', NULL, 'Jasná cena předem', 'A clear price up front', 19),
('home.b2_text',       'home', 'text', NULL, 'Po zaměření dostanete závaznou nabídku. Žádné vícepráce, o kterých byste nevěděli.', 'After measuring you get a binding quote. No surprise extras.', 20),
('home.b3_title',      'home', 'line', NULL, 'Vzorky přímo u vás', 'Samples at your place', 21),
('home.b3_text',       'home', 'text', NULL, 'Přivezeme vzorky, posoudíme podklad a poradíme – zdarma a bez závazku.', 'We bring samples, assess the substrate and advise you – free and without obligation.', 22),
('home.latest_title',  'home', 'line', NULL, 'Poslední realizace', 'Latest projects', 23),
('home.latest_link',   'home', 'line', NULL, 'Všechny reference →', 'All references →', 24),

-- Stránka služby / kategorie
('cat.default_body',   'category', 'text', NULL, 'Každou plochu připravujeme individuálně podle podkladu a způsobu používání. Rádi vám přivezeme vzorky a ukážeme, jak bude výsledný povrch vypadat.', 'Every surface is prepared individually according to the substrate and how it will be used. We are happy to bring samples and show you what the finished surface will look like.', 1),
('cat.cta_button',     'category', 'line', NULL, 'Chci nezávaznou nabídku', 'Get a free quote', 2),
('cat.children_title', 'category', 'line', NULL, 'Kde všude stěrku využijete', 'Where you can use it', 3),
('cat.gallery_title',  'category', 'line', NULL, 'Fotogalerie', 'Gallery', 4),
('cat.gallery_empty',  'category', 'line', NULL, 'Fotografie z realizací připravujeme.', 'Project photos coming soon.', 5),

-- Reference
('ref.title',          'references', 'line', NULL, 'Reference', 'References', 1),
('ref.meta_desc',      'references', 'text', NULL, 'Fotografie a popisy dokončených realizací betonových stěrek a dekorativních omítek.', 'Photos and descriptions of completed concrete finish and decorative plaster projects.', 2),
('ref.lead',           'references', 'text', NULL, 'Výběr z dokončených zakázek – koupelny, kuchyně, podlahy, schodiště i exteriéry.', 'A selection of completed jobs – bathrooms, kitchens, floors, staircases and exteriors.', 3),
('ref.projects_title', 'references', 'line', NULL, 'Vybrané realizace', 'Selected projects', 4),
('ref.gallery_title',  'references', 'line', NULL, 'Fotogalerie', 'Gallery', 5),
('ref.gallery_empty',  'references', 'line', NULL, 'Fotografie brzy doplníme.', 'Photos coming soon.', 6),
('project.others_title','references', 'line', NULL, 'Další realizace', 'More projects', 7),

-- Hodnocení
('reviews.title',      'reviews', 'line', NULL, 'Hodnocení zákazníků', 'Customer reviews', 1),
('reviews.meta_desc',  'reviews', 'text', NULL, 'Zkušenosti a hodnocení zákazníků.', 'Customer experiences and reviews.', 2),
('reviews.summary',    'reviews', 'line', NULL, '{avg} z 5 · {count} hodnocení', '{avg} out of 5 · {count} reviews', 3),
('reviews.empty',      'reviews', 'text', NULL, 'Zatím zde nejsou žádná hodnocení. Budeme rádi, když se podělíte o svou zkušenost jako první.', 'There are no reviews yet. We would be glad if you shared your experience first.', 4),
('reviews.form_title', 'reviews', 'line', NULL, 'Napište nám hodnocení', 'Write a review', 5),
('reviews.f_name',     'reviews', 'line', NULL, 'Jméno *', 'Name *', 6),
('reviews.f_location', 'reviews', 'line', NULL, 'Obec / město', 'Town / city', 7),
('reviews.f_rating',   'reviews', 'line', NULL, 'Hodnocení', 'Rating', 8),
('reviews.f_text',     'reviews', 'line', NULL, 'Vaše zkušenost *', 'Your experience *', 9),
('reviews.submit',     'reviews', 'line', NULL, 'Odeslat hodnocení', 'Submit review', 10),
('reviews.thanks',     'reviews', 'line', NULL, 'Děkujeme! Hodnocení se zobrazí po schválení.', 'Thank you! Your review will appear once approved.', 11),
('reviews.err_name',   'reviews', 'line', NULL, 'Vyplňte prosím jméno.', 'Please enter your name.', 12),
('reviews.err_text',   'reviews', 'line', NULL, 'Napište prosím alespoň pár slov.', 'Please write at least a few words.', 13),
('reviews.of5',        'reviews', 'line', NULL, 'z 5', 'out of 5', 14),

-- Ceník
('pricing.title',         'pricing', 'line', NULL, 'Ceník', 'Pricing', 1),
('pricing.meta_desc',     'pricing', 'text', NULL, 'Cena betonové stěrky a dekorativních omítek – nezávazná cenová nabídka zdarma.', 'Prices for concrete finishes and decorative plasters – free, no-obligation quote.', 2),
('pricing.lead',          'pricing', 'text', NULL, 'Kvalitní povrch za rozumnou cenu – vždy připravenou přesně pro váš prostor.', 'A quality surface at a fair price – always calculated for your specific space.', 3),
('pricing.factors_title', 'pricing', 'line', NULL, 'Na čem cena závisí', 'What the price depends on', 4),
('pricing.factors',       'pricing', 'html', NULL, '<ul class="checklist"><li><strong>Stav podkladu</strong> – zda je potřeba vyrovnání, penetrace nebo armovací síťka.</li><li><strong>Velikost a členitost plochy</strong> – rovná stěna vs. schodiště či krb.</li><li><strong>Zvolený materiál a ochrana</strong> – interiér, koupelna, podlaha nebo exteriér.</li><li><strong>Lokalita a termín</strong> realizace.</li></ul><p>Po zaměření a výběru vzorku vám připravíme <strong>závaznou cenovou nabídku</strong>. Zaměření i konzultace jsou zdarma.</p>', '<ul class="checklist"><li><strong>Condition of the substrate</strong> – whether levelling, primer or reinforcing mesh is needed.</li><li><strong>Size and complexity</strong> – a flat wall vs. a staircase or fireplace.</li><li><strong>Material and protection</strong> – interior, bathroom, floor or exterior.</li><li><strong>Location and timing</strong> of the job.</li></ul><p>After measuring and choosing a sample, we prepare a <strong>binding quote</strong>. Measuring and consultation are free.</p>', 5),
('pricing.form_title',    'pricing', 'line', NULL, 'Poptat cenu', 'Ask for a price', 6),

-- Ochrana osobních údajů (GDPR)
('privacy.title',      'privacy', 'line', NULL, 'Ochrana osobních údajů', 'Privacy policy', 1),
('privacy.meta_desc',  'privacy', 'text', NULL, 'Jak zpracováváme osobní údaje z poptávek a hodnocení, jak dlouho je uchováváme a jaká máte práva.', 'How we process personal data from enquiries and reviews, how long we keep it and what your rights are.', 2),
('privacy.lead',       'privacy', 'text', NULL, 'Jak zacházíme s údaji, které nám svěříte přes formulář, e-mail nebo telefon.', 'How we handle the data you share with us through the form, by e-mail or by phone.', 3),
('privacy.body',       'privacy', 'html', NULL, '<h2>Správce osobních údajů</h2><p>{owner}, IČ {ico}, {address} (dále „správce“). Kontakt: {email}, tel. {phone}.</p><h2>Jaké údaje zpracováváme a proč</h2><ul><li><strong>Poptávkový formulář, e-mail a telefon</strong> – jméno, e-mail, telefon, místo realizace, obsah zprávy a fotografie, které přiložíte (např. snímky prostoru). Fotografie mohou obsahovat metadata, třeba místo pořízení – před nahráním je můžete odstranit. Údaje použijeme k vyřízení poptávky, přípravě cenové nabídky a domluvě zaměření. Právní základ: jednání o uzavření smlouvy na vaši žádost (čl. 6 odst. 1 písm. b) GDPR).</li><li><strong>Zákaznický účet</strong> – pokud si účet založíte: jméno, e-mail, telefon, lokalita, zabezpečené (hashované) heslo a historie poptávek a zpráv. Účet slouží k přehledu vašich poptávek a komunikaci s námi. Právní základ: smlouva o vedení účtu (čl. 6 odst. 1 písm. b) GDPR). Účet na vaši žádost zrušíme.</li><li><strong>Hodnocení</strong> – jméno, případně lokalita a text hodnocení, které po schválení zveřejníme na webu. Právní základ: váš souhlas (čl. 6 odst. 1 písm. a) GDPR), který můžete kdykoli odvolat.</li><li><strong>Zakázky a fakturace</strong> – pokud uzavřeme smlouvu, zpracováváme údaje nutné k jejímu plnění a ke splnění zákonných povinností, např. v účetnictví (čl. 6 odst. 1 písm. b) a c) GDPR).</li><li><strong>IP adresa</strong> odesílatele formuláře – k ochraně webu před zneužitím a spamem. Právní základ: oprávněný zájem (čl. 6 odst. 1 písm. f) GDPR).</li></ul><h2>Jak dlouho údaje uchováváme</h2><p>Poptávky včetně přiložených fotografií, ze kterých nevznikne zakázka, mažeme nejpozději do 3 let. Údaje související se zakázkou uchováváme po dobu, kterou ukládá zákon (účetní doklady 10 let). Přiložené fotografie bez vašeho souhlasu nezveřejňujeme. Zveřejněné hodnocení odstraníme, kdykoli o to požádáte.</p><h2>Komu údaje předáváme</h2><p>Údaje neprodáváme a nepoužíváme k reklamě. Přístup k nim má jen poskytovatel hostingu webu a e-mailu, případně účetní nebo státní orgány, pokud to vyžaduje zákon.</p><h2>Cookies a služby třetích stran</h2><p>Web používá jen technicky nezbytnou cookie relace, která zajišťuje bezpečné odeslání formuláře a po zavření prohlížeče zanikne. Analytické ani reklamní cookies nepoužíváme, proto se souhlas s cookies nevyžaduje.</p><p>Mapa na stránce Kontakt se z Google Maps načte až po vašem kliknutí – teprve potom může Google ukládat vlastní cookies podle svých <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">zásad ochrany soukromí</a>. Písma webu poskytuje služba Google Fonts, při jejich načtení se společnosti Google předá vaše IP adresa.</p><h2>Vaše práva</h2><p>Máte právo na přístup ke svým údajům, jejich opravu nebo výmaz, omezení zpracování a přenositelnost údajů. Proti zpracování na základě oprávněného zájmu můžete vznést námitku a udělený souhlas můžete kdykoli odvolat. Stačí napsat na {email}.</p><p>Pokud se domníváte, že údaje zpracováváme v rozporu s právem, můžete podat stížnost u Úřadu pro ochranu osobních údajů, Pplk. Sochora 27, 170 00 Praha 7, <a href="https://uoou.gov.cz" target="_blank" rel="noopener">uoou.gov.cz</a>.</p><p class="muted">Tyto zásady platí od 7. 11. 2026.</p>', '<h2>Data controller</h2><p>{owner}, Company ID {ico}, {address} (the “controller”). Contact: {email}, phone {phone}.</p><h2>What data we process and why</h2><ul><li><strong>Enquiry form, e-mail and phone</strong> – name, e-mail, phone, project location, the content of your message and any photos you attach (e.g. pictures of the space). Photos may contain metadata such as the location where they were taken – you can remove it before uploading. We use the data to handle your enquiry, prepare a quote and arrange a visit. Legal basis: steps taken at your request before entering into a contract (Art. 6(1)(b) GDPR).</li><li><strong>Customer account</strong> – if you create one: name, e-mail, phone, location, a securely hashed password and the history of your enquiries and messages. The account lets you track your enquiries and talk to us. Legal basis: the agreement to provide the account (Art. 6(1)(b) GDPR). We close the account whenever you ask.</li><li><strong>Reviews</strong> – your name, optionally your location, and the review text, which we publish on the website once approved. Legal basis: your consent (Art. 6(1)(a) GDPR), which you can withdraw at any time.</li><li><strong>Jobs and invoicing</strong> – if we enter into a contract, we process the data needed to perform it and to meet legal obligations such as accounting (Art. 6(1)(b) and (c) GDPR).</li><li><strong>IP address</strong> of the form sender – to protect the website against abuse and spam. Legal basis: legitimate interest (Art. 6(1)(f) GDPR).</li></ul><h2>How long we keep data</h2><p>Enquiries that do not lead to a job, including attached photos, are deleted within 3 years at the latest. Data related to a job is kept for the period required by law (accounting records 10 years). We never publish your attached photos without your consent. A published review is removed whenever you ask.</p><h2>Who we share data with</h2><p>We do not sell your data or use it for advertising. Only our web and e-mail hosting provider has access to it, and accountants or public authorities where required by law.</p><h2>Cookies and third-party services</h2><p>The website only uses a strictly necessary session cookie, which secures form submission and expires when you close the browser. We use no analytics or advertising cookies, so no cookie consent is required.</p><p>The map on the Contact page loads from Google Maps only after you click it – only then may Google set its own cookies under its <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">privacy policy</a>. The website fonts are served by Google Fonts, which passes your IP address to Google when they load.</p><h2>Your rights</h2><p>You have the right to access your data, to have it corrected or erased, to restrict processing and to data portability. You may object to processing based on legitimate interest and withdraw your consent at any time. Just write to {email}.</p><p>If you believe we process your data unlawfully, you can lodge a complaint with the Czech Office for Personal Data Protection (ÚOOÚ), Pplk. Sochora 27, 170 00 Prague 7, <a href="https://uoou.gov.cz" target="_blank" rel="noopener">uoou.gov.cz</a>.</p><p class="muted">This policy is effective from 7 November 2026.</p>', 4),

-- Kontakt
('contact.title',      'contact', 'line', NULL, 'Kontakt', 'Contact', 1),
('contact.meta_desc',  'contact', 'text', NULL, 'Kontaktujte nás pro nezávaznou konzultaci a zaměření zdarma.', 'Contact us for a free consultation and measuring.', 2),
('contact.lead',       'contact', 'text', NULL, 'Zavolejte nebo napište. Domluvíme si schůzku, přivezeme vzorky a zaměříme prostor – zdarma.', 'Call or write. We will arrange a visit, bring samples and measure the space – free of charge.', 3),
('contact.phone',      'contact', 'line', NULL, 'Telefon', 'Phone', 4),
('contact.email',      'contact', 'line', NULL, 'E-mail', 'Email', 5),
('contact.address',    'contact', 'line', NULL, 'Adresa', 'Address', 6),
('contact.form_title', 'contact', 'line', NULL, 'Nezávazná konzultace', 'Free consultation', 7),
('contact.map_title',  'contact', 'line', NULL, 'Mapa', 'Map', 8),
('contact.call',       'contact', 'line', NULL, 'Zavolat', 'Call', 9),
('contact.write',      'contact', 'line', NULL, 'Napsat', 'Write', 10),
('contact.navigate',   'contact', 'line', NULL, 'Navigovat', 'Get directions', 11),
('contact.hours',      'contact', 'line', NULL, 'Kdy volat', 'When to call', 12),
('contact.form_lead',  'contact', 'text', NULL, 'Popište, co plánujete. Připravíme orientační cenu a domluvíme termín návštěvy.', 'Tell us what you are planning. We will prepare an estimate and arrange a visit.', 13),
('contact.map_load',   'contact', 'line', NULL, 'Zobrazit mapu', 'Show map', 14),
('contact.map_note',   'contact', 'line', NULL, 'Mapa se načte z Google Maps až po kliknutí.', 'The map loads from Google Maps only after you click.', 15),
('contact.map_open',   'contact', 'line', NULL, 'Otevřít v Google Maps', 'Open in Google Maps', 16),
('contact.steps_title','contact', 'line', NULL, 'Jak to probíhá', 'How it works', 17),
('contact.step1_title','contact', 'line', NULL, 'Ozvete se', 'Get in touch', 18),
('contact.step1_text', 'contact', 'text', NULL, 'Zavolejte nebo vyplňte formulář. Stačí pár slov o prostoru a představě.', 'Call or fill in the form. A few words about the space and your idea are enough.', 19),
('contact.step2_title','contact', 'line', NULL, 'Přijedeme se vzorky', 'We visit with samples', 20),
('contact.step2_text', 'contact', 'text', NULL, 'Prohlédneme podklad, zaměříme plochu a ukážeme vzorky barev a struktur – zdarma.', 'We check the substrate, measure the area and show colour and texture samples – free of charge.', 21),
('contact.step3_title','contact', 'line', NULL, 'Pošleme nabídku', 'You get a quote', 22),
('contact.step3_text', 'contact', 'text', NULL, 'Dostanete cenovou nabídku a návrh termínu. Rozhodnutí je na vás.', 'You receive a price quote and a proposed date. The decision is yours.', 23),

-- Poptávkový formulář
('form.choose',      'form', 'line', NULL, '— vyberte —', '— choose —', 1),
('form.err_required','form', 'line', NULL, 'Vyplňte prosím pole „{field}“.', 'Please fill in “{field}”.', 2),
('form.gdpr',       'form', 'line', NULL, 'Souhlasím se zpracováním osobních údajů za účelem vyřízení poptávky', 'I agree to the processing of my personal data in order to handle my enquiry', 8),
('form.gdpr_more',   'form', 'line', NULL, 'více informací', 'more information', 9),
('form.submit',      'form', 'line', NULL, 'Odeslat nezávaznou poptávku', 'Send enquiry', 10),
('form.thanks',      'form', 'line', NULL, 'Děkujeme, zpráva byla odeslána. Ozveme se vám co nejdříve.', 'Thank you, your message has been sent. We will get back to you shortly.', 11),
('form.err_csrf',    'form', 'line', NULL, 'Platnost formuláře vypršela, odešlete ho prosím znovu.', 'The form has expired, please submit it again.', 12),
('form.err_email',   'form', 'line', NULL, 'Zadejte platný e-mail.', 'Please enter a valid email address.', 14),
('form.err_gdpr',    'form', 'line', NULL, 'Potvrďte prosím souhlas se zpracováním osobních údajů.', 'Please confirm your consent to data processing.', 16),
('form.err_dates',   'form', 'line', NULL, 'Termín „do“ nemůže být dřív než termín „od“.', 'The “to” date cannot be earlier than the “from” date.', 17),

-- Ostatní
('404.eyebrow', 'misc', 'line', NULL, 'Chyba 404', 'Error 404', 1),
('404.title',   'misc', 'line', NULL, 'Tahle stránka neexistuje', 'This page does not exist', 2),
('404.lead',    'misc', 'line', NULL, 'Možná byla přesunuta. Zkuste začít na úvodní stránce.', 'It may have been moved. Try starting from the home page.', 3),
('404.button',  'misc', 'line', NULL, 'Zpět na úvod', 'Back to home', 4),
('404.suggest', 'misc', 'line', NULL, 'Nehledáte stránku', 'Were you looking for', 5),
('404.links_title',  'misc', 'line', NULL, 'Co nabízíme', 'What we offer', 6),
('404.contact_text', 'misc', 'line', NULL, 'Nenašli jste, co hledáte? Zavolejte nebo napište:', 'Can’t find what you are looking for? Call or write:', 7),
('ui.close',    'misc', 'line', NULL, 'Zavřít', 'Close', 5),
('ui.prev',     'misc', 'line', NULL, 'Předchozí', 'Previous', 6),
('ui.next',     'misc', 'line', NULL, 'Další', 'Next', 7);

-- ---------------------------------------------------------------
-- Stránky služeb
-- ---------------------------------------------------------------
INSERT INTO categories (id, parent_id, slug, slug_en, area, title, title_en, menu_title, menu_title_en, headline, headline_en, intro, intro_en, body, body_en, sort) VALUES
(1, NULL, 'betonove-sterky', 'concrete-finishes', 'service',
 'Betonové stěrky', 'Concrete finishes', 'Betonové stěrky', 'Concrete finishes',
 'Betonové stěrky pro interiér i exteriér', 'Concrete finishes for interiors and exteriors',
 'Bezespárý povrch s vzhledem pohledového betonu, který zvládne stěny, podlahy, schody i nábytek – bez bourání a s minimální tloušťkou vrstvy.',
 'A seamless surface with the look of exposed concrete for walls, floors, stairs and furniture – no demolition and only a few millimetres thick.',
 '<p>Stěrka se nanáší ručně v několika tenkých vrstvách přímo na stávající podklad – omítku, sádrokarton, dlažbu, dřevo i beton. Každá plocha je tak originál s jemnou kresbou, kterou u průmyslově vyráběných materiálů nenajdete.</p><p>Na závěr povrch ošetříme impregnací a lakem podle zátěže: v koupelně proti vodě, na podlaze proti otěru, v kuchyni proti mastnotě.</p>',
 '<p>The finish is applied by hand in several thin layers directly onto the existing substrate – plaster, plasterboard, tiles, wood or concrete. Every surface is one of a kind, with a subtle pattern you will not find in mass-produced materials.</p><p>Finally, we seal and lacquer the surface according to its use: against water in bathrooms, against wear on floors and against grease in kitchens.</p>', 10),
(2, NULL, 'dekorativni-omitky', 'decorative-plasters', 'service',
 'Dekorativní omítky', 'Decorative plasters', 'Dekorativní omítky', 'Decorative plasters',
 'Dekorativní omítky s charakterem', 'Decorative plasters with character',
 'Metalické efekty, struktura kamene, rez nebo jemný štuk – vzorky vám rádi ukážeme naživo, protože fotka strukturu nikdy úplně nevystihne.',
 'Metallic effects, stone textures, rust or fine stucco – we will gladly show you samples in person, because a photo never fully captures the texture.',
 '<p>Dekorativní omítky jsou ideální na akcentní stěnu v obývacím pokoji, do recepce nebo restaurace. Pomůžeme vám vybrat odstín i strukturu tak, aby ladila s nábytkem a světlem v místnosti.</p>',
 '<p>Decorative plasters are ideal for a feature wall in a living room, reception area or restaurant. We will help you choose a shade and texture that match your furniture and the light in the room.</p>', 20),
(3, NULL, 'malirske-prace', 'painting', 'service',
 'Malířské práce', 'Painting', 'Malířské práce', 'Painting',
 'Malířské práce bez starostí', 'Hassle-free painting',
 'Vymalujeme byt, dům i kanceláře. Poradíme, jakou barvu zvolit do které místnosti, a po práci po sobě uklidíme.',
 'We paint flats, houses and offices. We advise which paint suits which room, and we clean up after ourselves.',
 '<p><strong>Klasické (nedisperzní) barvy</strong> jsou cenově dostupné a prodyšné, hodí se do ložnic a obytných místností s nízkou zátěží.</p><p><strong>Disperzní a latexové barvy</strong> jsou omyvatelné a odolnější – volte je do kuchyní, chodeb, dětských pokojů nebo koupelen.</p>',
 '<p><strong>Traditional (non-dispersion) paints</strong> are affordable and breathable, suitable for bedrooms and low-traffic living spaces.</p><p><strong>Dispersion and latex paints</strong> are washable and more durable – choose them for kitchens, hallways, children''s rooms and bathrooms.</p>', 30),

(10, 1, 'do-kuchyne',          'kitchen',     'interior', 'Betonová stěrka do kuchyně', 'Concrete finish for kitchens', 'Do kuchyně', 'Kitchen', 'Stěrka do kuchyně', 'Kitchen finishes',
 'Zástěna za linkou, pracovní deska i stěny – povrch odolný vůči mastnotě a snadno omyvatelný.', 'Splashbacks, worktops and walls – a grease-resistant surface that is easy to clean.', NULL, NULL, 10),
(11, 1, 'do-koupelny',         'bathroom',    'interior', 'Betonová stěrka do koupelny', 'Concrete finish for bathrooms', 'Do koupelny', 'Bathroom', 'Stěrka do koupelny', 'Bathroom finishes',
 'Koupelna bez spár a obkladů. Speciální vodoodpudivý systém i do sprchového koutu.', 'A bathroom without joints or tiles. A special water-repellent system, even for showers.', NULL, NULL, 20),
(12, 1, 'do-obyvaciho-pokoje', 'living-room', 'interior', 'Betonová stěrka do obývacího pokoje', 'Concrete finish for living rooms', 'Do obývacího pokoje', 'Living room', 'Stěrka do obývacího pokoje', 'Living room finishes',
 'Akcentní stěna nebo celý prostor v industriálním či minimalistickém stylu.', 'A feature wall or the whole space in an industrial or minimalist style.', NULL, NULL, 30),
(13, 1, 'na-podlahu',          'floors',      'interior', 'Betonová stěrka na podlahu', 'Concrete finish for floors', 'Na podlahu', 'Floors', 'Stěrka na podlahu', 'Floor finishes',
 'Litý vzhled bez spár – i přes stávající dlažbu, s vrchním lakem pro vysokou zátěž.', 'A poured, seamless look – even over existing tiles, with a heavy-duty top coat.', NULL, NULL, 40),
(14, 1, 'na-schody',           'stairs',      'interior', 'Betonová stěrka na schody', 'Concrete finish for stairs', 'Na schody', 'Stairs', 'Stěrka na schody', 'Stair finishes',
 'Renovace schodiště bez bourání. Protiskluzová úprava a pevné hrany.', 'Staircase renovation without demolition. Anti-slip finish and solid edges.', NULL, NULL, 50),
(15, 1, 'na-nabytek',          'furniture',   'interior', 'Betonová stěrka na nábytek', 'Concrete finish for furniture', 'Na nábytek', 'Furniture', 'Stěrka na nábytek', 'Furniture finishes',
 'Stoly, komody, pulty i dvířka – starý kus nábytku získá nový, moderní povrch.', 'Tables, chests, counters and doors – give old furniture a new, modern surface.', NULL, NULL, 60),
(16, 1, 'na-krb',              'fireplace',   'interior', 'Betonová stěrka na krb', 'Concrete finish for fireplaces', 'Na krb', 'Fireplace', 'Stěrka na krb', 'Fireplace finishes',
 'Obklad krbu a krbové stěny materiálem, který snese teplo a vypadá jako masivní beton.', 'Cladding for fireplaces and chimney walls that withstands heat and looks like solid concrete.', NULL, NULL, 70),
(20, 1, 'fasada',              'facade',      'exterior', 'Betonová stěrka na fasádu', 'Concrete finish for facades', 'Fasáda', 'Facade', 'Stěrka na fasádu', 'Facade finishes',
 'Mrazuvzdorný systém pro vnější stěny, sokly a vstupy do domu.', 'A frost-resistant system for external walls, plinths and entrances.', NULL, NULL, 80),
(21, 1, 'plot',                'fence',       'exterior', 'Betonová stěrka na plot', 'Concrete finish for fences', 'Plot', 'Fence', 'Stěrka na plot', 'Fence finishes',
 'Zděné ploty a podezdívky s vzhledem pohledového betonu, odolné vůči povětrnosti.', 'Masonry fences and footings with an exposed-concrete look, resistant to weather.', NULL, NULL, 90),
(22, 1, 'venkovni-betonova-sterka', 'outdoor', 'exterior', 'Venkovní betonová stěrka', 'Outdoor concrete finishes', 'Terasy a exteriér', 'Terraces & outdoor', 'Venkovní stěrka', 'Outdoor finishes',
 'Terasy, balkony a venkovní schodiště – opravy i nové povrchy odolné vůči vodě a mrazu.', 'Terraces, balconies and outdoor stairs – repairs and new surfaces resistant to water and frost.', NULL, NULL, 100);

INSERT INTO projects (slug, slug_en, title, title_en, location, summary, summary_en, body, body_en) VALUES
('ukazkova-realizace-koupelna', 'sample-project-bathroom', 'Ukázková realizace: koupelna bez spár', 'Sample project: a seamless bathroom', 'Brno',
 'Ukázkový záznam – přepište ho vlastní realizací v administraci.', 'Sample entry – replace it with your own project in the admin.',
 '<p>Sem popište zadání, použitý materiál, postup a výsledek. Fotky přidejte v administraci v sekci Galerie.</p>',
 '<p>Describe the brief, materials, process and result here. Add photos in the admin Gallery section.</p>');
