@extends('contracts.layout')
@section('contracts-title', $contract ? 'Actualizar contrato ' . $contract->codigo : 'Registro de Contrato')
@section('contracts-crumb', $contract ? 'Actualizar' : 'Nuevo')
@push('styles')
    <script src="{{ asset('js/contracts.js') }}?v=6" defer></script>
@endpush
@section('contracts-content')
    @routecan('contracts.index')<div class="contract-form-nav"><a class="secondary contract-return-link" href="{{ route('contracts.index') }}">← Volver al listado</a></div>@endroutecan
    @if ($plans->isEmpty())
        <p class="notice">No hay planes activos. Solicita a Gerencia que registre o active un plan.</p>
    @endif
    @if ($clients->isEmpty() || $zones->isEmpty() || ($canChooseResponsible && $employees->isEmpty()))
        <p class="notice">Se necesita al menos un cliente, una zona y, para Gerencia o Administración, un Asistente de Ventas activo.</p>
    @endif
    @php($selectedClient = $clients->firstWhere('cliente_id', (int) old('cliente_id', $contract->cliente_id ?? ($selectedClientId ?? 0))))
    @can('contracts.manage')<form method="POST" @if ($contract) enctype="multipart/form-data" @endif
        action="{{ $contract ? route('contracts.update', $contract->contrato_id) : route('contracts.store') }}"
        class="account-form contracts-form" data-coverage="{{ $coverage->toJson() }}"
        data-current-plan="{{ $contract->plan_id ?? '' }}" data-current-price="{{ $contract->precio_contratado ?? '' }}">
        @csrf @if ($contract)
            @method('PUT')
        @endif
        <div class="contracts-form-grid">
            <div class="contracts-form-column">
                <div class="contract-field"><label for="cliente_id">Cliente *</label><select id="cliente_id"
                        name="cliente_id" required
                        @error('cliente_id') aria-invalid="true" aria-describedby="cliente_id-error" @enderror>
                        <option value="">Seleccionar cliente</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->cliente_id }}" data-district="{{ $client->distrito }}" data-address="{{ $client->direccion }}"
                                @selected((string) old('cliente_id', $contract->cliente_id ?? ($selectedClientId ?? '')) === (string) $client->cliente_id)>{{ $client->numero_documento }} ·
                                {{ trim($client->nombres_razon_social . ' ' . $client->apellidos) }} · {{ $client->estado === 'PENDIENTE' ? 'Pendiente de instalación' : 'Activo' }}</option>
                        @endforeach
                    </select>
                    @error('cliente_id')
                        <p id="cliente_id-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="contract-field"><label for="plan_id">Plan de servicio *</label><select id="plan_id"
                        name="plan_id" required
                        @error('plan_id') aria-invalid="true" aria-describedby="plan_id-error" @enderror>
                        <option value="">Seleccionar plan</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->plan_id }}" data-price="{{ $plan->precio_mensual }}"
                                @selected((string) old('plan_id', $contract->plan_id ?? '') === (string) $plan->plan_id)>{{ $plan->nombre }} · {{ $plan->numero_plan }}</option>
                        @endforeach
                    </select>
                    @error('plan_id')
                        <p id="plan_id-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="contract-field"><label for="precio_contratado">Precio contratado</label><input
                        id="precio_contratado" type="text"
                        value="{{ $contract ? 'S/ ' . number_format($contract->precio_contratado, 2) : '' }}"
                        placeholder="Selecciona un plan" readonly></div>
                <div class="contract-field"><label for="zona_id">Zona de cobertura *</label><select id="zona_id"
                        name="zona_id" required
                        @error('zona_id') aria-invalid="true" aria-describedby="zona_id-error" @enderror>
                        <option value="">Seleccionar zona</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->zona_id }}" data-district="{{ $zone->distrito }}"
                                @selected((string) old('zona_id', $contract->zona_id ?? '') === (string) $zone->zona_id)>{{ $zone->nombre }} · {{ $zone->distrito }}</option>
                        @endforeach
                    </select>
                    <small id="coverage-message" role="status">Selecciona el cliente, plan y zona para validar
                        cobertura.</small>
                    @error('zona_id')
                        <p id="zona_id-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="contract-field"><label for="direccion_servicio">Dirección de servicio</label><input
                        id="direccion_servicio" value="{{ $selectedClient?->direccion ?? '' }}"
                        placeholder="Selecciona un cliente" readonly>
                </div>
                @if ($canChooseResponsible)
                <div class="contract-field"><label for="responsable_id">Responsable del contrato *</label><select id="responsable_id"
                        name="responsable_id" required
                        @error('responsable_id') aria-invalid="true" aria-describedby="responsable_id-error" @enderror>
                        <option value="">Seleccionar responsable</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->empleado_id }}" @selected((string) old('responsable_id', $contract->responsable_id ?? '') === (string) $employee->empleado_id)>
                                {{ $employee->nombres }} {{ $employee->apellidos }}</option>
                        @endforeach
                    </select>
                    @error('responsable_id')
                        <p id="responsable_id-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                @else
                <div class="contract-field contract-responsible-summary">
                    <span class="contract-field-label">Responsable del contrato</span>
                    <strong>{{ $assignedResponsible ? trim($assignedResponsible->nombres.' '.$assignedResponsible->apellidos) : 'Tu cuenta no tiene un Asistente de Ventas activo asociado' }}</strong>
                    <small>Se asignará automáticamente la persona vinculada a tu cuenta. Gerencia o Administración puede cambiarla.</small>
                    @error('responsable_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                @endif
            </div>
            <div class="contracts-form-column">
                <div class="contract-field"><label for="fecha_inicio">Fecha de inicio *</label><input id="fecha_inicio"
                        type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $contract->fecha_inicio ?? '') }}"
                        required @error('fecha_inicio') aria-invalid="true" aria-describedby="fecha_inicio-error" @enderror>
                    @error('fecha_inicio')
                        <p id="fecha_inicio-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="contract-field"><label for="duracion_meses">Duración en meses *</label><input
                        id="duracion_meses" type="number" name="duracion_meses"
                        value="{{ old('duracion_meses', $contract->duracion_meses ?? 12) }}" min="1" max="65535"
                        step="1" required
                        @error('duracion_meses') aria-invalid="true" aria-describedby="duracion_meses-error" @enderror>
                    @error('duracion_meses')
                        <p id="duracion_meses-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="contract-field"><label for="fecha_fin">Fecha de finalización</label><input id="fecha_fin"
                        type="text" value="{{ $contract->fecha_fin ?? '' }}" placeholder="Se calcula automáticamente"
                        readonly></div>
                    <div class="contract-field"><label for="estado_actual">Estado</label><select id="estado_actual" disabled>
                            <option>{{ $contract ? ucfirst(mb_strtolower($contract->estado)) : 'Pendiente' }}</option>
                        </select></div>
                <div class="contract-field"><label for="observaciones">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="5" maxlength="10000"
                        placeholder="Notas adicionales (opcional)"
                        @error('observaciones') aria-invalid="true" aria-describedby="observaciones-error" @enderror>{{ old('observaciones', $contract->observaciones ?? '') }}</textarea>
                    @error('observaciones')
                        <p id="observaciones-error" class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
        @if ($contract)
        <div class="contract-upload"><label for="documento_pdf">Documento contractual firmado (PDF)</label>
            @if($storedPdfAvailable)
                <div class="contract-saved-file" role="status">
                    <span class="contract-saved-file-icon" aria-hidden="true">✓</span>
                    <div><strong>PDF adjunto guardado</strong><span>{{ $contract->firma_revision === 'VERIFICADA' ? 'Firma del cliente detectada' : 'Firma pendiente de detectar' }}</span></div>
                    
@routecan('contracts.pdf')<a href="{{ route('contracts.pdf', $contract->contrato_id) }}">Descargar adjunto</a>@endroutecan

                </div>
            @elseif($contract->pdf_contrato_ruta)
                <p class="contract-file-warning" role="alert">El PDF registrado no está disponible. Selecciona un archivo para reemplazarlo.</p>
            @endif
            <div class="contract-dropzone" data-dropzone><svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" />
                </svg><span id="selected-pdf" aria-live="polite">Arrastra un PDF aquí o selecciona uno</span><span id="selected-pdf-hint" class="contract-selected-hint" hidden>Archivo seleccionado · se guardará al pulsar «Guardar cambios».</span><label class="contract-file-button"
                    for="documento_pdf">Seleccionar archivo</label></div><input class="contract-file-input"
                id="documento_pdf" type="file" name="documento_pdf" accept="application/pdf"
                @error('documento_pdf') aria-invalid="true" aria-describedby="documento_pdf-error" @enderror><small>Usa el contrato generado por este sistema y verifica que la firma del cliente aparezca sobre la línea «CONTRATO SERVICIO DE INTERNET». Máximo 10 MB.</small>
                @error('documento_pdf')
                <p id="documento_pdf-error" class="field-error">{{ $message }}</p>
            @enderror
        </div>
        @endif
        <div class="form-actions contract-form-actions"><button class="action-link" type="submit"
                @disabled(!$contract && ($plans->isEmpty() || $clients->isEmpty() || $zones->isEmpty() || ($canChooseResponsible ? $employees->isEmpty() : !$assignedResponsible)))>{{ $contract ? 'Guardar cambios' : 'Guardar contrato' }}</button>
        </div>
    </form>@endcan
    @if($contract && $cancellationWorkflowReady && auth()->user()->hasPermission('contracts.cancel.request') && in_array($contract->estado, ['PENDIENTE', 'ACTIVO'], true))
        <div class="cancellation-request-placement">@include('contracts._cancellation-request-form')</div>
    @endif
@endsection
