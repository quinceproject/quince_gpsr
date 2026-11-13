<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 0.1.3: pola producenta i podmiotu odpowiedzialnego przy produkcie i marce.
 *
 * Na PrestaShopie 9.2.1 instalacja zapisywała tylko pole informacji
 * o bezpieczeństwie: rdzeń odrzucał pozostałe cztery przez własną regułę
 * walidacji. Rejestracja jest powtarzalna, więc istniejące pola zostają.
 *
 * @param Quince_Gpsr $module
 */
function upgrade_module_0_1_3($module): bool
{
    return $module->installFields();
}
