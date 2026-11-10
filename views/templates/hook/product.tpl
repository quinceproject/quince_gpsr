{**
 * Quince GPSR — bezpieczeństwo produktu na karcie.
 *
 * Trzy rzeczy, których wymaga art. 19 rozporządzenia 2023/988: informacja
 * o bezpieczeństwie w języku kupującego, producent oraz podmiot odpowiedzialny
 * w Unii wraz z adresem pocztowym i elektronicznym.
 *
 * Układ jak w makiecie: ostrzeżenia jako zwykła treść, pod nimi tabela
 * specyfikacji, w której rola jest nagłówkiem wiersza. Producent i podmiot
 * odpowiedzialny bywają tą samą firmą i wtedy wypisujemy ją raz, w wierszu
 * o obu rolach: ten sam adres dwa razy pod rząd czyta się jak błąd strony.
 *}

{assign var=qgTenSam value=($qgProducent && $qgOdpowiedzialny && $qgProducent.code == $qgOdpowiedzialny.code)}

<div class="q-gpsr">

  {if $qgBezpieczenstwo}
    <div class="q-prose q-gpsr__safety">
      {$qgBezpieczenstwo nofilter}
    </div>
  {/if}

  {if $qgProducent || $qgOdpowiedzialny}
    <div class="q-table-wrap">
      <table class="q-spectable q-gpsr__table">
        <tbody>
          {if $qgProducent}
            <tr>
              <th scope="row">
                {if $qgTenSam}
                  {l s='Manufacturer and responsible operator in the EU' d='Modules.Quincegpsr.Shop'}
                {else}
                  {l s='Manufacturer' d='Modules.Quincegpsr.Shop'}
                {/if}
              </th>
              <td>{include file='module:quince_gpsr/views/templates/hook/_firm.tpl' qgFirma=$qgProducent}</td>
            </tr>
          {/if}

          {if $qgOdpowiedzialny && !$qgTenSam}
            <tr>
              <th scope="row">{l s='Responsible operator in the EU' d='Modules.Quincegpsr.Shop'}</th>
              <td>{include file='module:quince_gpsr/views/templates/hook/_firm.tpl' qgFirma=$qgOdpowiedzialny qgRola=true}</td>
            </tr>
          {/if}
        </tbody>
      </table>
    </div>
  {/if}

</div>
