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

/**
 * Kraje Unii, po kodzie ISO 3166-1 alpha-2.
 *
 * Potrzebne do jednego rozstrzygnięcia: producent z siedzibą w Unii jest
 * podmiotem odpowiedzialnym sam z siebie (art. 16 ust. 1 GPSR), producent spoza
 * Unii wymaga wskazania kogoś innego.
 *
 * Lista jest krótka i zmienia się raz na dekadę, więc stoi w kodzie, a nie w
 * ustawieniach: pole do wpisania krajów Unii przez sklep byłoby pytaniem, na
 * które prowadzący sklep nie ma powodu odpowiadać.
 *
 * Norwegia, Islandia i Liechtenstein nie są w Unii, ale są w EOG. GPSR mówi o
 * wprowadzeniu do obrotu w Unii, więc ich tutaj nie ma. Wielka Brytania wypadła
 * z listy razem z brexitem i dlatego towar z Wysp wymaga dziś importera.
 */
final class Eu
{
    public const COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR',
        'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO',
        'SE', 'SI', 'SK',
    ];

    public static function has(string $country): bool
    {
        return in_array(strtoupper(trim($country)), self::COUNTRIES, true);
    }
}
