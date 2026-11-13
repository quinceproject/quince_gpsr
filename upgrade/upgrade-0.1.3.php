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

use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinition;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyScope;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyType;
use Quince\Gpsr\Form\OrganizationChoiceType;
use Symfony\Component\Validator\Constraints\Length;

/**
 * 0.1.3: pola producenta i podmiotu odpowiedzialnego przy produkcie i marce.
 *
 * Na PrestaShopie 9.2.1 instalacja zapisywała tylko pole informacji
 * o bezpieczeństwie: rdzeń odrzucał pozostałe cztery przez własną regułę
 * walidacji. Rejestracja jest powtarzalna, więc istniejące pola zostają.
 *
 * Definicje są tutaj, a nie w metodzie modułu: przy aktualizacji z panelu
 * w pamięci bywa jeszcze klasa modułu w starej wersji, której metod nie
 * wolno zakładać. Wołamy tylko publiczną metodę rdzenia.
 *
 * @param Module $module
 */
function upgrade_module_0_1_3($module): bool
{
    $domain = 'Modules.Quincegpsr.Admin';
    $pola = [
        ['product', 'manufacturer', 'product', ['/products', '/products/{productId}'], [],
            'Manufacturer (override)', 'Code of a company from the GPSR register. Empty means the brand decides.'],
        ['product', 'responsible', 'product', ['/products', '/products/{productId}'], ['responsible_only' => true],
            'Responsible operator (override)', 'Code of a company from the GPSR register. Empty means the brand decides, and a manufacturer based in the EU takes the role itself.'],
        ['manufacturer', 'manufacturer', 'manufacturer', ['/manufacturers', '/manufacturers/{manufacturerId}'], ['placeholder' => 'Not set'],
            'Manufacturer for GPSR', 'Code of a company from the GPSR register. Products of this brand inherit it.'],
        ['manufacturer', 'responsible', 'manufacturer', ['/manufacturers', '/manufacturers/{manufacturerId}'], ['responsible_only' => true, 'placeholder' => 'Not set'],
            'Responsible operator for GPSR', 'Code of a company from the GPSR register. Products of this brand inherit it.'],
    ];

    foreach ($pola as [$encja, $pole, $formularz, $api, $opcje, $etykieta, $opis]) {
        $ok = $module->registerExtraProperty(new ExtraPropertyDefinition(
            entityName: $encja,
            propertyName: $pole,
            type: ExtraPropertyType::STRING,
            scope: ExtraPropertyScope::COMMON,
            size: 64,
            associatedForms: [$formularz],
            associatedApis: $api,
            formType: OrganizationChoiceType::class,
            formOptions: $opcje ?: null,
            constraints: [new Length(max: 64)],
            labelWording: $etykieta,
            labelDomain: $domain,
            descriptionWording: $opis,
            descriptionDomain: $domain
        ));

        if (!$ok) {
            return false;
        }
    }

    return true;
}
