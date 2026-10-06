@extends('zones.layout')
@section('zones-content')
@php($chosenPlans = collect(old('plan_ids', $selectedPlans))->map(fn ($id) => (string) $id)->all())
<div class="form-heading"><div><h2>{{ $zone->exists ? 'Actualizar zona de cobertura' : 'Registrar zona de cobertura' }}</h2><p>Los campos marcados con * son obligatorios.</p></div></div>
@can('zones.manage')
@routecan('zones.update')<form method="POST" action="{{ $zone->exists ? route('zones.update', $zone) : route('zones.store') }}" class="account-form zone-form">
    @csrf @if($zone->exists) @method('PUT') @endif
    <div><label for="nombre">Nombre de la zona *</label><input id="nombre" name="nombre" value="{{ old('nombre', $zone->nombre) }}" required maxlength="120" placeholder="Ej. Zona Norte" @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror>@error('nombre')<p id="nombre-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div><label for="departamento">Departamento *</label><input id="departamento" name="departamento" value="{{ old('departamento', $zone->departamento ?: 'La Libertad') }}" required maxlength="100" @error('departamento') aria-invalid="true" aria-describedby="departamento-error" @enderror>@error('departamento')<p id="departamento-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div><label for="provincia">Provincia (opcional)</label><input id="provincia" name="provincia" value="{{ old('provincia', $zone->provincia) }}" maxlength="100" placeholder="Ej. Trujillo" @error('provincia') aria-invalid="true" aria-describedby="provincia-error" @enderror>@error('provincia')<p id="provincia-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div><label for="distrito">Distrito *</label><input id="distrito" name="distrito" value="{{ old('distrito', $zone->distrito) }}" required maxlength="100" placeholder="Ej. Huanchaco" @error('distrito') aria-invalid="true" aria-describedby="distrito-error" @enderror>@error('distrito')<p id="distrito-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="zone-description"><label for="descripcion">Descripción de cobertura (opcional)</label><textarea id="descripcion" name="descripcion" rows="4" maxlength="500" placeholder="Describe sectores, referencias o condiciones de cobertura" @error('descripcion') aria-invalid="true" aria-describedby="descripcion-error" @enderror>{{ old('descripcion', $zone->descripcion) }}</textarea>@error('descripcion')<p id="descripcion-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
    <fieldset class="zone-plans"><legend>Planes disponibles en esta zona</legend><p>Selecciona los planes activos que podrán contratarse en esta ubicación.</p>
        @if($plans->isEmpty())<div class="empty-selection">No hay planes activos disponibles.</div>@else<div class="plan-options">@foreach($plans as $plan)<label><input id="plan-{{ $plan->plan_id }}" type="checkbox" name="plan_ids[]" value="{{ $plan->plan_id }}" @checked(in_array((string) $plan->plan_id, $chosenPlans, true))><span><strong>{{ $plan->nombre }}</strong><small>{{ $plan->numero_plan }} · S/ {{ number_format($plan->precio_mensual, 2) }}</small></span></label>@endforeach</div>@endif
        @error('plan_ids')<p id="plan_ids-error" class="field-error" role="alert">{{ $message }}</p>@enderror @error('plan_ids.*')<p id="plan_ids-item-error" class="field-error" role="alert">{{ $message }}</p>@enderror
    </fieldset>
    <div class="form-actions"><button class="action-link" type="submit">{{ $zone->exists ? 'Guardar cambios' : 'Registrar zona' }}</button>
@routecan('zones.show')<a class="secondary" href="{{ $zone->exists ? route('zones.show', $zone) : route('zones.index') }}">Cancelar</a>@endroutecan
</div>
</form>@endroutecan
@endcan
@endsection
