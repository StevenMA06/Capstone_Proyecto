<article class="installation-panel warehouse-request-card" id="solicitud-grupo-{{ $group->solicitud_grupo_id }}">
    <header class="order-card-header warehouse-card-header">
        <div><p class="order-card-kicker">SOLICITUD CONJUNTA #{{ $group->solicitud_grupo_id }} · Pedido {{ $group->pedido_codigo }}</p><h3>{{ $items->count() === 1 ? 'Recurso solicitado' : 'Equipo y material solicitados' }}</h3></div>
        <span class="installation-state warehouse-state {{ $groupLabel === 'Preparada' ? 'is-ready' : ($groupLabel === 'Rechazada' ? 'is-rejected' : 'is-waiting') }}">{{ $groupLabel }}</span>
    </header>
    <div class="warehouse-group-lines">
        @foreach($items as $resourceItem)
            @php
                $matchingEquipment = $resourceItem->tipo === 'EQUIPO' && $resourceItem->equipo_tipo_solicitado
                    ? $availableEquipment->get($resourceItem->equipo_tipo_solicitado, collect()) : collect();
                $equipmentAvailable = $resourceItem->tipo === 'EQUIPO'
                    ? ($resourceItem->equipo_tipo_solicitado ? $matchingEquipment->isNotEmpty() : ($resourceItem->equipo_estado === 'DISPONIBLE' && !in_array($resourceItem->equipo_condicion, ['MALO', 'MALOGRADO'], true))) : true;
                $availableQuantity = $resourceItem->tipo === 'EQUIPO'
                    ? ($resourceItem->equipo_tipo_solicitado ? $matchingEquipment->count() : (int) $equipmentAvailable) : (float) $resourceItem->material_stock_libre;
                $shortage = $resourceItem->tipo === 'EQUIPO' ? !$equipmentAvailable : $availableQuantity < (float) $resourceItem->cantidad;
                $resource = $resourceItem->tipo === 'EQUIPO' ? 'equipo' : 'material';
                $materialUnit = $resourceItem->material_inventario_en_empaque ? $resourceItem->material_unidad_empaque : $resourceItem->material_unidad_base;
            @endphp
            <section class="warehouse-group-line">
                <div class="warehouse-group-line-heading"><div><small>{{ $resourceItem->tipo === 'EQUIPO' ? 'EQUIPO' : 'MATERIAL' }}</small><strong>{{ $resourceItem->tipo === 'EQUIPO' ? ($resourceItem->equipo_tipo_solicitado ? 'Tipo '.$resourceItem->equipo_tipo_solicitado : $resourceItem->equipo_codigo.' · '.$resourceItem->equipo_nombre) : $resourceItem->material_codigo.' · '.$resourceItem->material_nombre }}</strong>@if($resourceItem->tipo === 'EQUIPO' && !$resourceItem->equipo_tipo_solicitado)<span>{{ $resourceItem->equipo_tipo }} · Modelo {{ $resourceItem->equipo_modelo ?: 'No registrado' }} · Serie {{ $resourceItem->equipo_serie ?: 'No registrada' }}</span>@endif</div><span>{{ \App\Support\InventoryQuantity::format($resourceItem->cantidad) }}{{ $resourceItem->tipo === 'EQUIPO' ? ' unidad' : ' '.$materialUnit }}</span></div>
                @if($resourceItem->tipo === 'MATERIAL' && $resourceItem->material_inventario_en_empaque && $resourceItem->material_factor_conversion)<p class="warehouse-result-detail">Equivale a {{ \App\Support\InventoryQuantity::format($resourceItem->cantidad * $resourceItem->material_factor_conversion) }} {{ $resourceItem->material_unidad_base }}.</p>@endif
                @if($pending)<div class="warehouse-group-availability {{ $shortage ? 'is-shortage' : 'is-available' }}"><span>Disponible ahora</span><strong>{{ \App\Support\InventoryQuantity::format($availableQuantity) }} {{ $resourceItem->tipo === 'EQUIPO' ? 'unidad(es)' : $materialUnit }}</strong>@if($resourceItem->tipo === 'MATERIAL' && $resourceItem->material_inventario_en_empaque && $resourceItem->material_factor_conversion)<small>Equivale a {{ \App\Support\InventoryQuantity::format($availableQuantity * $resourceItem->material_factor_conversion) }} {{ $resourceItem->material_unidad_base }}</small>@endif<small>{{ $shortage ? 'No alcanza para completar este recurso' : 'Se reserva junto con el resto de la solicitud' }}</small></div>@endif
                @if($resourceItem->tipo === 'EQUIPO' && !$resourceItem->equipo_tipo_solicitado && $resourceItem->equipo_id)<p class="warehouse-result-detail"><strong>Unidad identificada:</strong> {{ $resourceItem->equipo_codigo }} · {{ $resourceItem->equipo_nombre }} · {{ $resourceItem->equipo_tipo }} · Modelo {{ $resourceItem->equipo_modelo ?: 'No registrado' }} · Serie {{ $resourceItem->equipo_serie ?: 'No registrada' }}</p>@endif
                @if($resourceItem->decision_motivo_codigo || $resourceItem->decision_observacion)<p class="warehouse-result-detail"><strong>Motivo:</strong> {{ \App\Support\ResourceRejectionReasons::label($resourceItem->decision_motivo_codigo) ?: $resourceItem->decision_observacion }}@if($resourceItem->decision_motivo_codigo && $resourceItem->decision_observacion) · {{ $resourceItem->decision_observacion }}@endif</p>@endif
            </section>
        @endforeach
    </div>
    <div class="warehouse-request-overview">
        <div><small>Solicitó Instalaciones</small><strong>{{ $group->solicitante }}</strong><span>{{ \Illuminate\Support\Carbon::parse($group->solicitado_en)->format('d/m/Y · H:i') }}</span></div>
        @if($group->fecha_programada)<div><small>Visita acordada</small><strong>{{ \Illuminate\Support\Carbon::parse($group->fecha_programada)->format('d/m/Y · H:i') }}</strong></div>@endif
    </div>
    @if($pending)
        @if(!$activeOrder)
            <div class="warehouse-flow is-muted"><strong>Este pedido ya no admite preparación</strong><p>Consulta el estado del pedido antes de realizar cambios en sus recursos.</p></div>
        @elseif($items->contains('estado', 'PENDIENTE_GERENCIA'))
            <div class="warehouse-flow is-waiting"><small>PASO ACTUAL · GERENCIA</small><strong>La solicitud completa espera autorización</strong><p>Cuando Gerencia apruebe, Almacén podrá preparar y reservar el equipo y material en una sola operación.</p>@if(auth()->user()->hasPermission('orders.resources.approve') && auth()->user()->hasPermission('orders.view') && !$sameAccount)<a class="action-link secondary" href="{{ route('orders.index', ['search' => $group->pedido_codigo, 'recursos' => 'pendiente']).'#pedido-'.$group->pedido_id }}">Revisar solicitud completa en Pedidos</a>@endif</div>
        @elseif($sameAccount && $items->contains('estado', 'PENDIENTE_ALMACEN'))
            <div class="warehouse-flow is-waiting"><small>PASO 1 DE 2</small><strong>Solicita autorización de la solicitud completa</strong><p>Como registraste estos recursos desde esta cuenta, otra persona de Gerencia debe autorizar el conjunto antes de prepararlo.</p><form method="POST" action="{{ route('warehouse.requests.escalate', $representative->solicitud_id) }}">@csrf @method('PATCH')<button type="submit" class="action-link">Enviar solicitud completa a Gerencia</button></form></div>
        @elseif($canReview)
            @php
                $anyShortage = $items->contains(fn ($resourceItem) => $resourceItem->tipo === 'EQUIPO'
                    ? ($resourceItem->equipo_tipo_solicitado
                        ? $availableEquipment->get($resourceItem->equipo_tipo_solicitado, collect())->isEmpty()
                        : !($resourceItem->equipo_estado === 'DISPONIBLE'
                            && !in_array($resourceItem->equipo_condicion, ['MALO', 'MALOGRADO'], true)))
                    : (float) $resourceItem->material_stock_libre < (float) $resourceItem->cantidad);
            @endphp
            <div class="warehouse-flow {{ $anyShortage ? 'is-waiting' : 'is-ready' }}"><small>PASO ACTUAL · ALMACÉN</small><strong>{{ $anyShortage ? 'Aún no se puede completar la solicitud' : 'Prepara y reserva toda la solicitud' }}</strong><p>{{ $anyShortage ? 'Al menos un recurso no tiene disponibilidad suficiente. Espera a que se reponga o rechaza la solicitud completa con el motivo correspondiente.' : ($items->contains(fn ($resourceItem) => $resourceItem->tipo === 'EQUIPO' && $resourceItem->equipo_tipo_solicitado) ? 'Confirma la unidad libre para los equipos por tipo. Al confirmar, equipo y material quedarán reservados juntos para el pedido.' : 'Verifica los recursos solicitados. El equipo ya está identificado por código, modelo y serie; al confirmar, equipo y material quedarán reservados juntos para el pedido.') }}</p></div>
            @unless($anyShortage)
                <form method="POST" action="{{ route('warehouse.requests.fulfill', $representative->solicitud_id) }}" class="warehouse-delivery-form warehouse-group-delivery">@csrf @method('PATCH')
                    @foreach($items->where('tipo', 'EQUIPO')->whereNotNull('equipo_tipo_solicitado') as $equipmentRequest)
                        @php
                            $matchingEquipment = $availableEquipment->get($equipmentRequest->equipo_tipo_solicitado, collect());
                        @endphp
                        <label>Unidad para {{ $equipmentRequest->equipo_tipo_solicitado }}<select name="equipos[{{ $equipmentRequest->solicitud_id }}]" required><option value="">Selecciona una unidad libre</option>@foreach($matchingEquipment as $equipment)<option value="{{ $equipment->equipo_id }}" @selected(old('equipos.'.$equipmentRequest->solicitud_id) == $equipment->equipo_id)>{{ $equipment->codigo }} · {{ $equipment->nombre }}{{ $equipment->numero_serie ? ' · Serie '.$equipment->numero_serie : '' }}</option>@endforeach</select></label>
                    @endforeach
                    <button type="submit" class="action-link">Preparar y reservar todo</button>
                </form>
            @endunless
            <details class="warehouse-reject" @if($errors->any() && old('solicitud_contexto') == $representative->solicitud_id) open @endif><summary>Rechazar solicitud completa</summary><div class="warehouse-reject-body"><p>El rechazo se aplicará a todos los recursos y quedará en el historial con tu cuenta y el motivo.</p><form method="POST" action="{{ route('warehouse.requests.reject', $representative->solicitud_id) }}" data-resource-rejection-form>@csrf @method('PATCH')<input type="hidden" name="solicitud_contexto" value="{{ $representative->solicitud_id }}"><label>Motivo del rechazo<select name="decision_motivo_codigo" required><option value="">Selecciona un motivo</option>@foreach($items->count() > 1 ? \App\Support\ResourceRejectionReasons::warehouseGroup() : \App\Support\ResourceRejectionReasons::warehouse($representative->tipo, $anyShortage ?? false) as $code => $reason)<option value="{{ $code }}" @selected(old('solicitud_contexto') == $representative->solicitud_id && old('decision_motivo_codigo') === $code)>{{ $reason }}</option>@endforeach</select></label><label>Detalle adicional <small>Obligatorio si eliges «Otro motivo»</small><textarea name="decision_observacion" maxlength="500" rows="3" placeholder="Añade información útil para Instalaciones">{{ old('solicitud_contexto') == $representative->solicitud_id ? old('decision_observacion') : '' }}</textarea></label><button type="submit" class="action-link rejection-submit">Confirmar rechazo completo</button></form></div></details>
        @else
            <div class="warehouse-flow is-muted"><strong>En espera de revisión de Almacén</strong><p>Esta solicitud requiere una cuenta autorizada para preparar o rechazar los recursos.</p></div>
        @endif
    @else
        @php
            $finalActor = $groupLabel === 'Preparada'
                ? [$representative->atendido_por ? $representative->almacenista : null, $representative->atendido_en, 'Preparó y reservó la solicitud completa']
                : ($groupLabel === 'Rechazada'
                    ? [$representative->cerrado_por ? $representative->cerrador : $representative->aprobador,
                        $representative->cerrado_en ?: $representative->resuelto_en, 'Rechazó la solicitud completa']
                    : [$representative->cerrador, $representative->cerrado_en, 'Cerró la solicitud']);
        @endphp
        <div class="warehouse-flow {{ $groupLabel === 'Rechazada' ? 'is-rejected' : ($groupLabel === 'Preparada' ? 'is-ready' : 'is-muted') }}"><small>RESULTADO</small><strong>{{ $groupLabel === 'Preparada' ? 'Solicitud completa preparada y reservada' : ($groupLabel === 'Rechazada' ? 'Solicitud completa rechazada' : 'Solicitud anulada') }}</strong><p>{{ $groupLabel === 'Preparada' ? 'Equipo y material están asignados al pedido. Instalaciones puede continuar con la asignación del técnico.' : ($groupLabel === 'Rechazada' ? 'Instalaciones puede revisar el motivo y registrar una nueva solicitud conjunta.' : 'Los recursos ya no están solicitados para este pedido.') }}</p></div>
        <div class="warehouse-request-timeline">
            @if($finalActor[0] && $finalActor[1])<p><small>{{ $finalActor[2] }}</small><strong>{{ $finalActor[0] }}</strong><span>{{ \Illuminate\Support\Carbon::parse($finalActor[1])->format('d/m/Y · H:i') }}</span></p>@endif
        </div>
    @endif
</article>
