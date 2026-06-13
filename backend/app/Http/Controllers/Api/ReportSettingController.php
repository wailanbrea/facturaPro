<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReportSettingResource;
use App\Models\ReportSetting;

class ReportSettingController extends Controller
{
    public function show(): ReportSettingResource
    {
        return new ReportSettingResource(ReportSetting::current());
    }
}
