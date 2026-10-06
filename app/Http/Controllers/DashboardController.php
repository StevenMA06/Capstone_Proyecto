<?php
namespace App\Http\Controllers;

use App\Repositories\DashboardRepository;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user=$request->user();
        foreach (['dashboard.view'=>'dashboard.management','office.view'=>'dashboard.office','technician.panel'=>'technician.index'] as $permission=>$route) {
            if ($user->hasPermission($permission)) return redirect()->route($route);
        }
        return view('access.home');
    }

    public function management(Request $request, DashboardRepository $dashboard)
    {
        return view('dashboard',['clientCount'=>$dashboard->clients(),'summary'=>$dashboard->management()]);
    }

    public function office()
    {
        return view('access.office');
    }
}
