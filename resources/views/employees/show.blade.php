@extends('employees.layout')
@section('employees-content')
<div class="management-toolbar"><h2>{{ $employee->full_name }}</h2>@can('edit-employee', $employee)@can('employees.manage')<a class="action-link" href="{{ route('employees.edit', $employee) }}">Editar empleado</a>@endcan
@endcan</div>
<dl class="account-detail">
<div><dt>Identificador</dt><dd>#{{ $employee->empleado_id }}</dd></div><div><dt>Documento</dt><dd>{{ $employee->tipo_documento }} {{ $employee->numero_documento }}</dd></div>
@foreach(['nombres'=>'Nombres','apellidos'=>'Apellidos','cargo'=>'Cargo','correo'=>'Correo electrónico','telefono'=>'Teléfono','fecha_ingreso'=>'Fecha de inicio','fecha_cese'=>'Fecha de cese','creado_en'=>'Fecha de registro','actualizado_en'=>'Última actualización'] as $field=>$label)<div><dt>{{ $label }}</dt><dd>{{ $employee->$field ?? 'Sin registrar' }}</dd></div>@endforeach
<div><dt>Estado</dt><dd>{{ $employee->estado === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</dd></div><div><dt>Vínculo laboral</dt><dd>{{ $employee->hasEnded() ? 'Finalizado' : ($employee->fecha_ingreso && $employee->fecha_ingreso > \App\Models\Employee::today() ? 'Inicio pendiente' : 'Sin cese vencido') }}</dd></div>
</dl>
<h3>Cuentas asociadas</h3>
@forelse($employee->accounts()->with('role')->get() as $account)<p><a class="clear-link" href="{{ route('users.show', $account) }}">{{ $account->nombre_usuario }}</a> · {{ $account->role?->nombre }} · {{ $account->estado }}</p>@empty<p>Este empleado no tiene una cuenta asociada.</p>@endforelse
@can('edit-employee', $employee)
<div class="status-box"><div><h3>{{ $employee->estado === 'ACTIVO' ? 'Inhabilitar empleado' : 'Habilitar empleado' }}</h3><p>La inhabilitación bloquea las cuentas asociadas y cierra sus sesiones. Se conserva la información histórica. Para habilitarlo, su fecha de cese no puede estar vencida; las cuentas se habilitan por separado en Usuarios.</p></div>@can('employees.manage')<form method="POST" action="{{ route('employees.status', $employee) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $employee->estado === 'ACTIVO' ? '0' : '1' }}"><button class="secondary" type="submit">{{ $employee->estado === 'ACTIVO' ? 'Inhabilitar' : 'Habilitar' }}</button></form>@endcan</div>
@endcan
<a class="secondary" href="{{ route('employees.index') }}">Volver al listado de empleados</a>
@endsection
