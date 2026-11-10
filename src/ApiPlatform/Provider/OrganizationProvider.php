<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Quince\Gpsr\ApiPlatform\Resources\Organization;
use Quince\Gpsr\Storage\OrganizationRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Rejestr firm czytany przez Admin API.
 *
 * Te same wiersze co na ekranie w panelu, bo to ten sam rejestr. Osobne
 * zapytanie pisane pod API znaczyłoby, że sklep ma dwie wersje własnych danych.
 *
 * @implements ProviderInterface<Organization>
 */
final class OrganizationProvider implements ProviderInterface
{
    /** Ile wierszy oddajemy bez pytania i ile najwyżej na żądanie. */
    private const NA_STRONE = 100;
    private const NAJWYZEJ = 500;

    public function __construct(
        private readonly OrganizationRepository $rejestr,
        private readonly RequestStack $zadania
    ) {
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     *
     * @return array<int,Organization>|Organization|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Organization|null
    {
        // Operacja pojedyncza podaje kod w adresie; zbiorcza nie.
        if (isset($uriVariables['code'])) {
            $wiersz = $this->rejestr->byCode((string) $uriVariables['code']);

            return $wiersz !== null ? $this->zasob($wiersz) : null;
        }

        $zadanie = $this->zadania->getCurrentRequest();
        $pytanie = $zadanie !== null ? $zadanie->query->all() : [];

        $limit = min(self::NAJWYZEJ, max(1, (int) ($pytanie['limit'] ?? self::NA_STRONE)));
        $strona = max(1, (int) ($pytanie['page'] ?? 1));

        // Brak parametru znaczy „wszystkie": narzędzie, które odtwarza u siebie
        // stan sklepu, musi zobaczyć także wycofane, bo produkty je wskazują.
        $wycofane = array_key_exists('retired', $pytanie)
            ? filter_var($pytanie['retired'], FILTER_VALIDATE_BOOLEAN)
            : null;

        $wiersze = $this->rejestr->search('', $wycofane, $limit, ($strona - 1) * $limit);

        return array_map([$this, 'zasob'], $wiersze);
    }

    /** @param array<string,mixed> $wiersz */
    private function zasob(array $wiersz): Organization
    {
        $firma = new Organization();
        $firma->code = (string) $wiersz['code'];
        $firma->roles = $wiersz['roles'];
        $firma->tradeName = (string) $wiersz['trade_name'];
        $firma->legalName = (string) $wiersz['legal_name'];
        $firma->street = (string) $wiersz['street'];
        $firma->postalCode = (string) $wiersz['postal_code'];
        $firma->city = (string) $wiersz['city'];
        $firma->country = (string) $wiersz['country'];
        $firma->email = (string) $wiersz['email'];
        $firma->contactUrl = (string) $wiersz['contact_url'];
        $firma->phone = (string) $wiersz['phone'];
        $firma->taxId = (string) $wiersz['tax_id'];
        $firma->retired = (bool) $wiersz['retired'];
        $firma->source = (string) $wiersz['source'];

        return $firma;
    }
}
