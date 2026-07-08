<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Credenciales incorrectas.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($this->homeRouteFor($request->user()));
    }

    /**
     * Cada usuario entra directo a su area segun sus permisos: los usuarios de
     * solo calendario nunca pasan por el dashboard de facturacion (sin fugas
     * de ingresos u otra informacion sensible).
     */
    private function homeRouteFor(?\App\Models\User $user): string
    {
        if ($user === null) {
            return route('login');
        }

        if ($user->hasPermission('ver_factura')) {
            return route('web.dashboard');
        }

        if ($user->hasPermission('ver_calendario')) {
            return route('web.appointments.index');
        }

        if ($user->hasPermission('ver_informes')) {
            return route('web.technical-reports.index');
        }

        return route('web.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
