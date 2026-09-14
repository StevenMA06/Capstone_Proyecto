<div class="table-scroll">
<table class="users-table inventory-table">
    <caption class="sr-only">Existencias actuales de {{ $kind === 'equipment' ? 'equipos' : 'materiales' }}</caption>
    <thead><tr><th scope="col">{{ $kind === 'equipment' ? 'Equipo' : 'Material' }}</th><th scope="col" class="stock-column">Stock actual</th></tr></thead>
    <tbody>
        @forelse($records as $item)
        <tr>
            <td><strong>{{ $item->nombre }}</strong><small class="table-subtitle">{{ $kind === 'equipment' ? implode(' · ', array_filter([$item->tipo, $item->marca, $item->modelo])) : $item->codigo }}</small></td>
            <td class="stock-column"><strong>{{ $kind === 'equipment' ? number_format($item->stock_actual) : rtrim(rtrim(number_format($item->stock_actual, 3, '.', ','), '0'), '.') }} {{ $kind === 'equipment' ? 'unid.' : $item->unidad_medida }}</strong>
                @if($item->stock_actual <= $item->stock_minimo)<span class="stock-warning">⚠ Stock mínimo</span>@endif
                <small class="table-subtitle">Mínimo: {{ $item->stock_minimo + 0 }}</small>
            </td>
        </tr>
        @empty<tr><td colspan="2" class="empty-state">No hay {{ $kind === 'equipment' ? 'equipos' : 'materiales' }} activos registrados.</td></tr>@endforelse
    </tbody>
</table>
</div>
