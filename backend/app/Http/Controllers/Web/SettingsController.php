<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SettingsBootstrapService;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(SettingsBootstrapService $settings): View
    {
        return view('settings.index', ['settings' => $settings->get()]);
    }
}
