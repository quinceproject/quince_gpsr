<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\Controller\Admin;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Quince\Gpsr\Ui\PanelNavigation;
use Quince\Gpsr\Ui\SupportLinks;
use Symfony\Component\HttpFoundation\Response;

/**
 * Support: wsparcie projektu na Ko-fi i zgłoszenia do repozytorium modułu.
 *
 * Ten sam ekran co Support w silniku, w wariancie modułu samodzielnego: bez
 * listy modułów, z jednym przyciskiem do zgłoszeń GPSR. Do menu silnika go nie
 * zgłaszamy, bo silnik ma własną zakładkę Support.
 */
final class SupportController extends PrestaShopAdminController
{
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function indexAction(): Response
    {
        return $this->render('@Modules/quince_gpsr/views/templates/admin/support.html.twig', [
            'kofi' => SupportLinks::KOFI,
            'project' => SupportLinks::PROJECT,
            'issues' => SupportLinks::ISSUES,
            'quinceTabs' => (new PanelNavigation())->items(),
            'quinceRoute' => 'quince_gpsr_support',
            'layoutTitle' => 'Support',
        ]);
    }
}
