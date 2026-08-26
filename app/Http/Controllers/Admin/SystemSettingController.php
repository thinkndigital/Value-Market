<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;

class SystemSettingController extends Controller
{
    public function index()
    {
        return view('admin.pages.forms.system_settings');
    }
}
