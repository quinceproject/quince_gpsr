<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\ApiPlatform\Processor;

use Quince\Gpsr\ApiPlatform\Resources\Organization;

/**
 * Przełożenie kształtu z API na kształt rejestru.
 *
 * Admin API mówi `tradeName`, tabela mówi `trade_name`. Przekład stoi w jednym
 * miejscu, żeby zapis pojedynczy i zbiorczy nie rozjechały się o literę.
 */
final class Mapper
{
    /** @return array<string,mixed> */
    public static function naWiersz(Organization $firma): array
    {
        return [
            'code' => trim($firma->code),
            'roles' => $firma->roles,
            'trade_name' => $firma->tradeName,
            'legal_name' => $firma->legalName,
            'street' => $firma->street,
            'postal_code' => $firma->postalCode,
            'city' => $firma->city,
            'country' => $firma->country,
            'email' => $firma->email,
            'contact_url' => $firma->contactUrl,
            'phone' => $firma->phone,
            'tax_id' => $firma->taxId,
            'retired' => $firma->retired,
        ];
    }

    /**
     * Wpis z przesyłki zbiorczej: ta sama mapa, tylko ze zwykłej tablicy.
     *
     * @param array<string,mixed> $wpis
     *
     * @return array<string,mixed>
     */
    public static function zTablicy(array $wpis): array
    {
        $firma = new Organization();
        $firma->code = (string) ($wpis['code'] ?? '');
        $firma->roles = is_array($wpis['roles'] ?? null) ? $wpis['roles'] : [];
        $firma->tradeName = (string) ($wpis['tradeName'] ?? '');
        $firma->legalName = (string) ($wpis['legalName'] ?? '');
        $firma->street = (string) ($wpis['street'] ?? '');
        $firma->postalCode = (string) ($wpis['postalCode'] ?? '');
        $firma->city = (string) ($wpis['city'] ?? '');
        $firma->country = (string) ($wpis['country'] ?? '');
        $firma->email = (string) ($wpis['email'] ?? '');
        $firma->contactUrl = (string) ($wpis['contactUrl'] ?? '');
        $firma->phone = (string) ($wpis['phone'] ?? '');
        $firma->taxId = (string) ($wpis['taxId'] ?? '');
        $firma->retired = (bool) ($wpis['retired'] ?? false);

        return self::naWiersz($firma);
    }
}
