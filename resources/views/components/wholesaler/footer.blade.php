@php
    use App\Services\SettingService;
    $settings = app(SettingService::class)->getSettings('system_settings', true);
    $settings = json_decode($settings, true);
    $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : 'Value Market';
@endphp
<footer class="footer mt-4 py-3 bg-body">
    <div class="px-4">
        <div class="col-12">
            <div class="text-center">
                <div class="row">
                    <div class="col-md-6">
                        <span class="copyright">
                            Copyright © {{ date('Y') }} <a href="{{ config('app.url') }}">{{ $app_name }}</a>
                            All rights reserved.
                        </span>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="text-end text-muted">
                            <span class="badge bg-primary footer-version-badge d-inline">V.
                                {{ get_current_version() }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
