<?php
declare(strict_types=1);

/*
 * Automatická aktualizace databáze.
 * Po nahrání nových souborů stačí otevřít web – chybějící tabulky, sloupce a texty se doplní samy.
 * Verze je uložena ve vlastnosti sys.db_version.
 */
const DB_VERSION = 18;

function db_migrate(): void
{
    if (!props()) return;              // DB nedostupná nebo bez schema.sql – nic k aktualizaci
    $current = (int) (props()['sys.db_version']['value_cs'] ?? 0);
    if ($current >= DB_VERSION) return;

    $try = function (string $sql): void {
        try { db()->exec($sql); } catch (PDOException $ex) {
            // 1060 duplicitní sloupec, 1061 duplicitní index, 1826/121 duplicitní cizí klíč – souběžná migrace
            if (!in_array((int) ($ex->errorInfo[1] ?? 0), [1060, 1061, 1826, 121], true)) throw $ex;
        }
    };
    $hasColumn = fn(string $t, string $c): bool => (bool) q(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$t, $c])->fetchColumn();

    // v2: zákaznické účty, stav poptávky, komunikace k poptávce
    $try("CREATE TABLE IF NOT EXISTS customers (
        id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email             VARCHAR(160) NOT NULL UNIQUE,
        password_hash     VARCHAR(255) NOT NULL,
        name              VARCHAR(120) NOT NULL,
        phone             VARCHAR(40)  NULL,
        location          VARCHAR(160) NULL,
        lang              CHAR(2) NOT NULL DEFAULT 'cs',
        email_verified_at DATETIME NULL,
        verify_hash       CHAR(64) NULL,
        reset_hash        CHAR(64) NULL,
        reset_expires     DATETIME NULL,
        is_active         TINYINT(1) NOT NULL DEFAULT 1,
        last_login        DATETIME NULL,
        created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");

    if (!$hasColumn('inquiries', 'customer_id')) {
        $try("ALTER TABLE inquiries ADD customer_id INT UNSIGNED NULL AFTER id, ADD INDEX idx_inq_customer (customer_id)");
        $try("ALTER TABLE inquiries ADD CONSTRAINT fk_inq_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL");
    }
    if (!$hasColumn('inquiries', 'status')) {
        $try("ALTER TABLE inquiries ADD status ENUM('new','in_progress','quoted','done','cancelled') NOT NULL DEFAULT 'new' AFTER is_read");
    }
    if (!$hasColumn('inquiries', 'updated_at')) {
        $try("ALTER TABLE inquiries ADD updated_at DATETIME NULL");
    }
    $try("CREATE TABLE IF NOT EXISTS inquiry_messages (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        inquiry_id  INT UNSIGNED NOT NULL,
        author      ENUM('customer','admin') NOT NULL,
        body        TEXT NOT NULL,
        is_read     TINYINT(1) NOT NULL DEFAULT 0,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_msg_inquiry (inquiry_id),
        CONSTRAINT fk_msg_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");

    // v7: smlouvy o dílo (data smlouvy jako JSON – zůstanou i po smazání poptávky)
    $try("CREATE TABLE IF NOT EXISTS contracts (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        inquiry_id  INT UNSIGNED NULL,
        number      VARCHAR(30) NOT NULL UNIQUE,
        data        MEDIUMTEXT NOT NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME NULL,
        INDEX idx_contract_inquiry (inquiry_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");

    // v8: nadpis hodnocení, hodnocení bez hvězd (rating NULL), import hodnocení z nejremeslnici.cz
    if (!$hasColumn('reviews', 'title')) {
        $try("ALTER TABLE reviews ADD title VARCHAR(160) NULL AFTER author");
    }
    $try("ALTER TABLE reviews MODIFY rating TINYINT UNSIGNED NULL DEFAULT 5");
    if ($current < 8 && is_file(ROOT_DIR . '/database/reviews-import.php')) {
        foreach (require ROOT_DIR . '/database/reviews-import.php' as [$rTitle, $rText, $rAuthor, $rDate]) {
            $exists = q("SELECT COUNT(*) FROM reviews WHERE author = ? AND DATE(created_at) = ?", [$rAuthor, $rDate])->fetchColumn();
            if (!$exists) {
                q("INSERT INTO reviews (author, title, rating, text, lang, is_approved, created_at) VALUES (?,?,NULL,?,'cs',1,?)",
                  [$rAuthor, $rTitle, $rText, $rDate . ' 12:00:00']);
            }
        }
    }

    // v12: faktury (údaje faktury jako JSON, částka k úhradě a data pro přehled)
    $try("CREATE TABLE IF NOT EXISTS invoices (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        number      VARCHAR(30) NOT NULL UNIQUE,
        inquiry_id  INT UNSIGNED NULL,
        contract_id INT UNSIGNED NULL,
        data        MEDIUMTEXT NOT NULL,
        total       DECIMAL(12,2) NOT NULL DEFAULT 0,
        issued      DATE NOT NULL,
        due         DATE NOT NULL,
        paid_at     DATE NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME NULL,
        INDEX idx_inv_inquiry (inquiry_id),
        INDEX idx_inv_contract (contract_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");

    // v13: soukromý odkaz zákazníka na poptávku (ukládá se jen hash tokenu)
    if (!$hasColumn('inquiries', 'access_hash')) {
        $try("ALTER TABLE inquiries ADD access_hash CHAR(64) NULL AFTER id");
    }

    // v15: fotky přiložené k poptávce (soubory v uploads/private/inquiries/{id}/)
    $try("CREATE TABLE IF NOT EXISTS inquiry_files (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        inquiry_id  INT UNSIGNED NOT NULL,
        file        VARCHAR(80)  NOT NULL,
        original    VARCHAR(160) NULL,
        size        INT UNSIGNED NOT NULL DEFAULT 0,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_file_inquiry (inquiry_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");

    // nové texty a nastavení (INSERT IGNORE – nepřepíše úpravy)
    $file = ROOT_DIR . '/database/migrate.sql';
    if (is_file($file)) {
        $sql = preg_replace('~^\s*--.*$~m', '', (string) file_get_contents($file));
        foreach (preg_split('~;\s*(\R|$)~', (string) $sql) as $stmt) {
            if (trim($stmt) !== '') db()->exec($stmt);
        }
    }

    q("INSERT INTO properties (pkey, grp, type, label, value_cs, value_en, sort) VALUES ('sys.db_version', 'sys', 'line', 'Verze databáze', ?, '', 0)
       ON DUPLICATE KEY UPDATE value_cs = VALUES(value_cs)", [(string) DB_VERSION]);
    props(true);
}
