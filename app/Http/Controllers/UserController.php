<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserService $users, private UserRepository $repository) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_.-]*$/'],
            'role' => ['nullable', 'integer', Rule::exists('roles', 'rol_id')],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return view('users.index', [
            'users' => $this->users->search(strtolower($filters['search'] ?? ''), isset($filters['role']) ? (int) $filters['role'] : null),
            'roles' => $this->repository->roles(false),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['account' => new User, 'roles' => $this->assignableRoles(), 'employees' => $this->repository->employees()]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated());

        return redirect()->route('users.show', $user)->with('status', 'Cuenta registrada correctamente.');
    }

    public function show(User $user): View
    {
        return view('users.show', ['account' => $user]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('edit-user', $user);

        return view('users.form', ['account' => $user, 'roles' => $user->hasRole(Role::ADMIN, false) ? $this->repository->roles()->where('nombre', Role::ADMIN) : $this->assignableRoles(), 'employees' => $this->repository->employees($user)]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->validated());

        return redirect()->route('users.show', $user)->with('status', 'Cuenta actualizada correctamente.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('edit-user', $user);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $this->users->setActive($user, (bool) $data['is_active']);

        return redirect()->route('users.show', $user)->with('status', $data['is_active'] ? 'Cuenta habilitada.' : 'Cuenta inhabilitada.');
    }

    private function assignableRoles()
    {
        return $this->repository->roles()->filter(fn ($role) => auth()->user()->isAdministrator() || $role->nombre !== Role::ADMIN);
    }
}
