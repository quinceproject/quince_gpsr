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

require_once __DIR__ . '/vendor/autoload.php';

use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinition;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyScope;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyType;
use Quince\Gpsr\Form\OrganizationChoiceType;
use Quince\Gpsr\Gpsr\Resolver;
use Quince\Gpsr\Gpsr\Roles;
use Quince\Gpsr\Storage\OrganizationRepository;
use Quince\Gpsr\Storage\Schema;
use Quince\Gpsr\Validator\OrganizationCode;

/**
 * Quince GPSR: bezpieczeństwo produktu i podmiot odpowiedzialny.
 *
 * Rozporządzenie 2023/988 wymaga, żeby oferta sprzedaży na odległość pokazywała
 * przed zakupem: producenta, podmiot odpowiedzialny w Unii wraz z adresem
 * pocztowym i elektronicznym, dane identyfikujące produkt oraz ostrzeżenia i
 * informacje o bezpieczeństwie w języku kupującego (art. 19 ust. 1).
 * PrestaShop nie ma na to nic: `ps_manufacturer` trzyma jeden adres opisowy,
 * bez adresu elektronicznego i bez podmiotu odpowiedzialnego.
 *
 * Moduł trzyma rejestr firm u siebie, a przypisania i tekst o bezpieczeństwie
 * jako pola dodatkowe rdzenia. Dzięki temu stoją w formularzu produktu obok
 * opisu i są zapisywalne zwykłym Admin API, bez własnego punktu odbioru.
 *
 * Wejście danych jest neutralne. Sklep przyjmuje je z dowolnego narzędzia:
 * PIM, arkusz, cudza integracja. Moduł nie zakłada żadnego konkretnego źródła.
 *
 * Działa samodzielnie; przy obecności `quince_engine` zgłasza się do niego i
 * oddaje treść w miejsce ustawione na stronie produktu, zamiast rysować ją
 * w standardowym hooku.
 */
class Quince_Gpsr extends Module
{
    /** Nasze miejsce na karcie produktu, gdy sklep stoi na silniku Quince. */
    public const HOOK_PRODUCT = 'displayQuinceGpsr';

    /**
     * Nazwy pól dodatkowych.
     *
     * Rdzeń robi z nich kolumny `<moduł>_<pole>`, czyli `quince_gpsr_safety`,
     * i tak samo nazywa je w Admin API, pod kluczem modułu. Stąd krótkie nazwy:
     * `gpsr_safety` dałoby `quince_gpsr_gpsr_safety`.
     */
    public const FIELD_SAFETY = 'safety';
    public const FIELD_MANUFACTURER = 'manufacturer';
    public const FIELD_RESPONSIBLE = 'responsible';

    private const DOMAIN_ADMIN = 'Modules.Quincegpsr.Admin';
    private const DOMAIN_SHOP = 'Modules.Quincegpsr.Shop';

    /** Zakładka-kontener w menu, gdy moduł stoi bez silnika Quince. */
    private const TAB_PARENT = 'AdminQuinceGpsr';

    /** Ekran modułu. */
    private const TAB_SCREEN = 'AdminQuinceGpsrOrganizations';

    public function __construct()
    {
        $this->name = 'quince_gpsr';
        $this->tab = 'administration';
        $this->version = '0.1.2';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.2.0', 'max' => '9.99.99'];

        parent::__construct();

        $this->displayName = $this->trans('Quince GPSR', [], self::DOMAIN_ADMIN);
        $this->description = $this->trans(
            'Product safety and the responsible economic operator on the product page, as the EU General Product Safety Regulation requires. Companies live in one register; products and brands point to them.',
            [],
            self::DOMAIN_ADMIN
        );
        $this->confirmUninstall = $this->trans(
            'Uninstalling deletes the company register, the safety texts and the assignments. Export them first if another tool does not hold them.',
            [],
            self::DOMAIN_ADMIN
        );
    }

    /**
     * Instalacja obejmuje wszystkie sklepy instalacji, nie tylko ten z kontekstu.
     *
     * Instalacja z konsoli jedzie w kontekście sklepu domyślnego, więc bez jawnej
     * listy moduł ląduje tylko w nim. Na instalacji wielosklepowej sekcja
     * bezpieczeństwa nie pojawiłaby się wtedy w pozostałych sklepach, a obowiązek
     * z art. 19 dotyczy każdej oferty, nie tylko pierwszej.
     */
    public function install(): bool
    {
        $sklepy = Shop::getShops(true, null, true);

        return parent::install()
            && Schema::install()
            && $this->createHook()
            && $this->registerHook($this->hooks(), $sklepy)
            && $this->installForShops($sklepy)
            && $this->installModuleGroups()
            && $this->installFields()
            && $this->installTabs();
    }

    /**
     * Wpisy w `ps_module_shop` dla każdego sklepu.
     *
     * `parent::install()` dokłada je tylko dla sklepów z kontekstu.
     *
     * @param array<int,int> $sklepy
     */
    private function installForShops(array $sklepy): bool
    {
        foreach ($sklepy as $idShop) {
            Db::getInstance()->insert(
                'module_shop',
                ['id_module' => (int) $this->id, 'id_shop' => (int) $idShop, 'enable_device' => 7],
                false,
                true,
                Db::INSERT_IGNORE
            );
        }

        return true;
    }

    public function uninstall(): bool
    {
        $this->uninstallTabs();
        $this->removeFields(true);

        return Schema::uninstall() && parent::uninstall();
    }

    /** @return string[] */
    private function hooks(): array
    {
        return [
            'actionQuinceRegisterFeature',
            self::HOOK_PRODUCT,
            'displayProductAdditionalInfo',
            'actionFrontControllerSetMedia',
        ];
    }

    /**
     * Własny hook musi istnieć w tabeli, zanim moduł się na niego zarejestruje.
     * PrestaShop po cichu pomija rejestrację na hook, którego nie zna.
     */
    private function createHook(): bool
    {
        if (Hook::getIdByName(self::HOOK_PRODUCT)) {
            return true;
        }

        $hook = new Hook();
        $hook->name = self::HOOK_PRODUCT;
        $hook->title = 'Quince: bezpieczeństwo produktu (GPSR)';
        $hook->description = 'Producent, podmiot odpowiedzialny i informacje o bezpieczeństwie na karcie produktu.';
        $hook->position = false;

        return (bool) $hook->add();
    }

    /**
     * Wpisy w `ps_module_group`.
     *
     * Bez nich hook frontendowy w ogóle nie odpala: PrestaShop dokłada do listy
     * modułów warunek na grupę klienta i moduł bez wpisów znika z wyników.
     */
    private function installModuleGroups(): bool
    {
        $grupy = Group::getGroups((int) Context::getContext()->language->id, true);

        foreach (Shop::getShops(true, null, true) as $idShop) {
            foreach ($grupy as $grupa) {
                Db::getInstance()->insert(
                    'module_group',
                    [
                        'id_module' => (int) $this->id,
                        'id_shop' => (int) $idShop,
                        'id_group' => (int) $grupa['id_group'],
                    ],
                    false,
                    true,
                    Db::INSERT_IGNORE
                );
            }
        }

        return true;
    }

    /**
     * Pola dodatkowe rdzenia: przy produkcie i przy marce.
     *
     * Tekst o bezpieczeństwie jest językowy, bo art. 19 wymaga go w języku
     * kupującego. Przypisania językowe nie są: adres producenta brzmi tak samo
     * po polsku i po niemiecku. Przy marce zakres musi być wspólny także
     * dlatego, że `ps_manufacturer_lang` nie ma kolumny `id_shop`, a rdzeniowy
     * zapis pola językowego wpisuje ją bezwarunkowo i kończy się błędem SQL.
     */
    private function installFields(): bool
    {
        foreach ($this->fieldDefinitions() as $definicja) {
            if ($this->registerExtraProperty($definicja) === false) {
                return false;
            }
        }

        return true;
    }

    private function removeFields(bool $dropData): void
    {
        foreach ($this->fieldDefinitions() as $definicja) {
            $this->unregisterExtraProperty($definicja, $dropData);
        }
    }

    /** @return ExtraPropertyDefinition[] */
    private function fieldDefinitions(): array
    {
        return [
            new ExtraPropertyDefinition(
                entityName: 'product',
                propertyName: self::FIELD_SAFETY,
                type: ExtraPropertyType::HTML,
                scope: ExtraPropertyScope::LANG,
                displayFront: false,
                associatedForms: ['product'],
                associatedApis: ['/products', '/products/{productId}'],
                labelWording: 'Safety information',
                labelDomain: self::DOMAIN_ADMIN,
                descriptionWording: 'Warnings and safety information shown to the customer before the purchase. Required by the General Product Safety Regulation.',
                descriptionDomain: self::DOMAIN_ADMIN
            ),
            new ExtraPropertyDefinition(
                entityName: 'product',
                propertyName: self::FIELD_MANUFACTURER,
                type: ExtraPropertyType::STRING,
                scope: ExtraPropertyScope::COMMON,
                size: 64,
                associatedForms: ['product'],
                associatedApis: ['/products', '/products/{productId}'],
                formType: OrganizationChoiceType::class,
                constraints: [new OrganizationCode()],
                labelWording: 'Manufacturer (override)',
                labelDomain: self::DOMAIN_ADMIN,
                descriptionWording: 'Code of a company from the GPSR register. Empty means the brand decides.',
                descriptionDomain: self::DOMAIN_ADMIN
            ),
            new ExtraPropertyDefinition(
                entityName: 'product',
                propertyName: self::FIELD_RESPONSIBLE,
                type: ExtraPropertyType::STRING,
                scope: ExtraPropertyScope::COMMON,
                size: 64,
                associatedForms: ['product'],
                associatedApis: ['/products', '/products/{productId}'],
                formType: OrganizationChoiceType::class,
                formOptions: ['responsible_only' => true],
                constraints: [new OrganizationCode(['responsibleOnly' => true])],
                labelWording: 'Responsible operator (override)',
                labelDomain: self::DOMAIN_ADMIN,
                descriptionWording: 'Code of a company from the GPSR register. Empty means the brand decides, and a manufacturer based in the EU takes the role itself.',
                descriptionDomain: self::DOMAIN_ADMIN
            ),
            new ExtraPropertyDefinition(
                entityName: 'manufacturer',
                propertyName: self::FIELD_MANUFACTURER,
                type: ExtraPropertyType::STRING,
                scope: ExtraPropertyScope::COMMON,
                size: 64,
                associatedForms: ['manufacturer'],
                associatedApis: ['/manufacturers', '/manufacturers/{manufacturerId}'],
                formType: OrganizationChoiceType::class,
                formOptions: ['placeholder' => 'Not set'],
                constraints: [new OrganizationCode()],
                labelWording: 'Manufacturer for GPSR',
                labelDomain: self::DOMAIN_ADMIN,
                descriptionWording: 'Code of a company from the GPSR register. Products of this brand inherit it.',
                descriptionDomain: self::DOMAIN_ADMIN
            ),
            new ExtraPropertyDefinition(
                entityName: 'manufacturer',
                propertyName: self::FIELD_RESPONSIBLE,
                type: ExtraPropertyType::STRING,
                scope: ExtraPropertyScope::COMMON,
                size: 64,
                associatedForms: ['manufacturer'],
                associatedApis: ['/manufacturers', '/manufacturers/{manufacturerId}'],
                formType: OrganizationChoiceType::class,
                formOptions: ['responsible_only' => true, 'placeholder' => 'Not set'],
                constraints: [new OrganizationCode(['responsibleOnly' => true])],
                labelWording: 'Responsible operator for GPSR',
                labelDomain: self::DOMAIN_ADMIN,
                descriptionWording: 'Code of a company from the GPSR register. Products of this brand inherit it.',
                descriptionDomain: self::DOMAIN_ADMIN
            ),
        ];
    }

    /**
     * Zakładki panelu.
     *
     * Ekran wchodzi pod menu Quince, gdy silnik stoi w sklepie, a bez niego
     * dostaje własną pozycję w menu. Moduł ma działać w obu sytuacjach, więc
     * rodzic nie może być wpisany na sztywno.
     */
    private function installTabs(): bool
    {
        $rodzic = (int) Tab::getIdFromClassName('AdminQuinceProject');

        if ($rodzic <= 0) {
            $rodzic = $this->createTab(self::TAB_PARENT, 'Quince GPSR', 0);
        }

        return $rodzic > 0 && $this->createTab(self::TAB_SCREEN, 'Product safety', $rodzic, 'quince_gpsr_organizations') > 0;
    }

    private function createTab(string $klasa, string $nazwa, int $rodzic, string $trasa = ''): int
    {
        $istniejaca = (int) Tab::getIdFromClassName($klasa);

        if ($istniejaca > 0) {
            return $istniejaca;
        }

        $zakladka = new Tab();
        $zakladka->class_name = $klasa;
        $zakladka->module = $this->name;
        $zakladka->id_parent = $rodzic;
        $zakladka->active = true;

        if ($trasa !== '') {
            $zakladka->route_name = $trasa;
        }

        $zakladka->wording = $nazwa;
        $zakladka->wording_domain = self::DOMAIN_ADMIN;

        foreach (Language::getLanguages(false) as $jezyk) {
            $zakladka->name[(int) $jezyk['id_lang']] = $nazwa;
        }

        return $zakladka->add() ? (int) $zakladka->id : 0;
    }

    private function uninstallTabs(): void
    {
        foreach ([self::TAB_SCREEN, self::TAB_PARENT] as $klasa) {
            $id = (int) Tab::getIdFromClassName($klasa);

            if ($id > 0) {
                (new Tab($id))->delete();
            }
        }
    }

    /**
     * Zgłoszenie do silnika: co dostarczamy i gdzie są nasze ustawienia.
     *
     * @return array<int,array<string,string>>
     */
    public function hookActionQuinceRegisterFeature(): array
    {
        return [[
            'type' => 'gpsr',
            'id' => $this->name,
            'label' => $this->trans('Product safety', [], self::DOMAIN_ADMIN),
            'route' => 'quince_gpsr_organizations',
        ]];
    }

    /**
     * Styl sekcji, tylko na karcie produktu.
     *
     * Arkusz bierze tokeny motywu, a gdy ich nie ma, wartości zapasowe: moduł
     * ma wyglądać znośnie także w sklepie stojącym na cudzym motywie.
     */
    public function hookActionFrontControllerSetMedia(): void
    {
        if ($this->context->controller instanceof ProductController) {
            $this->context->controller->registerStylesheet(
                'quince-gpsr',
                'modules/' . $this->name . '/views/css/front.css',
                ['media' => 'all', 'priority' => 150]
            );
        }
    }

    /**
     * Karta produktu w silniku Quince: treść ląduje tam, gdzie ustawił ją sklep.
     *
     * @param array<string,mixed> $params
     */
    public function hookDisplayQuinceGpsr(array $params): string
    {
        return $this->section($this->productId($params['product'] ?? null));
    }

    /**
     * Karta produktu bez silnika Quince.
     *
     * Gdy silnik jest zainstalowany, treść idzie jego hookiem w miejsce wybrane
     * w ustawieniach strony produktu. Tutaj wypisalibyśmy ją drugi raz.
     *
     * @param array<string,mixed> $params
     */
    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        // Pytamy o silnik włączony W TYM SKLEPIE, nie o obecny w instalacji.
        // Przy wielosklepowości jeden sklep bywa na Quince, a drugi na cudzym
        // motywie bez silnika: przy pytaniu o samą instalację sekcja znikałaby
        // w tym drugim, bo silnik gdzieś tam jest, tylko nie tutaj.
        if (Module::isEnabled('quince_engine')) {
            return '';
        }

        return $this->section($this->productId($params['product'] ?? null));
    }

    /** Wspólna treść obu hooków. */
    private function section(int $idProduct): string
    {
        if ($idProduct <= 0) {
            return '';
        }

        $resolver = new Resolver(new OrganizationRepository());
        $dane = $resolver->forProduct(
            $idProduct,
            (int) $this->context->language->id,
            (int) $this->context->shop->id
        );

        if ($dane['manufacturer'] === null && $dane['responsible'] === null && $dane['safety'] === '') {
            return '';
        }

        $etykiety = [];

        foreach (Roles::labels() as $rola => $napis) {
            $etykiety[$rola] = $this->trans($napis, [], self::DOMAIN_SHOP);
        }

        $this->context->smarty->assign([
            'qgProducent' => $dane['manufacturer'],
            'qgOdpowiedzialny' => $dane['responsible'],
            'qgZReguly' => $dane['responsible_inherited'],
            'qgBezpieczenstwo' => $dane['safety'],
            'qgRole' => $etykiety,
        ]);

        return $this->fetch('module:quince_gpsr/views/templates/hook/product.tpl');
    }

    /** Identyfikator produktu z parametru hooka: tablicy z prezentera albo obiektu. */
    private function productId($produkt): int
    {
        if ($produkt instanceof Product) {
            return (int) $produkt->id;
        }

        if (is_array($produkt) || $produkt instanceof ArrayAccess) {
            return (int) ($produkt['id_product'] ?? 0);
        }

        return (int) Tools::getValue('id_product');
    }

    /** Przycisk „Konfiguruj" w menedżerze modułów prowadzi na ekran rejestru. */
    public function getContent(): void
    {
        Tools::redirectAdmin($this->get('router')->generate('quince_gpsr_organizations'));
    }
}
