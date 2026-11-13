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

use Context;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Quince\Gpsr\Gpsr\Audit;
use Quince\Gpsr\Gpsr\Eu;
use Quince\Gpsr\Gpsr\Roles;
use Quince\Gpsr\Storage\OrganizationRepository;
use Quince\Gpsr\Ui\PanelNavigation;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejestr firm GPSR: producenci, importerzy, przedstawiciele.
 *
 * Jeden ekran, bo jedna rzecz: lista firm i formularz jednej z nich.
 * Przypisanie firmy do produktu i do marki stoi w ich własnych formularzach,
 * jako pole dodatkowe rdzenia, więc tutaj go nie powtarzamy.
 */
final class OrganizationsController extends PrestaShopAdminController
{
    public function __construct(private readonly OrganizationRepository $rejestr)
    {
    }

    /** Ile firm mieści się na jednej stronie listy. */
    private const NA_STRONE = 50;

    /**
     * Ekran panelu razem z zakładkami widoków.
     *
     * Zakładki są częścią powłoki, nie ekranu, więc nie mogą zależeć od tego,
     * czy autor konkretnej metody o nich pamiętał.
     *
     * @param array<string,mixed> $parametry
     */
    private function renderPanel(string $szablon, string $trasa, array $parametry): Response
    {
        return $this->render($szablon, $parametry + [
            'quinceTabs' => (new PanelNavigation())->items(),
            'quinceRoute' => $trasa,
        ]);
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function indexAction(Request $request): Response
    {
        $fraza = trim((string) $request->query->get('q', ''));
        $wszystkich = $this->rejestr->countSearch($fraza);
        $stron = max(1, (int) ceil($wszystkich / self::NA_STRONE));
        $strona = min($stron, max(1, (int) $request->query->get('page', 1)));

        return $this->renderPanel('@Modules/quince_gpsr/views/templates/admin/organizations.html.twig', 'quince_gpsr_organizations', [
            'organizations' => $this->rejestr->search($fraza, null, self::NA_STRONE, ($strona - 1) * self::NA_STRONE),
            'phrase' => $fraza,
            'total' => $wszystkich,
            'page' => $strona,
            'pages' => $stron,
            'roles' => $this->roleChoices(),
            'lastImport' => $this->rejestr->lastImport(),
            'layoutTitle' => 'Product safety',
        ]);
    }

    /**
     * Przegląd braków: włączone produkty bez kompletu danych GPSR.
     */
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function productsAction(Request $request): Response
    {
        $idShop = (int) Context::getContext()->shop->id;
        $idLang = (int) Context::getContext()->language->id;

        $filtry = [
            'category' => (int) $request->query->get('category', 0),
            'brand' => (int) $request->query->get('brand', 0),
            'phrase' => trim((string) $request->query->get('q', '')),
        ];

        $wynik = (new Audit($this->rejestr))->incomplete($idShop, $idLang, $filtry);

        $stron = max(1, (int) ceil($wynik['incomplete'] / self::NA_STRONE));
        $strona = min($stron, max(1, (int) $request->query->get('page', 1)));

        return $this->renderPanel('@Modules/quince_gpsr/views/templates/admin/products.html.twig', 'quince_gpsr_products', [
            'products' => array_slice($wynik['rows'], ($strona - 1) * self::NA_STRONE, self::NA_STRONE),
            'checked' => $wynik['checked'],
            'incomplete' => $wynik['incomplete'],
            'page' => $strona,
            'pages' => $stron,
            'filters' => $filtry,
            'categories' => $this->categoryChoices($idLang),
            'brands' => $this->brandChoices(),
            'shopName' => (string) Context::getContext()->shop->name,
            'layoutTitle' => 'Products without GPSR data',
        ]);
    }

    /**
     * Jedna firma na osobnym widoku: dodanie albo edycja.
     */
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function editAction(Request $request, string $code = ''): Response
    {
        $firma = $code !== '' ? $this->rejestr->byCode($code) : null;

        if ($code !== '' && $firma === null) {
            return $this->redirectToRoute('quince_gpsr_organizations');
        }

        return $this->renderPanel('@Modules/quince_gpsr/views/templates/admin/organization.html.twig', 'quince_gpsr_organizations', [
            'edited' => $firma,
            'usage' => $firma !== null ? $this->rejestr->usageOf($code) : ['products' => 0, 'brands' => 0],
            'confirmDelete' => (bool) $request->query->get('confirm'),
            'roles' => $this->roleChoices(),
            'euCountries' => Eu::COUNTRIES,
            'layoutTitle' => $firma ? 'Edit company' : 'Add company',
        ]);
    }

    /**
     * Zapis jednej firmy.
     *
     * Wpis zapisany tutaj dostaje źródło „ręczne" i od tej pory wgrywanie
     * z zewnątrz go nie rusza. To jest właśnie sens ręcznej poprawki: ma
     * przetrwać najbliższy import.
     */
    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to do this.')]
    public function saveAction(Request $request): RedirectResponse
    {
        $dane = [
            'code' => trim((string) $request->request->get('code', '')),
            'roles' => (array) $request->request->all('roles'),
            'retired' => (bool) $request->request->get('retired'),
        ];

        foreach (['trade_name', 'legal_name', 'street', 'postal_code', 'city', 'country', 'email', 'contact_url', 'phone', 'tax_id'] as $pole) {
            $dane[$pole] = trim((string) $request->request->get($pole, ''));
        }

        if ($dane['code'] === '' || $dane['trade_name'] === '') {
            $this->addFlash('error', $this->trans('A company needs at least a code and a name.', [], 'Modules.Quincegpsr.Admin'));

            return $this->redirectToRoute('quince_gpsr_organizations');
        }

        // Adres elektroniczny jest wymogiem art. 19, nie naszym pomysłem: bez
        // niego oferta jest niezgodna, więc mówimy o tym przy zapisie, a nie
        // dopiero na karcie produktu.
        if ($dane['email'] === '' && $dane['contact_url'] === '') {
            $this->addFlash('warning', $this->trans(
                'Saved without an electronic address. GPSR requires an email or a contact form address for every company shown to customers.',
                [],
                'Modules.Quincegpsr.Admin'
            ));
        }

        if ($this->rejestr->save($dane, 'manual') > 0) {
            $this->addFlash('success', $this->trans('Saved.', [], 'Modules.Quincegpsr.Admin'));
        } else {
            $this->addFlash('error', $this->trans('Could not save the company.', [], 'Modules.Quincegpsr.Admin'));
        }

        return $this->redirectToRoute('quince_gpsr_organizations');
    }

    /**
     * Skasowanie firmy.
     *
     * Firma wskazywana przez produkty albo marki nie znika po jednym kliknięciu.
     * Skasowanie zabiera dane podmiotu odpowiedzialnego ze wszystkich tych kart
     * naraz, a sekcja po prostu przestaje się wypisywać, więc nikt tego nie
     * zauważy aż do kontroli. Pierwsze kliknięcie mówi, ilu produktów to dotyczy,
     * i proponuje wycofanie; skasowanie wymaga potwierdzenia.
     */
    #[AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message: 'You do not have permission to do this.')]
    public function deleteAction(Request $request, string $code): RedirectResponse
    {
        $uzycie = $this->rejestr->usageOf($code);
        $wskazan = $uzycie['products'] + $uzycie['brands'];

        if ($wskazan > 0 && !$request->request->get('confirm')) {
            $this->addFlash('warning', $this->trans(
                'This company is used by %products% products and %brands% brands. Deleting it takes the responsible operator off every one of those product pages. Retiring it instead keeps the data where it already is.',
                ['%products%' => $uzycie['products'], '%brands%' => $uzycie['brands']],
                'Modules.Quincegpsr.Admin'
            ));

            return $this->redirectToRoute('quince_gpsr_organization_edit', ['code' => $code, 'confirm' => 1]);
        }

        if ($this->rejestr->delete($code)) {
            $this->addFlash('success', $this->trans('Company deleted.', [], 'Modules.Quincegpsr.Admin'));
        }

        return $this->redirectToRoute('quince_gpsr_organizations');
    }

    /**
     * Kategorie do zawężenia listy braków.
     *
     * Płaska lista z pełną ścieżką, bo przy katalogu z zagnieżdżeniem sama
     * nazwa „Filtry" nie mówi, czy chodzi o filtry oleju, czy powietrza.
     *
     * @return array<int,array{id:int,name:string}>
     */
    private function categoryChoices(int $idLang): array
    {
        $wybor = [];

        foreach (\Category::getSimpleCategories($idLang) as $kategoria) {
            $wybor[] = ['id' => (int) $kategoria['id_category'], 'name' => (string) $kategoria['name']];
        }

        return $wybor;
    }

    /**
     * Marki do zawężenia listy braków.
     *
     * @return array<int,array{id:int,name:string}>
     */
    private function brandChoices(): array
    {
        $wybor = [];

        foreach (\Manufacturer::getManufacturers(false, 0, true) as $marka) {
            $wybor[] = ['id' => (int) $marka['id_manufacturer'], 'name' => (string) $marka['name']];
        }

        return $wybor;
    }

    /** @return array<string,string> kod roli => napis */
    private function roleChoices(): array
    {
        $wybor = [];

        foreach (Roles::labels() as $rola => $napis) {
            $wybor[$rola] = $this->trans($napis, [], 'Modules.Quincegpsr.Admin');
        }

        return $wybor;
    }
}
