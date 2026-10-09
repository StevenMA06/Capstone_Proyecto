<section class="order-manager-review" id="revisar-recursos-{{ $record->pedido_id }}" aria-labelledby="review-title-{{ $record->pedido_id }}">
    <header class="order-manager-review-heading">
        <div><p class="order-step-label">DECISIÓN DE GERENCIA</p><h4 id="review-title-{{ $record->pedido_id }}">Solicitudes de recursos por autorizar</h4><p>Revisa cada solicitud completa. Una autorización permite a Almacén preparar juntos el equipo y el material.</p></div>
        <span class="order-manager-review-count">{{ $resourcesForManagerGroups->count() }} {{ $resourcesForManagerGroups->count() === 1 ? 'solicitud' : 'solicitudes' }}</span>
    </header>
    <div class="order-manager-request-list">
        @foreach($resourcesForManagerGroups as $group)
        @php($representative = $group->first())
        @php($hasMaterial = $group->contains('tipo', 'MATERIAL'))
        <article class="order-manager-request">
            <div class="order-manager-request-heading"><div><small>Solicitud conjunta · {{ $group->count() }} {{ $group->count() === 1 ? 'recurso' : 'recursos' }}</small><h5>Pedido {{ $record->codigo }}</h5></div><span>Pendiente de decisión</span></div>
            <ul class="order-manager-resource-lines">
                @foreach($group as $item)
                <li class="order-manager-resource-line">
                    <div class="order-manager-resource-info">
                        <small class="order-manager-resource-kind">{{ $item->tipo === 'EQUIPO' ? 'Equipo' : 'Material' }}</small>
                        <strong>{{ $item->tipo === 'EQUIPO' ? ($item->equipo_tipo_solicitado ? 'Tipo '.$item->equipo_tipo_solicitado : $item->equipo_codigo.' · '.$item->equipo_nombre) : $item->material_codigo.' · '.$item->material_nombre }}</strong>
                        @if($item->tipo === 'EQUIPO' && !$item->equipo_tipo_solicitado)
                            <small>{{ $item->equipo_modelo ? 'Modelo '.$item->equipo_modelo : 'Modelo sin registrar' }} · {{ $item->equipo_serie ? 'Serie '.$item->equipo_serie : 'Serie sin registrar' }}</small>
                        @elseif($item->tipo === 'MATERIAL')
                            <small>Unidad de medida: {{ $item->material_inventario_en_empaque ? $item->material_unidad_empaque : $item->material_unidad_base }}</small>
                        @endif
                    </div>
                    <div class="order-manager-resource-quantity">
                        <small>Cantidad solicitada</small>
                        <strong>{{ \App\Support\InventoryQuantity::format($item->cantidad) }} {{ $item->tipo === 'EQUIPO' ? 'unidad' : ($item->material_inventario_en_empaque ? $item->material_unidad_empaque : $item->material_unidad_base) }}</strong>
                        @if($item->tipo === 'MATERIAL' && $item->material_inventario_en_empaque && $item->material_factor_conversion)
                            <small>Equivale a {{ \App\Support\InventoryQuantity::format($item->cantidad * $item->material_factor_conversion) }} {{ $item->material_unidad_base }}</small>
                        @endif
                    </div>
                </li>
                @endforeach
            </ul>
            <dl class="order-manager-request-facts"><div><dt>Solicitó</dt><dd>{{ $representative->solicitante }}</dd></div><div><dt>Fecha de solicitud</dt><dd>{{ \Illuminate\Support\Carbon::parse($representative->solicitado_en)->format('d/m/Y · H:i') }}</dd></div></dl>
            @if($group->contains(fn ($item) => (int) $item->solicitado_por === (int) auth()->id()))
                <p class="order-manager-own-request" role="status">Registraste esta solicitud. Otra cuenta de Gerencia debe autorizarla completa.</p>
            @else
                <div class="order-manager-request-decisions">
                    <form method="POST" action="{{ route('orders.resources.approve', [$record->pedido_id, $representative->solicitud_id]) }}" data-installation-form data-return-anchor="pedido-{{ $record->pedido_id }}">@csrf @method('PATCH')<button type="submit" class="action-link">Autorizar la solicitud completa</button></form>
                    <details class="order-manager-reject" @if($errors->any() && old('resource_decision') == $representative->solicitud_id) open @endif><summary>Rechazar la solicitud completa</summary><div><p>La decisión se aplicará al equipo y al material juntos y quedará registrada en el historial.</p><form method="POST" action="{{ route('orders.resources.reject', [$record->pedido_id, $representative->solicitud_id]) }}" data-installation-form data-resource-rejection-form data-return-anchor="pedido-{{ $record->pedido_id }}">@csrf @method('PATCH')<input type="hidden" name="resource_decision" value="{{ $representative->solicitud_id }}"><label>Motivo del rechazo<select name="decision_motivo_codigo" required><option value="">Selecciona un motivo</option>@foreach(\App\Support\ResourceRejectionReasons::management($hasMaterial ? null : 'EQUIPO') as $code => $reason)<option value="{{ $code }}" @selected(old('resource_decision') == $representative->solicitud_id && old('decision_motivo_codigo') === $code)>{{ $reason }}</option>@endforeach</select></label><label>Detalle adicional <small>Obligatorio si eliges «Otro motivo»</small><textarea name="decision_observacion" maxlength="500" rows="3" placeholder="Explica qué debe corregirse o revisarse">{{ old('resource_decision') == $representative->solicitud_id ? old('decision_observacion') : '' }}</textarea></label><button type="submit" class="action-link rejection-submit">Confirmar rechazo completo</button></form></div></details>
                </div>
            @endif
        </article>
        @endforeach
    </div>
</section>
