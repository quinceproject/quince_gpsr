<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\Gpsr;

use Db;
use Quince\Gpsr\Storage\OrganizationRepository;

/**
 * Kto odpowiada za ten produkt: rozstrzygnięcie kaskady.
 *
 * Przypisanie stoi przy produkcie albo przy marce, jako pole dodatkowe rdzenia.
 * Puste pole przy produkcie znaczy „weź z marki": przy kilkuset produktach
 * jednej marki nikt nie wskazuje producenta tysiąc razy.
 *
 * Gdy podmiot odpowiedzialny nie jest wskazany nigdzie, wchodzi reguła z art. 16
 * ust. 1: producent z siedzibą w Unii jest nim sam z siebie. Producent spoza
 * Unii nie, i wtedy karta zostaje bez podmiotu. To jest brak do uzupełnienia,
 * a nie stan do ukrycia.
 */
final class Resolver
{
    /** Kolumny pól dodatkowych. Nazwa to umowa rdzenia: moduł i nazwa pola. */
    private const COLUMN_MANUFACTURER = 'quince_gpsr_manufacturer';
    private const COLUMN_RESPONSIBLE = 'quince_gpsr_responsible';
    private const COLUMN_SAFETY = 'quince_gpsr_safety';

    public function __construct(private readonly OrganizationRepository $rejestr)
    {
    }

    /**
     * Komplet danych GPSR dla jednego produktu.
     *
     * @return array{
     *     manufacturer: array<string,mixed>|null,
     *     responsible: array<string,mixed>|null,
     *     responsible_inherited: bool,
     *     safety: string
     * }
     */
    public function forProduct(int $idProduct, int $idLang, int $idShop): array
    {
        $produkt = $this->assignments('product', 'id_product', $idProduct);
        $idMarki = $this->brandOf($idProduct);
        $marka = $idMarki > 0 ? $this->assignments('manufacturer', 'id_manufacturer', $idMarki) : ['manufacturer' => '', 'responsible' => ''];

        $rozstrzygniecie = self::decide(
            $this->organization($produkt['manufacturer'] ?: $marka['manufacturer']),
            $this->organization($produkt['responsible'] ?: $marka['responsible'])
        );

        return $rozstrzygniecie + ['safety' => $this->safety($idProduct, $idLang, $idShop)];
    }

    /**
     * Reguła wyboru podmiotu odpowiedzialnego i lista braków.
     *
     * Statyczna i bez zapytań, bo używa jej i karta produktu, i przegląd
     * braków w panelu. Przegląd przechodzi po całym katalogu jednym zapytaniem
     * i nie może pytać bazy o każdy produkt z osobna, ale liczyć ma dokładnie
     * to samo, co widzi klient.
     *
     * @param array<string,mixed>|null $producent
     * @param array<string,mixed>|null $wskazany  podmiot wskazany wprost
     *
     * @return array{
     *     manufacturer: array<string,mixed>|null,
     *     responsible: array<string,mixed>|null,
     *     responsible_inherited: bool,
     *     gaps: array<int,string>
     * }
     */
    public static function decide(?array $producent, ?array $wskazany): array
    {
        // Producent z siedzibą w Unii jest podmiotem odpowiedzialnym sam
        // z siebie (art. 16 ust. 1 GPSR).
        $odpowiedzialny = $wskazany;
        $zReguly = false;

        if ($odpowiedzialny === null && $producent !== null && Eu::has((string) $producent['country'])) {
            $odpowiedzialny = $producent;
            $zReguly = true;
        }

        return [
            'manufacturer' => $producent,
            'responsible' => $odpowiedzialny,
            'responsible_inherited' => $zReguly,
            'gaps' => self::gaps($producent, $odpowiedzialny),
        ];
    }

    /**
     * Czego brakuje do wymogu z art. 19 ust. 1.
     *
     * Braku tekstu o bezpieczeństwie tu nie ma i nie będzie: ostrzeżenia dotyczą
     * produktów, które je mają, a kubek ich nie ma. Zaliczanie tego jako
     * niezgodności zamieniłoby przegląd w listę, na którą nikt nie patrzy.
     *
     * @param array<string,mixed>|null $producent
     * @param array<string,mixed>|null $odpowiedzialny
     *
     * @return array<int,string>
     */
    public static function gaps(?array $producent, ?array $odpowiedzialny): array
    {
        if ($odpowiedzialny === null) {
            return $producent === null
                ? ['no_operator']
                : ['no_eu_operator'];
        }

        $braki = [];

        if (($odpowiedzialny['trade_name'] ?? '') === '') {
            $braki[] = 'no_name';
        }

        if (($odpowiedzialny['street'] ?? '') === '' || ($odpowiedzialny['city'] ?? '') === '' || ($odpowiedzialny['country'] ?? '') === '') {
            $braki[] = 'no_address';
        }

        if (($odpowiedzialny['email'] ?? '') === '' && ($odpowiedzialny['contact_url'] ?? '') === '') {
            $braki[] = 'no_contact';
        }

        return $braki;
    }

    /**
     * Czy produkt ma komplet danych wymaganych przez art. 19.
     *
     * Używa tego panel, żeby pokazać braki zanim zrobi to kontrola.
     */
    public function isComplete(int $idProduct, int $idLang, int $idShop): bool
    {
        return $this->forProduct($idProduct, $idLang, $idShop)['gaps'] === [];
    }

    /**
     * Kody przypisane wprost do encji.
     *
     * Tabela pola dodatkowego powstaje dopiero z rejestracją pola, więc przy
     * pierwszym uruchomieniu po instalacji może jeszcze nie istnieć. Zapytanie
     * opakowane, bo brak tabeli nie jest błędem wartym strony błędu.
     *
     * @return array{manufacturer:string,responsible:string}
     */
    private function assignments(string $entity, string $klucz, int $id): array
    {
        $puste = ['manufacturer' => '', 'responsible' => ''];

        if ($id <= 0) {
            return $puste;
        }

        $wiersz = Db::getInstance()->getRow(sprintf(
            'SELECT `%s` AS producent, `%s` AS odpowiedzialny FROM `%s%s_extra` WHERE `%s` = %d',
            self::COLUMN_MANUFACTURER,
            self::COLUMN_RESPONSIBLE,
            _DB_PREFIX_,
            bqSQL($entity),
            bqSQL($klucz),
            $id
        ), false);

        if (!is_array($wiersz)) {
            return $puste;
        }

        return [
            'manufacturer' => (string) ($wiersz['producent'] ?? ''),
            'responsible' => (string) ($wiersz['odpowiedzialny'] ?? ''),
        ];
    }

    /** Tekst o bezpieczeństwie w języku klienta. */
    private function safety(int $idProduct, int $idLang, int $idShop): string
    {
        $wartosc = Db::getInstance()->getValue(sprintf(
            'SELECT `%s` FROM `%sproduct_extra_lang` WHERE id_product = %d AND id_lang = %d AND id_shop = %d',
            self::COLUMN_SAFETY,
            _DB_PREFIX_,
            $idProduct,
            $idLang,
            $idShop
        ), false);

        return is_string($wartosc) ? $wartosc : '';
    }

    /**
     * Marka produktu.
     *
     * Czytana z `product`, nie z `product_shop`: w PrestaShopie 9.2 marka nie
     * jest ustawieniem per sklep i kolumny `id_manufacturer` w tabeli sklepowej
     * po prostu nie ma.
     */
    private function brandOf(int $idProduct): int
    {
        return (int) Db::getInstance()->getValue(sprintf(
            'SELECT id_manufacturer FROM `%sproduct` WHERE id_product = %d',
            _DB_PREFIX_,
            $idProduct
        ));
    }

    /** @return array<string,mixed>|null */
    private function organization(string $code): ?array
    {
        return $code !== '' ? $this->rejestr->byCode($code) : null;
    }
}
