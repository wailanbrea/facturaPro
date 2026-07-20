<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReportSettingResource;
use App\Models\ReportSetting;
use Illuminate\Http\Request;

class ReportSettingController extends Controller
{
    public function show(Request $request): ReportSettingResource
    {
        $profileId = $request->integer('fiscal_profile_id') ?: null;
        if ($profileId !== null) {
            abort_unless(in_array($profileId, $request->user()->availableFiscalProfileIds(), true), 422, 'El perfil fiscal seleccionado no esta disponible.');
        }

        return new ReportSettingResource(ReportSetting::current());
    }
}
