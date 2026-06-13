<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReportSettingRequest;
use App\Models\ReportSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReportSettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.reports', [
            'setting' => ReportSetting::current(),
        ]);
    }

    public function update(UpdateReportSettingRequest $request): RedirectResponse
    {
        $setting = ReportSetting::current();
        $setting->update($request->validated());

        return redirect()
            ->route('web.settings.reports.edit')
            ->with('status', 'Configuracion de informes actualizada correctamente.');
    }
}
