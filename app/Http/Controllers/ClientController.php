<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(private ClientService $clients) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:180'],
            'estado' => ['nullable', Rule::in(['ACTIVO', 'INACTIVO'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $clients = $this->clients->search($filters['search'] ?? '', $filters['estado'] ?? null);

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('clients.create', ['client' => new Client]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        try {
            $client = $this->clients->create($request->validated(), $request->user());
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'uq_clientes_documento')) {
                throw $exception;
            }

            return back()->withInput()->withErrors([
                'numero_documento' => 'Este número ya está registrado para el tipo de documento seleccionado.',
            ]);
        }

        return redirect()->route('clients.show', $client)
            ->with('status', 'Cliente #'.$client->getKey().' registrado correctamente.');
    }

    public function show(Request $request, Client $client): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['contratos', 'pagos', 'instalaciones'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $tab = $filters['tab'] ?? 'contratos';
        $history = $this->clients->history($client, $tab);

        return view('clients.show', compact('client', 'tab', 'history'));
    }

    public function edit(Client $client): View
    {
        return view('clients.create', compact('client'));
    }

    public function update(StoreClientRequest $request, Client $client): RedirectResponse
    {
        try {
            $this->clients->update($client, $request->validated());
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'uq_clientes_documento')) {
                throw $exception;
            }

            return back()->withInput()->withErrors([
                'numero_documento' => 'Este número ya está registrado para el tipo de documento seleccionado.',
            ]);
        }

        return redirect()->route('clients.show', $client)->with('status', 'Datos del cliente actualizados correctamente.');
    }

    public function deactivate(Client $client): RedirectResponse
    {
        $this->clients->deactivate($client);

        return redirect()->route('clients.show', $client)
            ->with('status', 'Cliente inhabilitado. Su información y sus historiales se conservan.');
    }
}
