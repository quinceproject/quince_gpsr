<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Wartość pola musi wskazywać firmę z rejestru GPSR.
 *
 * Formularz w panelu pilnuje tego sam, bo podaje listę do wyboru. Zapis przez
 * Admin API listy nie ma i bez tego ograniczenia dałoby się przypisać produktowi
 * kod firmy, której w sklepie nie ma. Karta produktu nie wypisałaby wtedy nic,
 * a narzędzie wgrywające dostałoby odpowiedź, że zapis się udał — czyli cicha
 * utrata danych, najgorszy możliwy wariant przy obowiązku prawnym.
 *
 * `responsibleOnly` zawęża do ról, które wolno postawić w polu podmiotu
 * odpowiedzialnego. Dystrybutor nim nie bywa: sama sprzedaż towaru
 * wprowadzonego już do obrotu w Unii nikogo nim nie czyni.
 */
final class OrganizationCode extends Constraint
{
    public string $message = 'Company "%code%" is not in the GPSR register.';

    public string $roleMessage = 'Company "%code%" cannot be the responsible operator: none of its roles allows it.';

    /** Czy wartość ma wskazywać firmę, która może być podmiotem odpowiedzialnym. */
    public bool $responsibleOnly = false;
}
