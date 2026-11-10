<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\Storage;

use Db;

/**
 * Rejestr firm i ślad wgrywania.
 *
 * Rejestr jest wspólny dla całej instalacji, nie per sklep: wpis to dane firmy,
 * a adres producenta nie zmienia się od tego, w którym sklepie sprzedajemy jego
 * towar. Przypisanie, czyli decyzja „ten produkt ma tę firmę", siedzi osobno,
 * przy produkcie i przy marce, jako pola dodatkowe rdzenia.
 *
 * `code` jest złączem z narzędziem zasilającym i kluczem zapisu idempotentnego.
 * Musi być stabilny przez całe życie wpisu, bo po nim idzie upsert; nazwa i
 * adres mogą się zmieniać do woli.
 *
 * Wpis wycofany zostaje w tabeli. Kasowanie zabrałoby dane firmy z produktów,
 * które ciągle ją wskazują, a karta bez podmiotu odpowiedzialnego jest niezgodna
 * z art. 19 GPSR.
 */
final class Schema
{
    public const ORGANIZATION = 'quince_gpsr_organization';
    public const IMPORT = 'quince_gpsr_import';

    public static function install(): bool
    {
        $silnik = _MYSQL_ENGINE_;
        $zapytania = [];

        // Adres rozbity na części, a nie jedno pole tekstowe: rynki zewnętrzne
        // (Allegro, Amazon) biorą go polami, a karta produktu skleja z powrotem
        // w jedną linijkę bez straty. Odwrotnie się nie da.
        $zapytania[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::ORGANIZATION . '` (
            `id_organization` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(64) NOT NULL,
            `roles` VARCHAR(255) NOT NULL DEFAULT \'\',
            `trade_name` VARCHAR(255) NOT NULL DEFAULT \'\',
            `legal_name` VARCHAR(255) NOT NULL DEFAULT \'\',
            `street` VARCHAR(255) NOT NULL DEFAULT \'\',
            `postal_code` VARCHAR(32) NOT NULL DEFAULT \'\',
            `city` VARCHAR(128) NOT NULL DEFAULT \'\',
            `country` CHAR(2) NOT NULL DEFAULT \'\',
            `email` VARCHAR(255) NOT NULL DEFAULT \'\',
            `contact_url` VARCHAR(255) NOT NULL DEFAULT \'\',
            `phone` VARCHAR(64) NOT NULL DEFAULT \'\',
            `tax_id` VARCHAR(32) NOT NULL DEFAULT \'\',
            `source` ENUM(\'import\', \'manual\') NOT NULL DEFAULT \'import\',
            `retired` TINYINT(1) NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_organization`),
            UNIQUE KEY `kod` (`code`),
            KEY `wycofane` (`retired`)
        ) ENGINE=' . $silnik . ' DEFAULT CHARSET=utf8mb4;';

        // Ślad wgrywania: bez niego nikt nie zauważy, że dane przestały
        // przychodzić, a karta produktu wygląda tak samo do dnia kontroli.
        $zapytania[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::IMPORT . '` (
            `id_import` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `date_add` DATETIME NOT NULL,
            `channel` VARCHAR(64) NOT NULL DEFAULT \'\',
            `organizations` INT(11) NOT NULL DEFAULT 0,
            `rejected` INT(11) NOT NULL DEFAULT 0,
            `status` ENUM(\'ok\', \'error\', \'partial\') NOT NULL DEFAULT \'ok\',
            `message` TEXT NULL,
            PRIMARY KEY (`id_import`),
            KEY `kiedy` (`date_add`)
        ) ENGINE=' . $silnik . ' DEFAULT CHARSET=utf8mb4;';

        foreach ($zapytania as $zapytanie) {
            if (!Db::getInstance()->execute($zapytanie)) {
                return false;
            }
        }

        return true;
    }

    public static function uninstall(): bool
    {
        foreach ([self::IMPORT, self::ORGANIZATION] as $tabela) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $tabela . '`');
        }

        return true;
    }
}
