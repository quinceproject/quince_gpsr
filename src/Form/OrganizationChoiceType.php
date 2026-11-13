<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Gpsr\Form;

use Quince\Gpsr\Storage\OrganizationRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Wybór firmy z rejestru GPSR w formularzu produktu i marki.
 *
 * Pole dodatkowe rdzenia trzyma sam kod firmy i bez tego typu byłoby zwykłym
 * polem tekstowym: prowadzący sklep musiałby przepisywać kod z pamięci, a
 * literówka cicho zabrałaby kartę produktu z danymi podmiotu.
 *
 * Lista powstaje przy rysowaniu formularza, nie przy rejestracji pola. Typ
 * `choice` rdzenia to ENUM zapisany w schemacie tabeli, więc firma dodana po
 * instalacji nigdy by się w nim nie pojawiła.
 */
final class OrganizationChoiceType extends AbstractType
{
    /**
     * Bez wstrzykiwania zależności i to celowo.
     *
     * Rdzeń sprawdza definicję pola dodatkowego budując próbne pole tym typem,
     * a robi to w trakcie instalacji modułu, kiedy kontener usług jeszcze nic
     * o tym module nie wie. Typ z argumentem w konstruktorze nie powstaje wtedy
     * wcale i rdzeń odrzuca całą definicję. Symfony potrafi zbudować typ spoza
     * kontenera, ale tylko wtedy, gdy da się go utworzyć bez argumentów.
     */
    private function rejestr(): OrganizationRepository
    {
        return new OrganizationRepository();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // `responsible_only` zawęża listę do ról, które wolno postawić w polu
        // podmiotu odpowiedzialnego: dystrybutor nim nie bywa.
        $resolver->setDefined(['responsible_only']);
        $resolver->setAllowedTypes('responsible_only', 'bool');
        $resolver->setDefaults([
            'responsible_only' => false,
            'placeholder' => 'Inherit from the brand',
            // Podpowiedź tłumaczymy w domenie modułu; nazw firm z rejestru nie.
            'translation_domain' => 'Modules.Quincegpsr.Admin',
            'choice_translation_domain' => false,
            'required' => false,
            'choices' => [],
        ]);

        $resolver->setNormalizer('choices', function ($options, $wartosc) {
            return $this->rejestr()->choices((bool) ($options['responsible_only'] ?? false));
        });
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
