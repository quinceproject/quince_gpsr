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
use Quince\Gpsr\ApiPlatform\Resources\OrganizationBatch;
use Quince\Gpsr\Storage\OrganizationRepository;

/**
 * Zapis całej paczki firm.
 *
 * Wiersz bez kodu albo bez nazwy odpada, a reszta paczki wchodzi: jedna
 * literówka w eksporcie nie może zablokować wgrania pozostałych kilkuset firm.
 * Odrzucone liczymy i zapisujemy w śladzie wgrywania, żeby nie zniknęły po
 * cichu.
 *
 * @implements ProcessorInterface<OrganizationBatch, OrganizationBatch>
 */
final class OrganizationBatchProcessor implements ProcessorInterface
{
    /** Ile wierszy przyjmujemy w jednym żądaniu. */
    private const NAJWYZEJ = 500;

    public function __construct(private readonly OrganizationRepository $rejestr)
    {
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationBatch
    {
        if (!$data instanceof OrganizationBatch) {
            throw new PrestaShopException('Unexpected payload for the GPSR register.');
        }

        if (count($data->organizations) > self::NAJWYZEJ) {
            throw new PrestaShopException(sprintf('At most %d companies per request.', self::NAJWYZEJ));
        }

        $data->saved = 0;
        $data->skipped = 0;
        $data->rejected = 0;
        $odrzucone = [];

        foreach ($data->organizations as $wpis) {
            if (!is_array($wpis)) {
                ++$data->rejected;

                continue;
            }

            $wiersz = Mapper::zTablicy($wpis);

            if ($wiersz['code'] === '' || trim((string) $wiersz['trade_name']) === '') {
                ++$data->rejected;
                $odrzucone[] = $wiersz['code'] !== '' ? $wiersz['code'] : '(bez kodu)';

                continue;
            }

            if ($this->rejestr->save($wiersz, 'import') > 0) {
                ++$data->saved;

                continue;
            }

            // Zero po poprawnym wierszu znaczy: ten wpis prowadzi sklep ręcznie.
            ++$data->skipped;
        }

        $stan = $data->rejected > 0 ? 'partial' : 'ok';
        $kanal = $data->channel !== '' ? $data->channel : 'api';

        $this->rejestr->logImport(
            $kanal,
            $data->saved,
            $data->rejected,
            $stan,
            $odrzucone === [] ? '' : 'odrzucone: ' . implode(', ', array_slice($odrzucone, 0, 20))
        );

        $ostatni = $this->rejestr->lastImport();
        $data->importId = $ostatni !== null ? (int) $ostatni['id_import'] : null;

        return $data;
    }
}
