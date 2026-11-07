-- Doplní do již nainstalované databáze nové texty a nastavení.
-- Bezpečné spustit opakovaně (INSERT IGNORE nepřepíše vaše úpravy).
SET NAMES utf8mb4;
INSERT IGNORE INTO properties (pkey, grp, type, label, value_cs, value_en, sort) VALUES
-- strukturovaná data a ochrana e-mailu
('seo.jsonld',         'seo',  'bool', 'Strukturovaná data JSON-LD (firma, web, služby, realizace)', '1', '', 4),
('seo.microdata',      'seo',  'bool', 'Strukturovaná data Microdata (hodnocení, fotky v galeriích)', '1', '', 5),
('seo.rdfa',           'seo',  'bool', 'Strukturovaná data RDFa (drobečková navigace, kontakt v patičce)', '1', '', 6),
('site.email_protect', 'site', 'bool', 'Chránit e-mail před spamboty (zakódovat v HTML)', '1', '', 13),
('site.hours',         'site', 'line', 'Kdy volat (např. Po–Pá 7–17 h; prázdné = skryto)', '', '', 14),
-- stránka 404
('404.suggest',        'misc', 'line', NULL, 'Nehledáte stránku', 'Were you looking for', 5),
('404.links_title',    'misc', 'line', NULL, 'Co nabízíme', 'What we offer', 6),
('404.contact_text',   'misc', 'line', NULL, 'Nenašli jste, co hledáte? Zavolejte nebo napište:', 'Can’t find what you are looking for? Call or write:', 7),
-- stránka Kontakt
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
('contact.step3_text', 'contact', 'text', NULL, 'Dostanete cenovou nabídku a návrh termínu. Rozhodnutí je na vás.', 'You receive a price quote and a proposed date. The decision is yours.', 23);
