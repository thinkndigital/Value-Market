@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.ai_setting', 'AI Setting') }}
@endsection
@section('content')
    <x-admin.breadcrumb :title="labels('admin_labels.ai_setting', 'AI Setting')" :subtitle="labels(
        'admin_labels.efficiently_organize_and_control_ai_setting',
        'Efficiently Organize and Control AI Setting',
    )" :breadcrumbs="[
        ['label' => labels('admin_labels.settings', 'Settings'), 'url' => route('settings.index')],
        ['label' => labels('admin_labels.ai_setting', 'AI Setting')],
    ]" />
    @php
        use App\Services\MediaService;
    @endphp

    <div class="row gy-2">
        <div class="card">
            <div class="card-body">
                <h5>{{ labels('admin_labels.ai_setting', 'AI Setting') }}
                </h5>
                <form class="submit_form" action="{{ route('ai_settings.store') }}" method="post">
                    @csrf
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ai_method"
                                    value="openrouter_api"<?= isset($settings['ai_method']) && $settings['ai_method'] == 'openrouter_api' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="openrouter_api">
                                    OpenRouter API
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ai_method" value="gemini_api"
                                    <?= isset($settings['ai_method']) && $settings['ai_method'] == 'gemini_api' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="gemini_api">
                                    Google Gemini Ai
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 mt-3">
                        <div class="d-flex justify-content-end flex-wrap gap-10">
                            <button type="submit"
                                class="btn btn-primary">{{ labels('admin_labels.save', 'Save') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="card mt-4">
                <div class="card-body">
                    <form class="submit_form" action="{{ route('gemini_api_key.store') }}" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-12">
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label
                                                for="">{{ labels('admin_labels.gemini_api_key', 'Gemini API Key') }}</label>
                                            <button type="button" class="btn btn-sm btn-primary mb-4"
                                                data-bs-toggle="modal" data-bs-target="#gemini_detail_modal">
                                                Where to find Gemini API Key?
                                            </button>
                                        </div>

                                        <div class="modal fade" id="gemini_detail_modal" tabindex="-1"
                                            aria-labelledby="exampleModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-body">
                                                        <p>1. Open this<a
                                                                href="https://aistudio.google.com/plan_information">
                                                                URL</a>.
                                                        </p>
                                                        <p>2. Click on built with the Gemini API</p>
                                                        <div class="currency_api_details">
                                                            <img alt=""
                                                                src="{{ app(MediaService::class)->getImageUrl('system_images/gemini_api_key_1.png') }}" />
                                                        </div>
                                                        <p>3. Click on "Create API Key"</p>
                                                        <div class="currency_api_details">
                                                            <img alt=""
                                                                src="{{ app(MediaService::class)->getImageUrl('system_images/gemini_api_key_2.png') }}" />
                                                        </div>
                                                        <p>4. You will able to see two options.Select existing project or
                                                            create api key in new project
                                                        </p>
                                                        <div class="currency_api_details">
                                                            <img alt=""
                                                                src="{{ app(MediaService::class)->getImageUrl('system_images/gemini_api_key_3.png') }}" />
                                                        </div>
                                                        <p>5. After click you will get API key, copy and paste it in
                                                            below
                                                            input box
                                                        </p>
                                                        <div class="currency_api_details">
                                                            <img alt=""
                                                                src="{{ app(MediaService::class)->getImageUrl('system_images/gemini_api_key_4.png') }}" />
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="text" name="gemini_api_key" class="form-control" id="gemini_api_key"
                                            placeholder="" onfocus="focused(this)" onfocusout="defocused(this)"
                                            value="{{ isset($gemini_api_key['gemini_api_key']) ? ($allowModification ? $gemini_api_key['gemini_api_key'] : '************') : '' }}">
                                    </div>

                                </div>
                            </div>
                            <div class="col-md-12 mt-3">
                                <div class="d-flex justify-content-end flex-wrap gap-10">
                                    <button type="submit"
                                        class="btn btn-primary">{{ labels('admin_labels.save', 'Save') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mt-4">
                <div class="card-body">
                    <form class="submit_form" action="{{ route('openrouter_api_key.store') }}" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="col-md-12">
                                    <div class="form-group mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label
                                                for="">{{ labels('admin_labels.openrouter_api_key', 'OpenRouter API Key') }}</label>
                                            <button type="button" class="btn btn-sm btn-primary mb-4"
                                                data-bs-toggle="modal" data-bs-target="#openrouter_detail_modal">
                                                Where to find OpenRouter API Key?
                                            </button>
                                        </div>

                                        <div class="modal fade" id="openrouter_detail_modal" tabindex="-1"
                                            aria-labelledby="exampleModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-body">
                                                        <p>1. You have to sign up at this <a
                                                                href="https://openrouter.ai/">URL</a>.
                                                        </p>
                                                        <p>2. After signing in, you will be able to see the "keys" menu
                                                            in the header click on that</p>
                                                        <div class="currency_api_details">
                                                            <img alt=""
                                                                src="{{ app(MediaService::class)->getImageUrl('system_images/openrouter_api_key_1.png') }}" />
                                                        </div>
                                                        <p>3. Click on "Create Key", and you will be able to see the app ID.
                                                            Copy
                                                            that ID and paste it into the input box provided below:</p>
                                                        <div class="currency_api_details">
                                                            <img alt=""
                                                                src="{{ app(MediaService::class)->getImageUrl('system_images/openrouter_api_key_2.png') }}" />
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="text" name="openrouter_api_key" class="form-control"
                                            id="openrouter_api_key" placeholder="" onfocus="focused(this)"
                                            onfocusout="defocused(this)"
                                            value="{{ isset($openrouter_api_key['openrouter_api_key']) ? ($allowModification ? $openrouter_api_key['openrouter_api_key'] : '************') : '' }}">
                                    </div>

                                </div>
                            </div>
                            <div class="col-md-12 mt-3">
                                <div class="d-flex justify-content-end flex-wrap gap-10">
                                    <button type="submit"
                                        class="btn btn-primary">{{ labels('admin_labels.save', 'Save') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
