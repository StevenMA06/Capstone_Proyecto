<div class="table-scroll"><table class="users-table"><caption class="sr-only">Instalaciones del cliente</caption>
<thead><tr><th>Pedido / Contrato</th><th>Solicitud</th><th>Programación</th><th>Técnico</th><th>Estado</th><th>Observaciones</th></tr></thead><tbody>
@forelse($history as $item)
<tr><td>{{ $item->codigo }}<br><span class="muted">{{ $item->contrato_codigo }}</span></td>
<td>{{ \Illuminate\Support\Carbon::parse($item->fecha_solicitud)->format('d/m/Y H:i') }}</td><td>{{ $item->fecha_programada ? \Illuminate\Support\Carbon::parse($item->fecha_programada)->format('d/m/Y H:i') : 'Sin programar' }}</td>
<td>{{ $item->tecnico_id ? trim($item->tecnico_nombres.' '.$item->tecnico_apellidos) : 'Sin asignar' }}</td>
<td>{{ ucfirst(mb_strtolower(str_replace('_', ' ', $item->estado))) }}</td><td>{{ $item->observaciones ?? '—' }}</td></tr>
@empty<tr><td colspan="6" class="empty-state">Este cliente aún no tiene instalaciones registradas.</td></tr>@endforelse
</tbody></table></div>
