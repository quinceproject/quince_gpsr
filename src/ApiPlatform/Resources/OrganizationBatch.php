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
use ApiPlatform\Metadata\Post;
use Quince\Gpsr\ApiPlatform\Processor\OrganizationBatchProcessor;

/**
 * Cały rejestr w jednym żądaniu.
 *
 * Katalog z kilkuset markami niesie kilkaset firm, a jedno żądanie na firmę
 * znaczy kilkaset uwierzytelnień i kilkaset zapisów po kolei. Stąd przesyłka
 * zbiorcza: narzędzie wysyła paczkę, sklep odpowiada, ile wierszy przyjął,
 * ile pominął i dlaczego.
 *
 * Odpowiedź niesie liczby, nie wgrane wiersze: nadawca wie, co wysłał, a nie
 * potrzebuje tego z powrotem.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/quince-gpsr/organizations/batch',
            processor: OrganizationBatchProcessor::class,
            extraProperties: ['scopes' => ['quince_gpsr_register_write']],
            openapiContext: [
                'summary' => 'Add or update many companies at once',
                'description' => 'Each entry is recognised by `code`, so sending the same package twice '
                    . 'changes nothing. Companies entered by hand in the shop are left alone and counted '
                    . 'as skipped. At most 500 entries per request.',
            ],
        ),
    ],
)]
class OrganizationBatch
{
    /** Identyfikator przesyłki. Nadawany po zapisie, żeby ślad w sklepie dało się wskazać. */
    #[ApiProperty(identifier: true)]
    public ?int $importId = null;

    /**
     * Firmy do zapisania, w tym samym kształcie co pojedynczy zasób.
     *
     * @var array<int,array<string,mixed>>
     */
    public array $organizations = [];

    /** Nazwa narzędzia, które wysyła paczkę. Trafia do śladu wgrywania w panelu. */
    public string $channel = '';

    /** Ile wierszy zapisano. */
    public int $saved = 0;

    /** Ile pominięto, bo wiersz wpisano w sklepie ręcznie. */
    public int $skipped = 0;

    /** Ile odrzucono: bez kodu albo bez nazwy. */
    public int $rejected = 0;
}
