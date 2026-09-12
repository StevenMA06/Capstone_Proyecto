<div class="table-scroll"><table class="users-table"><caption class="sr-only">Contratos del cliente</caption>
<thead><tr><th>Contrato</th><th>Plan</th><th>Vigencia</th><th>Importe contratado</th><th>Estado</th><th>Información</th></tr></thead><tbody>
@forelse($history as $item)
<tr><td>{{ $item->codigo }}</td><td>{{ $item->plan_nombre }}</td><td>{{ \Illuminate\Support\Carbon::parse($item->fecha_inicio)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($item->fecha_fin)->format('d/m/Y') }}</td>
<td>{{ number_format($item->precio_contratado, 2) }}</td><td>{{ ucfirst(mb_strtolower($item->estado)) }}</td>
<td><details><summary>Ver contrato {{ $item->codigo }}</summary><dl class="history-detail">
<div><dt>Duración</dt><dd>{{ $item->duracion_meses }} meses</dd></div><div><dt>Dirección del servicio</dt><dd>{{ $item->direccion_servicio }}</dd></div>
<div><dt>Fecha de registro</dt><dd>{{ \Illuminate\Support\Carbon::parse($item->fecha_registro)->format('d/m/Y H:i') }}</dd></div>
<div><dt>Observaciones</dt><dd>{{ $item->observaciones ?? '—' }}</dd></div>
</dl></details></td></tr>
@empty<tr><td colspan="6" class="empty-state">Este cliente aún no tiene contratos registrados.</td></tr>@endforelse
</tbody></table></div>
