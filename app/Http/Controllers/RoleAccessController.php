<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAccessService;
use App\Support\AccessCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleAccessController extends Controller
{
    public function index(Request $request, RoleAccessService $service)
    {
        $request->validate(['role' => ['nullable', 'integer', 'exists:roles,rol_id']]);
        $selected = $request->filled('role') ? Role::findOrFail($request->integer('role')) : null;
        return view('access.roles', ['roles' => Role::orderBy('nombre')->get(), 'selected' => $selected,
            'snapshot' => $selected ? $service->snapshot($selected) : [], 'catalog' => AccessCatalog::permissions()]);
    }

    public function store(Request $request, RoleAccessService $service)
    {
        return $this->save($request, $service);
    }

    public function update(Request $request, Role $role, RoleAccessService $service)
    {
        return $this->save($request, $service, $role);
    }

    private function save(Request $request, RoleAccessService $service, ?Role $role = null)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:50', Rule::unique('roles', 'nombre')->ignore($role?->getKey(), 'rol_id')],
            'descripcion' => ['required', 'string', 'max:255'], 'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
            'permissions' => ['sometimes', 'array'], 'permissions.*' => ['string', 'distinct', Rule::in(array_keys(AccessCatalog::permissions()))],
        ], [], ['nombre' => 'nombre del rol', 'descripcion' => 'descripción', 'permissions' => 'permisos']);
        $role = $service->saveRole($request->user(), $role, $data);
        return redirect()->route('roles.index', ['role' => $role->getKey()])->with('status', 'Rol y permisos guardados correctamente.');
    }

    public function assignments(Request $request)
    {
        $request->validate(['search' => ['nullable', 'string', 'max:60'], 'user' => ['nullable', 'integer', 'exists:usuarios,usuario_id']]);
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $request->string('search')->toString()).'%';
        return view('access.assignments', ['accounts' => User::whereRaw("nombre_usuario LIKE ? ESCAPE '!'", [$pattern])->orderBy('nombre_usuario')->paginate(15)->withQueryString(),
            'selected' => $request->filled('user') ? User::findOrFail($request->integer('user')) : null,
            'roles' => Role::where('estado', 'ACTIVO')->when(! $request->user()->isAdministrator(), fn ($q) => $q->where('nombre', '<>', Role::ADMIN))->orderBy('nombre')->get()]);
    }

    public function assign(Request $request, User $user, RoleAccessService $service)
    {
        $data = $request->validate(['roles' => ['required', 'array', 'min:1'], 'roles.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'rol_id')->where('estado', 'ACTIVO')]], [], ['roles' => 'roles activos']);
        $service->assign($request->user(), $user, $data['roles']);
        return redirect()->route('user-roles.index', ['user' => $user->getKey()])->with('status', 'Roles asignados correctamente. Los permisos ya están actualizados.');
    }
}
