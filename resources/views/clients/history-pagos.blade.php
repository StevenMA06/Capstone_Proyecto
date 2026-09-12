<div class="table-scroll"><table class="users-table"><caption class="sr-only">Pagos del cliente</caption>
<thead><tr><th>Pago / Contrato</th><th>Periodo</th><th>Vencimiento</th><th>Fecha de pago</th><th>Monto</th><th>Estado / Método</th></tr></thead><tbody>
@forelse($history as $item)
<tr><td>{{ $item->codigo }}<br><span class="muted">{{ $item->contrato_codigo }}</span></td><td>{{ $item->periodo_servicio ?? '—' }}</td>
<td>{{ \Illuminate\Support\Carbon::parse($item->fecha_vencimiento)->format('d/m/Y') }}</td><td>{{ $item->fecha_pago ? \Illuminate\Support\Carbon::parse($item->fecha_pago)->format('d/m/Y H:i') : '—' }}</td>
<td>{{ number_format($item->monto, 2) }}</td><td>{{ ucfirst(mb_strtolower($item->estado)) }}<br>{{ $item->metodo_pago ?? '—' }}</td></tr>
@empty<tr><td colspan="6" class="empty-state">Este cliente aún no tiene pagos registrados.</td></tr>@endforelse
</tbody></table></div>
