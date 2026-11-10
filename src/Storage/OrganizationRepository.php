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
use DbQuery;
use Quince\Gpsr\Gpsr\Roles;

/**
 * Rejestr firm: odczyt dla sklepu, zapis dla panelu i dla wgrywania z zewnątrz.
 *
 * Wiersz wpisany ręcznie w panelu jest nietykalny dla wgrywania. Pierwszy import
 * inaczej skasowałby pracę prowadzącego sklep, a on nie ma jak jej odzyskać.
 * W drugą stronę wolno: panel może poprawić wiersz z importu, bo to świadoma
 * decyzja człowieka, który widzi, co zmienia.
 */
final class OrganizationRepository
{
    /** Pola, które niesie wpis rejestru. Kolejność jak w kontrakcie wgrywania. */
    private const FIELDS = [
        'trade_name', 'legal_name', 'street', 'postal_code', 'city', 'country',
        'email', 'contact_url', 'phone', 'tax_id',
    ];

    /**
     * Zapis wpisu, rozpoznawany po `code`.
     *
     * @param array<string,mixed> $dane
     *
     * @return int identyfikator wpisu, 0 gdy zapis pominięty albo nieudany
     */
    public function save(array $dane, string $source = 'manual'): int
    {
        $code = trim((string) ($dane['code'] ?? ''));

        if ($code === '') {
            return 0;
        }

        $istniejacy = $this->byCode($code);

        // Wgrywanie nie rusza wiersza wpisanego ręcznie.
        if ($istniejacy !== null && $source === 'import' && $istniejacy['source'] === 'manual') {
            return 0;
        }

        $wiersz = [
            'roles' => pSQL(implode(',', Roles::clean($dane['roles'] ?? []))),
            'retired' => !empty($dane['retired']) ? 1 : 0,
            'date_upd' => date('Y-m-d H:i:s'),
        ];

        foreach (self::FIELDS as $pole) {
            if (array_key_exists($pole, $dane)) {
                $wartosc = (string) $dane[$pole];
                $wiersz[$pole] = pSQL($pole === 'country' ? strtoupper(substr($wartosc, 0, 2)) : $wartosc);
            }
        }

        if ($istniejacy !== null) {
            // Źródło zostaje przy wierszu: raz wpisany ręcznie zostaje ręczny,
            // inaczej pierwsze wgranie odebrałoby mu ochronę.
            return Db::getInstance()->update(Schema::ORGANIZATION, $wiersz, '`code` = "' . pSQL($code) . '"')
                ? (int) $istniejacy['id_organization']
                : 0;
        }

        $wiersz['code'] = pSQL($code);
        $wiersz['source'] = $source === 'import' ? 'import' : 'manual';
        $wiersz['date_add'] = date('Y-m-d H:i:s');

        return Db::getInstance()->insert(Schema::ORGANIZATION, $wiersz)
            ? (int) Db::getInstance()->Insert_ID()
            : 0;
    }

    /** @return array<string,mixed>|null */
    public function byCode(string $code): ?array
    {
        if ($code === '') {
            return null;
        }

        $zapytanie = new DbQuery();
        $zapytanie->select('*')
            ->from(Schema::ORGANIZATION)
            ->where('`code` = "' . pSQL($code) . '"');

        $wiersz = Db::getInstance()->getRow($zapytanie);

        return $wiersz ? $this->hydrate($wiersz) : null;
    }

    /** @return array<string,mixed>|null */
    public function byId(int $id): ?array
    {
        $zapytanie = new DbQuery();
        $zapytanie->select('*')
            ->from(Schema::ORGANIZATION)
            ->where('`id_organization` = ' . $id);

        $wiersz = Db::getInstance()->getRow($zapytanie);

        return $wiersz ? $this->hydrate($wiersz) : null;
    }

    /**
     * Wszystkie wpisy, wycofane na końcu.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(bool $withRetired = true): array
    {
        return $this->search('', $withRetired ? null : false);
    }

    /**
     * Wyszukiwanie i stronicowanie.
     *
     * Szuka po nazwie, kodzie i mieście: prowadzący sklep pamięta firmę po
     * jednej z tych trzech rzeczy, a nie po wierszu w tabeli.
     *
     * @param string    $phrase  fraza; pusta znaczy „wszystkie"
     * @param bool|null $retired null bierze wycofane i czynne, `false` tylko czynne
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $phrase = '', ?bool $retired = null, int $limit = 0, int $offset = 0): array
    {
        $zapytanie = new DbQuery();
        $zapytanie->select('*')
            ->from(Schema::ORGANIZATION)
            ->orderBy('`retired` ASC, `trade_name` ASC');

        if ($retired !== null) {
            $zapytanie->where('`retired` = ' . ($retired ? 1 : 0));
        }

        $fraza = trim($phrase);

        if ($fraza !== '') {
            $szukane = '%' . pSQL($fraza) . '%';
            $zapytanie->where(
                '`trade_name` LIKE "' . $szukane . '"'
                . ' OR `legal_name` LIKE "' . $szukane . '"'
                . ' OR `code` LIKE "' . $szukane . '"'
                . ' OR `city` LIKE "' . $szukane . '"'
            );
        }

        if ($limit > 0) {
            $zapytanie->limit($limit, max(0, $offset));
        }

        $wiersze = Db::getInstance()->executeS($zapytanie) ?: [];

        return array_map([$this, 'hydrate'], $wiersze);
    }

    /**
     * Lista do pola wyboru przy produkcie i przy marce.
     *
     * Wycofane wypadają: nie wolno przypisać firmy, której już nie ma, a te,
     * które ją mają, dalej ją widzą.
     *
     * @param bool $responsibleOnly tylko wpisy, które wolno wskazać jako podmiot odpowiedzialny
     *
     * @return array<string,string> etykieta => kod
     */
    public function choices(bool $responsibleOnly = false): array
    {
        $wybor = [];

        foreach ($this->all(false) as $wpis) {
            if ($responsibleOnly && !Roles::canBeResponsible($wpis['roles'])) {
                continue;
            }

            $nazwa = $wpis['trade_name'] !== '' ? $wpis['trade_name'] : $wpis['code'];
            $wybor[$nazwa . ($wpis['country'] !== '' ? ' (' . $wpis['country'] . ')' : '')] = $wpis['code'];
        }

        return $wybor;
    }

    /** Wycofanie wpisu. Wiersz zostaje, bo produkty nadal go wskazują. */
    public function retire(string $code, bool $retired = true): bool
    {
        return (bool) Db::getInstance()->update(
            Schema::ORGANIZATION,
            ['retired' => $retired ? 1 : 0, 'date_upd' => date('Y-m-d H:i:s')],
            '`code` = "' . pSQL($code) . '"'
        );
    }

    /**
     * Skasowanie wpisu. Tylko z panelu i tylko świadomie.
     *
     * Razem z firmą znikają wskazania na nią przy produktach i markach.
     * Zostawiony kod wskazywałby donikąd: karta produktu i tak nic by nie
     * wypisała, a przy najbliższym zapisie tego produktu sprawdzenie kodu
     * odrzuciłoby zapis komunikatem o firmie spoza rejestru, blokując zmianę,
     * która z GPSR nie ma nic wspólnego.
     */
    public function delete(string $code): bool
    {
        if ($code === '') {
            return false;
        }

        $usuniety = (bool) Db::getInstance()->delete(Schema::ORGANIZATION, '`code` = "' . pSQL($code) . '"');

        if ($usuniety) {
            $this->clearAssignments($code);
        }

        return $usuniety;
    }

    /** Wyczyszczenie wskazań na firmę przy produktach i markach. */
    private function clearAssignments(string $code): void
    {
        $kod = '"' . pSQL($code) . '"';

        foreach (['product_extra', 'manufacturer_extra'] as $tabela) {
            foreach (['quince_gpsr_manufacturer', 'quince_gpsr_responsible'] as $kolumna) {
                Db::getInstance()->execute(sprintf(
                    'UPDATE `%s%s` SET `%s` = "" WHERE `%s` = %s',
                    _DB_PREFIX_,
                    $tabela,
                    $kolumna,
                    $kolumna,
                    $kod
                ));
            }
        }
    }

    /**
     * Kto wskazuje tę firmę.
     *
     * Potrzebne przed skasowaniem: wiersz znika w jednej chwili, a razem z nim
     * dane podmiotu odpowiedzialnego na wszystkich kartach, które go wskazują.
     * Karta bez tych danych jest niezgodna z art. 19, a nikt tego nie zauważy,
     * bo sekcja po prostu przestaje się wypisywać.
     *
     * @return array{products:int,brands:int}
     */
    public function usageOf(string $code): array
    {
        $kod = '"' . pSQL($code) . '"';

        $produkty = (int) Db::getInstance()->getValue(sprintf(
            'SELECT COUNT(*) FROM `%sproduct_extra` WHERE `quince_gpsr_manufacturer` = %s OR `quince_gpsr_responsible` = %s',
            _DB_PREFIX_,
            $kod,
            $kod
        ));

        $marki = (int) Db::getInstance()->getValue(sprintf(
            'SELECT COUNT(*) FROM `%smanufacturer_extra` WHERE `quince_gpsr_manufacturer` = %s OR `quince_gpsr_responsible` = %s',
            _DB_PREFIX_,
            $kod,
            $kod
        ));

        return ['products' => $produkty, 'brands' => $marki];
    }

    public function count(bool $withRetired = false): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . Schema::ORGANIZATION . '`'
            . ($withRetired ? '' : ' WHERE `retired` = 0')
        );
    }

    /** Ile wierszy odpowiada frazie. Potrzebne stronicowaniu, żeby znało koniec listy. */
    public function countSearch(string $phrase = ''): int
    {
        $fraza = trim($phrase);
        $warunek = '';

        if ($fraza !== '') {
            $szukane = '%' . pSQL($fraza) . '%';
            $warunek = ' WHERE `trade_name` LIKE "' . $szukane . '"'
                . ' OR `legal_name` LIKE "' . $szukane . '"'
                . ' OR `code` LIKE "' . $szukane . '"'
                . ' OR `city` LIKE "' . $szukane . '"';
        }

        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . Schema::ORGANIZATION . '`' . $warunek
        );
    }

    /** Ślad wgrywania. @param array<string,mixed> $dane */
    public function logImport(string $channel, int $organizations, int $rejected, string $status, string $message = ''): void
    {
        Db::getInstance()->insert(Schema::IMPORT, [
            'date_add' => date('Y-m-d H:i:s'),
            'channel' => pSQL($channel),
            'organizations' => $organizations,
            'rejected' => $rejected,
            'status' => pSQL($status),
            'message' => pSQL($message, true),
        ]);
    }

    /** @return array<string,mixed>|null */
    public function lastImport(): ?array
    {
        // Bez `limit(1)`: `getRow()` dokłada własne ograniczenie i zapytanie
        // kończyłoby się dwoma klauzulami LIMIT, czyli błędem składni.
        $zapytanie = new DbQuery();
        $zapytanie->select('*')
            ->from(Schema::IMPORT)
            ->orderBy('`date_add` DESC');

        $wiersz = Db::getInstance()->getRow($zapytanie);

        return $wiersz ?: null;
    }

    /**
     * Wiersz z bazy w postaci, jakiej oczekuje reszta modułu.
     *
     * @param array<string,mixed> $wiersz
     *
     * @return array<string,mixed>
     */
    private function hydrate(array $wiersz): array
    {
        $wiersz['id_organization'] = (int) $wiersz['id_organization'];
        $wiersz['roles'] = Roles::clean($wiersz['roles'] ?? '');
        $wiersz['retired'] = (bool) $wiersz['retired'];

        return $wiersz;
    }
}
