<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Services\ClientService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(private ClientService $clients) {}

    public function create(): View
    {
        return view('clients.create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        try {
            $client = $this->clients->create($request->validated(), $request->user());
        } catch (UniqueConstraintViolationException $exception) {
            // Preserve the existing SQL constraint without adding an availability lookup.
            if (! str_contains($exception->getMessage(), 'uq_clientes_documento')) {
                throw $exception;
            }

            return back()->withInput()->withErrors([
                'registro' => 'No se pudo registrar el cliente: el tipo y número de documento ya están registrados.',
            ]);
        }

        return redirect()->route('clients.create')
            ->with('status', 'Cliente #'.$client->getKey().' registrado correctamente.');
    }
}
