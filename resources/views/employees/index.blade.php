@extends('employees.layout')
@section('employees-content')
<div class="management-toolbar"><h2>Listado de empleados <span class="count">{{ $records->total() }}</span></h2><a class="action-link" href="{{ route('employees.create') }}">+ Registrar empleado</a></div>
<form method="GET" action="{{ route('employees.index') }}" class="filters">
<div><label for="search">Nombre o documento</label><input id="search" name="search" value="{{ request('search') }}" maxlength="120" placeholder="Buscar empleado"></div>
<div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option>@foreach(['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'] as $value => $label)<option value="{{ $value }}" @selected(request('estado') === $value)>{{ $label }}</option>@endforeach</select></div>
<button class="action-link" type="submit">Buscar</button><a class="clear-link" href="{{ route('employees.index') }}">Limpiar</a>
</form>
<div class="table-scroll"><table class="users-table"><caption class="sr-only">Personal registrado</caption><thead><tr><th scope="col">Documento</th><th scope="col">Empleado</th><th scope="col">Cargo</th><th scope="col">Inicio</th><th scope="col">Cese</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead><tbody>
@forelse($records as $employee)<tr><td>{{ $employee->tipo_documento }} {{ $employee->numero_documento }}</td><td>{{ $employee->full_name }}</td><td>{{ $employee->cargo }}</td><td>{{ $employee->fecha_ingreso ?? 'Sin registrar' }}</td><td>{{ $employee->fecha_cese ?? 'Sin registrar' }}</td><td><span class="state {{ $employee->estado === 'ACTIVO' ? 'active' : 'inactive' }}">{{ $employee->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</span></td><td><div class="row-actions"><a href="{{ route('employees.show', $employee) }}">Ver ficha</a>@can('edit-employee', $employee)<a href="{{ route('employees.edit', $employee) }}">Editar</a>@endcan</div></td></tr>
@empty<tr><td colspan="7" class="empty-state">No se encontraron empleados con esos criterios.</td></tr>@endforelse
</tbody></table></div>
@include('clients.pagination', ['label' => 'Paginación de empleados'])
@endsection
