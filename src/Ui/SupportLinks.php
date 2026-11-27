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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Adresy ekranu „Support”: wsparcie projektu, strona projektu i zgłoszenia
 * w repozytorium tego modułu.
 *
 * Te same adresy co w `Quince\Engine\Ui\SupportLinks`. Kopia, nie odwołanie:
 * moduł działa bez silnika.
 */
final class SupportLinks
{
    /** Strona wsparcia projektu na Ko-fi. */
    public const KOFI = 'https://ko-fi.com/quinceproject';

    /** Strona całego projektu. */
    public const PROJECT = 'https://quinceproject.github.io/';

    /** Zgłoszenia w repozytorium tego modułu. */
    public const ISSUES = 'https://github.com/quinceproject/quince_gpsr/issues';
}
