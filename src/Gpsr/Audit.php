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
 * Przegląd braków: które włączone produkty nie mają kompletu danych GPSR.
 *
 * Bez tego ekranu prowadzący sklep sprawdza zgodność otwierając produkt po
 * produkcie, czyli przy dwóch tysiącach pozycji nie sprawdza jej wcale.
 *
 * Liczy tylko produkty włączone w danym sklepie: produkt wyłączony nikomu się
 * nie wyświetla, więc nie ma przy nim obowiązku z art. 19, a mieszanie go do
 * listy zrobiłoby z niej listę, której nie da się doprowadzić do zera.
 *
 * Rozstrzygnięcie i lista braków idą przez `Resolver::decide()`, czyli przez tę
 * samą regułę, którą widzi klient na karcie produktu. Kaskada produkt → marka
 * jedzie w zapytaniu, a nie w pętli: przy katalogu z tysiącami pozycji zapytanie
 * na produkt oznaczałoby tysiące zapytań na jedno wejście na ekran.
 */
final class Audit
{
    /** Ile wierszy przeglądamy najwyżej. Zapora przed katalogiem, który nie mieści się w pamięci. */
    private const LIMIT = 20000;

    public function __construct(private readonly OrganizationRepository $rejestr)
    {
    }

    /**
     * Produkty z brakami.
     *
     * @param array{category?:int,brand?:int,phrase?:string} $filtry zawężenie listy roboczej
     *
     * @return array{rows: array<int,array<string,mixed>>, checked: int, incomplete: int}
     */
    public function incomplete(int $idShop, int $idLang, array $filtry = []): array
    {
        $firmy = [];

        foreach ($this->rejestr->all() as $firma) {
            $firmy[$firma['code']] = $firma;
        }

        $braki = [];
        $sprawdzonych = 0;

        foreach ($this->products($idShop, $idLang, $filtry) as $produkt) {
            ++$sprawdzonych;

            $rozstrzygniecie = Resolver::decide(
                $firmy[$produkt['kod_producenta']] ?? null,
                $firmy[$produkt['kod_odpowiedzialnego']] ?? null
            );

            if ($rozstrzygniecie['gaps'] === []) {
                continue;
            }

            $braki[] = [
                'id_product' => (int) $produkt['id_product'],
                'name' => (string) $produkt['name'],
                'reference' => (string) $produkt['reference'],
                'manufacturer' => $rozstrzygniecie['manufacturer'],
                'responsible' => $rozstrzygniecie['responsible'],
                // Skąd wzięło się przypisanie: z produktu, z marki, czy znikąd.
                // Bez tego prowadzący sklep poprawia produkt po produkcie zamiast
                // raz markę, a przy produkcie bez żadnego przypisania szukałby
                // wartości przy marce, której tam nie ma.
                'from_product' => $produkt['kod_z_produktu'] !== '',
                'from_brand' => $produkt['kod_z_produktu'] === '' && $produkt['kod_z_marki'] !== '',
                'gaps' => $rozstrzygniecie['gaps'],
            ];
        }

        return [
            'rows' => $braki,
            'checked' => $sprawdzonych,
            'incomplete' => count($braki),
        ];
    }

    /**
     * Włączone produkty sklepu wraz z rozstrzygniętą kaskadą kodów.
     *
     * Zawężenia idą w zapytaniu, nie po pobraniu: lista robocza przy katalogu
     * z tysiącami pozycji ma zaczynać się od tego, czym prowadzący sklep chce
     * się zająć teraz, a nie od przewijania wszystkiego.
     *
     * @param array{category?:int,brand?:int,phrase?:string} $filtry
     *
     * @return array<int,array<string,mixed>>
     */
    private function products(int $idShop, int $idLang, array $filtry = []): array
    {
        $idKategorii = (int) ($filtry['category'] ?? 0);
        $idMarki = (int) ($filtry['brand'] ?? 0);
        $fraza = trim((string) ($filtry['phrase'] ?? ''));

        $zlaczenie = $idKategorii > 0
            ? sprintf('INNER JOIN `%scategory_product` cp ON cp.id_product = p.id_product AND cp.id_category = %d', _DB_PREFIX_, $idKategorii)
            : '';

        $warunki = '';

        if ($idMarki > 0) {
            $warunki .= ' AND p.id_manufacturer = ' . $idMarki;
        }

        if ($fraza !== '') {
            $szukane = '%' . pSQL($fraza) . '%';
            $warunki .= ' AND (pl.name LIKE "' . $szukane . '" OR p.reference LIKE "' . $szukane . '")';
        }

        $zapytanie = sprintf(
            'SELECT p.id_product, p.reference, pl.name,
                COALESCE(NULLIF(pe.`quince_gpsr_manufacturer`, ""), NULLIF(me.`quince_gpsr_manufacturer`, ""), "") AS kod_producenta,
                COALESCE(NULLIF(pe.`quince_gpsr_responsible`, ""), NULLIF(me.`quince_gpsr_responsible`, ""), "") AS kod_odpowiedzialnego,
                COALESCE(NULLIF(pe.`quince_gpsr_responsible`, ""), NULLIF(pe.`quince_gpsr_manufacturer`, ""), "") AS kod_z_produktu,
                COALESCE(NULLIF(me.`quince_gpsr_responsible`, ""), NULLIF(me.`quince_gpsr_manufacturer`, ""), "") AS kod_z_marki
            FROM `%1$sproduct_shop` ps
            INNER JOIN `%1$sproduct` p ON p.id_product = ps.id_product
            INNER JOIN `%1$sproduct_lang` pl ON pl.id_product = p.id_product AND pl.id_shop = ps.id_shop AND pl.id_lang = %2$d
            LEFT JOIN `%1$sproduct_extra` pe ON pe.id_product = p.id_product
            LEFT JOIN `%1$smanufacturer_extra` me ON me.id_manufacturer = p.id_manufacturer
            %5$s
            WHERE ps.id_shop = %3$d AND ps.active = 1 %6$s
            GROUP BY p.id_product
            ORDER BY pl.name ASC
            LIMIT %4$d',
            _DB_PREFIX_,
            $idLang,
            $idShop,
            self::LIMIT,
            $zlaczenie,
            $warunki
        );

        // Tabele `product_extra` i `manufacturer_extra` powstają razem
        // z rejestracją pól dodatkowych, czyli przy instalacji modułu. Ekran
        // działa tylko przy module zainstalowanym, więc zapytanie ma po czym
        // jechać.
        $wiersze = Db::getInstance()->executeS($zapytanie);

        return is_array($wiersze) ? $wiersze : [];
    }
}
