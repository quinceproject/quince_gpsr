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
 * 0.1.5: zakładki panelu w sklepach, w których ich nie ma.
 *
 * Na PrestaShopie 9.2.1 instalacja 0.1.2 przerywała się na polach firmy, zanim
 * doszła do zakładek. Bez zakładki `AdminQuinceGpsrOrganizations` rdzeń nie
 * znajduje uprawnień do trasy ekranu i odpowiada 403.
 *
 * Logika tutaj, nie w metodzie modułu: przy aktualizacji z panelu w pamięci
 * bywa jeszcze klasa modułu w starej wersji.
 *
 * @param Module $module
 */
function upgrade_module_0_1_5($module): bool
{
    // Pod menu Quince, gdy jest silnik; bez niego własna gałąź.
    $rodzic = (int) Tab::getIdFromClassName('AdminQuinceProject_MTR');
    if ($rodzic <= 0) {
        $rodzic = (int) Tab::getIdFromClassName('AdminQuinceProject');
    }
    if ($rodzic <= 0) {
        $rodzic = quince_gpsr_015_tab($module, 'AdminQuinceGpsr', 'Quince GPSR', 0, '');
    }

    return $rodzic > 0
        && quince_gpsr_015_tab($module, 'AdminQuinceGpsrOrganizations', 'Product safety', $rodzic, 'quince_gpsr_organizations') > 0;
}

/** Zakłada zakładkę, jeśli jej nie ma. Zwraca jej id albo 0. */
function quince_gpsr_015_tab($module, string $klasa, string $nazwa, int $rodzic, string $trasa): int
{
    $istniejaca = (int) Tab::getIdFromClassName($klasa);
    if ($istniejaca > 0) {
        return $istniejaca;
    }

    $zakladka = new Tab();
    $zakladka->class_name = $klasa;
    $zakladka->module = $module->name;
    $zakladka->id_parent = $rodzic;
    $zakladka->active = true;
    if ($trasa !== '') {
        $zakladka->route_name = $trasa;
    }
    $zakladka->wording = $nazwa;
    $zakladka->wording_domain = 'Modules.Quincegpsr.Admin';

    foreach (Language::getLanguages(false) as $jezyk) {
        $zakladka->name[(int) $jezyk['id_lang']] = $nazwa;
    }

    return $zakladka->add() ? (int) $zakladka->id : 0;
}
