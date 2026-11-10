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

use Quince\Gpsr\Gpsr\Roles;
use Quince\Gpsr\Storage\OrganizationRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Sprawdzenie kodu firmy przy zapisie pola dodatkowego.
 *
 * Bez wstrzykiwania zależności i to celowo, tak samo jak w polu wyboru firmy:
 * rdzeń sprawdza i zapisuje definicje pól w trakcie instalacji modułu, kiedy
 * kontener usług jeszcze nic o tym module nie wie. Symfony potrafi utworzyć
 * sprawdzacz spoza kontenera, ale tylko bez argumentów w konstruktorze.
 */
final class OrganizationCodeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof OrganizationCode) {
            throw new UnexpectedTypeException($constraint, OrganizationCode::class);
        }

        // Puste pole znaczy „weź z marki", a przy marce „nieustawione".
        // To jest stan dozwolony, nie brak do zgłoszenia.
        if ($value === null || $value === '') {
            return;
        }

        $kod = trim((string) $value);
        $firma = (new OrganizationRepository())->byCode($kod);

        if ($firma === null) {
            // Domyślnie Symfony tłumaczy komunikat w domenie `validators`,
            // a przekład leży w domenie modułu.
            $this->context->buildViolation($constraint->message)
                ->setParameter('%code%', $kod)
                ->setTranslationDomain('Modules.Quincegpsr.Admin')
                ->addViolation();

            return;
        }

        // Wycofania tutaj nie sprawdzamy. Lista wyboru w panelu i tak nie poda
        // firmy wycofanej, a twarde odrzucenie zapisu blokowałoby zmianę ceny
        // produktu, którego firma została w międzyczasie wycofana — kara za
        // coś, czego edytujący nie zrobił.
        if ($constraint->responsibleOnly && !Roles::canBeResponsible($firma['roles'])) {
            $this->context->buildViolation($constraint->roleMessage)
                ->setParameter('%code%', $kod)
                ->addViolation();
        }
    }
}
