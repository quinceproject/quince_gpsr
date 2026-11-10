<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\Gpsr;

/**
 * Role podmiotów z rozporządzenia 2023/988.
 *
 * Podmiotem odpowiedzialnym może być jeden z czterech (art. 16 ust. 1 GPSR,
 * odsyłający do art. 4 ust. 2 rozporządzenia 2019/1020), w kolejności
 * pierwszeństwa: producent z siedzibą w UE, importer, upoważniony
 * przedstawiciel, dostawca usług realizacji zamówień.
 *
 * Dystrybutor jest w rejestrze, ale podmiotem odpowiedzialnym nie bywa: sama
 * sprzedaż towaru wprowadzonego już do obrotu w Unii nikogo nim nie czyni.
 * Dlatego stoi osobno i nie wolno go przypisać w polu podmiotu.
 */
final class Roles
{
    public const PRODUCER = 'producer';
    public const IMPORTER = 'importer';
    public const AUTHORIZED_REPRESENTATIVE = 'authorized_representative';
    public const FULFILLMENT_SERVICE_PROVIDER = 'fulfillment_service_provider';
    public const DISTRIBUTOR = 'distributor';

    /** Role, które wolno postawić w polu podmiotu odpowiedzialnego. */
    public const RESPONSIBLE = [
        self::PRODUCER,
        self::IMPORTER,
        self::AUTHORIZED_REPRESENTATIVE,
        self::FULFILLMENT_SERVICE_PROVIDER,
    ];

    /** Wszystkie role, jakie może nieść wpis rejestru. */
    public const ALL = [
        self::PRODUCER,
        self::IMPORTER,
        self::AUTHORIZED_REPRESENTATIVE,
        self::FULFILLMENT_SERVICE_PROVIDER,
        self::DISTRIBUTOR,
    ];

    /**
     * Napisy do panelu i na kartę produktu.
     *
     * Tłumaczenie robi moduł przez `trans()`; tutaj stoi angielski źródłowy.
     *
     * @return array<string,string>
     */
    public static function labels(): array
    {
        return [
            self::PRODUCER => 'Manufacturer',
            self::IMPORTER => 'Importer',
            self::AUTHORIZED_REPRESENTATIVE => 'Authorised representative',
            self::FULFILLMENT_SERVICE_PROVIDER => 'Fulfilment service provider',
            self::DISTRIBUTOR => 'Distributor',
        ];
    }

    /**
     * Role przysłane z zewnątrz: zostają znane, reszta wypada.
     *
     * Narzędzie zasilające ma własne role, których sklep nie zna (hurtownik,
     * wykonawca montażu). Cicha literówka nie może zrobić z hurtownika podmiotu
     * odpowiedzialnego, więc nieznane wartości odrzucamy, zamiast je zapisywać.
     *
     * @param array<int,string>|string|null $role
     *
     * @return array<int,string>
     */
    public static function clean($role): array
    {
        $lista = is_array($role) ? $role : preg_split('/\s*,\s*/', (string) $role, -1, PREG_SPLIT_NO_EMPTY);
        $czyste = [];

        foreach ($lista ?: [] as $wartosc) {
            $wartosc = strtolower(trim((string) $wartosc));

            if (in_array($wartosc, self::ALL, true) && !in_array($wartosc, $czyste, true)) {
                $czyste[] = $wartosc;
            }
        }

        return $czyste;
    }

    /** Czy wpis z takimi rolami wolno wskazać jako podmiot odpowiedzialny. */
    public static function canBeResponsible(array $role): bool
    {
        return array_intersect($role, self::RESPONSIBLE) !== [];
    }
}
