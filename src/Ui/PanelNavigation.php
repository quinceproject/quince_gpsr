<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\Ui;

/**
 * Zakładki modułu: rejestr firm i przegląd braków.
 *
 * Zakładki u góry, a nie pasek z boku: dwa widoki nie potrzebują własnej
 * kolumny, a każda zakładka prowadzi do osobnej trasy, więc widok otwiera się
 * wprost z adresu razem z filtrami i stroną.
 */
final class PanelNavigation
{
    /**
     * @return array<int,array{label:string,route:string}>
     */
    public function items(): array
    {
        return [
            ['label' => 'Company register', 'route' => 'quince_gpsr_organizations'],
            ['label' => 'Products without data', 'route' => 'quince_gpsr_products'],
        ];
    }
}
