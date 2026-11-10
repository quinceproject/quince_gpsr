{**
 * Jedna firma w komórce tabeli: nazwa, rola z rejestru, adres, kontakt.
 *
 * Każda dana w osobnej linii, bo sklejone w akapit nie dają się przeczytać
 * wzrokiem, a tego adresu szuka się wzrokiem, nie czyta od początku.
 *
 * Nazwa prawna wychodzi tylko wtedy, gdy różni się od handlowej, a rola
 * z rejestru („Importer") tylko przy podmiocie odpowiedzialnym: przy
 * producencie powtarzałaby nagłówek wiersza.
 *}

<span class="q-gpsr__name">{$qgFirma.trade_name|escape:'html':'UTF-8'}</span>

{if $qgFirma.legal_name && $qgFirma.legal_name != $qgFirma.trade_name}
  <span class="q-gpsr__legal">{$qgFirma.legal_name|escape:'html':'UTF-8'}</span>
{/if}

{if isset($qgRola) && $qgRola}
  {foreach from=$qgFirma.roles item=qgKod name=role}
    {if isset($qgRole[$qgKod])}
      <span class="q-gpsr__meta">{$qgRole[$qgKod]|escape:'html':'UTF-8'}</span>
      {break}
    {/if}
  {/foreach}
{/if}

<span class="q-gpsr__address">
  {if $qgFirma.street}{$qgFirma.street|escape:'html':'UTF-8'}, {/if}
  {$qgFirma.postal_code|escape:'html':'UTF-8'} {$qgFirma.city|escape:'html':'UTF-8'}{if $qgFirma.country}, {$qgFirma.country|escape:'html':'UTF-8'}{/if}
</span>

{if $qgFirma.email || $qgFirma.contact_url || $qgFirma.phone}
  <span class="q-gpsr__contact">
    {if $qgFirma.email}
      <a href="mailto:{$qgFirma.email|escape:'html':'UTF-8'}">{$qgFirma.email|escape:'html':'UTF-8'}</a>
    {elseif $qgFirma.contact_url}
      <a href="{$qgFirma.contact_url|escape:'html':'UTF-8'}" rel="nofollow">{l s='Contact form' d='Modules.Quincegpsr.Shop'}</a>
    {/if}

    {if ($qgFirma.email || $qgFirma.contact_url) && $qgFirma.phone} · {/if}
    {if $qgFirma.phone}{$qgFirma.phone|escape:'html':'UTF-8'}{/if}
  </span>
{/if}
