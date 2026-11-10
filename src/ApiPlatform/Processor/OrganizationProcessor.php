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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use PrestaShopException;
use Quince\Gpsr\ApiPlatform\Resources\Organization;
use Quince\Gpsr\Storage\OrganizationRepository;

/**
 * Zapis jednej firmy z Admin API.
 *
 * Rozpoznanie po `code`, więc to samo żądanie powtórzone dwa razy daje ten sam
 * wiersz. Narzędzie zasilające nie musi pamiętać, co już wysłało, ani pytać
 * sklepu o stan przed wysyłką.
 *
 * @implements ProcessorInterface<Organization, Organization>
 */
final class OrganizationProcessor implements ProcessorInterface
{
    public function __construct(private readonly OrganizationRepository $rejestr)
    {
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Organization
    {
        if (!$data instanceof Organization) {
            throw new PrestaShopException('Unexpected payload for the GPSR register.');
        }

        if (trim($data->code) === '' || trim($data->tradeName) === '') {
            throw new PrestaShopException('A company needs a code and a name.');
        }

        $zapisane = $this->rejestr->save(Mapper::naWiersz($data), 'import');

        // Zero znaczy: wiersz wpisano w sklepie ręcznie i wgrywanie go nie rusza.
        // To nie jest błąd, więc odpowiadamy tym, co w sklepie naprawdę stoi.
        $wiersz = $this->rejestr->byCode($data->code);

        if ($wiersz !== null && $zapisane === 0) {
            $data->source = (string) $wiersz['source'];
        }

        return $data;
    }
}
