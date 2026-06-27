<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(): View|RedirectResponse
    {
        if (! auth()->user()?->hasPermission('ver_factura')) {
            return redirect()->route('web.appointments.index');
        }

        return view('dashboard.index', $this->dashboard->metrics());
    }
}
