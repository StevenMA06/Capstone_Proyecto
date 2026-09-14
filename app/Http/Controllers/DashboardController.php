<?php

namespace App\Http\Controllers;

use App\Repositories\DashboardRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardRepository $dashboard): View
    {
        return view('dashboard', [
            'clientCount' => $request->user()->can('register-clients') ? $dashboard->clients() : null,
            'summary' => $request->user()->isManager() ? $dashboard->management() : null,
        ]);
    }
}
