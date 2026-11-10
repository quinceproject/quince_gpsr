<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\ApiPlatform\Resources;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Quince\Gpsr\ApiPlatform\Processor\OrganizationProcessor;
use Quince\Gpsr\ApiPlatform\Provider\OrganizationProvider;

/**
 * Firma z rejestru GPSR w Admin API.
 *
 * Wejście jest neutralne: nie zakłada żadnego konkretnego narzędzia po drugiej
 * stronie. Pola produktu i marki wgrywa się standardowym API produktu, bo
 * siedzą jako pola dodatkowe rdzenia; tutaj wchodzi wyłącznie rejestr firm,
 * którego rdzeń nie ma gdzie trzymać.
 *
 * Zapis jest idempotentny po `code`: to samo żądanie powtórzone dwa razy daje
 * ten sam wiersz, a nie dwie firmy. Dlatego kod musi być stabilny przez całe
 * życie wpisu, nawet gdy zmienia się nazwa i adres.
 *
 * Wiersz wpisany ręcznie w panelu zostaje nietknięty: pierwszy import inaczej
 * skasowałby pracę prowadzącego sklep, a on nie ma jak jej odzyskać.
 *
 * Dwa zakresy, bo to dwie różne rzeczy. Narzędzie, które tylko sprawdza stan
 * rejestru, nie powinno móc go nadpisać.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/quince-gpsr/organizations',
            provider: OrganizationProvider::class,
            extraProperties: ['scopes' => ['quince_gpsr_register_read']],
            openapiContext: [
                'summary' => 'Companies in the GPSR register',
                'description' => 'Every company the shop can show as manufacturer or responsible operator. '
                    . 'Filter with `retired`; page with `page` and `limit` (100 by default, 500 at most).',
            ],
        ),
        // Odczyt pojedynczej firmy. Potrzebny nie tylko sam dla siebie: bez
        // niego API Platform nie umie zbudować adresu zasobu zapisanego przez
        // `POST` i odpowiedź wywraca się mimo poprawnego zapisu.
        new Get(
            uriTemplate: '/quince-gpsr/organizations/{code}',
            provider: OrganizationProvider::class,
            extraProperties: ['scopes' => ['quince_gpsr_register_read']],
            openapiContext: ['summary' => 'One company by its code'],
        ),
        new Post(
            uriTemplate: '/quince-gpsr/organizations',
            processor: OrganizationProcessor::class,
            extraProperties: ['scopes' => ['quince_gpsr_register_write']],
            openapiContext: [
                'summary' => 'Add or update one company',
                'description' => 'Recognised by `code`, so sending the same company twice updates it '
                    . 'instead of duplicating it. A company entered by hand in the shop is left alone. '
                    . 'Use `/quince-gpsr/organizations/batch` to send many at once.',
            ],
        ),
    ],
)]
class Organization
{
    /** Stabilny identyfikator firmy. Po nim idzie zapis i po nim wskazują ją produkty. */
    #[ApiProperty(identifier: true)]
    public string $code = '';

    /**
     * Role firmy: producer, importer, authorized_representative,
     * fulfillment_service_provider, distributor. Nieznane wypadają po cichu,
     * żeby cudza rola wewnętrzna nie zrobiła z hurtownika podmiotu
     * odpowiedzialnego.
     *
     * @var array<int,string>
     */
    public array $roles = [];

    /** Nazwa albo znak towarowy: to, co widzi klient na karcie produktu. */
    public string $tradeName = '';

    /** Nazwa prawna, gdy różni się od handlowej. Nieobowiązkowa. */
    public string $legalName = '';

    public string $street = '';

    public string $postalCode = '';

    public string $city = '';

    /** Kraj w zapisie ISO 3166-1 alpha-2. Po nim liczy się, czy firma jest w Unii. */
    public string $country = '';

    /** Adres elektroniczny wymagany przez art. 19: e-mail albo `contactUrl`. */
    public string $email = '';

    public string $contactUrl = '';

    public string $phone = '';

    public string $taxId = '';

    /** Firma wycofana zostaje w rejestrze: produkty, które ją wskazują, dalej ją pokazują. */
    public bool $retired = false;

    /** `import` albo `manual`. Wiersza ręcznego wgrywanie nie rusza. */
    public string $source = 'import';
}
