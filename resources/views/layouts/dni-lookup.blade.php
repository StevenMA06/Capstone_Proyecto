<div class="dni-lookup" data-dni-lookup data-url="{{ route($lookupModule.'.lookup') }}" data-name-field="{{ $lookupModule === 'clients' ? 'nombres_razon_social' : 'nombres' }}">
    <button class="secondary" type="button" data-dni-query hidden>Consultar DNI</button>
    <p class="dni-lookup-message" data-dni-message role="status" aria-live="polite"></p>
</div>
