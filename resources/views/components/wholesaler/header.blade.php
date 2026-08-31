<div class="header @@classList bg-white">
    <link rel="stylesheet" href="{{ asset('/assets/boxicons/css/boxicons.css') }}">

    <!-- navbar -->
    <!-- Navbar -->
    @php

        use App\Models\Store;
        use Illuminate\Support\Facades\Auth;
        use App\Services\MediaService;
        $user = Auth::user();
        $user_image =
            !empty($user->image) && file_exists(public_path(config('constants.USER_IMG_PATH') . $user->image))
                ? app(MediaService::class)->getMediaImageUrl($user->image, 'USER_IMG_PATH')
                : app(MediaService::class)->getImageUrl('no-user-img.jpeg', '', '', 'image', 'NO_USER_IMAGE');
        use App\Models\Language;
        $languages = Language::all();

        $stores = Store::where('is_default_store', 1)->where('status', 1)->get();
    @endphp


    <nav class="navbar navbar-main navbar-expand-lg px-0 shadow-none border-radius-xl" id="navbarBlur" data-scroll="false">
        <input type="hidden" id="app_url" data-app-url="{{ config('app.url') }}" />
        <div class="col-md-12">
            <div class="row">
                <div class="col-md-4 d-flex justify-content-start">
                    <a id="nav-toggle" class="mx-2" href="#"><i class='bx bx-bar-chart bx-rotate-90'></i></a>
                </div>
                <div class="d-flex col-md-8 justify-content-end align-items-center">
                    @php
                        $language_code = session()->get('locale') ?? 'en';
                        $selected_language_rows = fetchDetails(Language::class, ['code' => $language_code], 'language');
                        // fetchDetails() always returns an Eloquent Collection - empty()/isset() are always
                        // true for an object regardless of contents, so this guard never caught a missing
                        // row. Same bug/fix as components/admin/header.blade.php.
                        $selected_language = $selected_language_rows->isNotEmpty()
                            ? $selected_language_rows[0]->language
                            : 'English';
                    @endphp
                    @if (!empty($selected_language))
                        <label for=""class="badge bg-primary mx-3">{{ $selected_language }}</label>
                    @endif

                    <li class="nav-item dropdown  d-flex justify-content-center me-3 notifiationDropDown">
                        <a href="javascript:;" class="nav-link p-0" id="languageDropdown" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <i class="bx bx-globe"></i>
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end  px-2 py-3 me-sm-n4"
                            aria-labelledby="languageDropdown">
                            @foreach ($languages as $language)
                                <li>
                                    <a class="dropdown-item changeLang" data-lang-code="{{ $language->code }}">
                                        {{ ucwords($language->language) }} - {{ strtoupper($language->code) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>

                    <div class="d-flex">

                        <div id="wholesaler_profile" class="input-group-text">
                            <li class="nav-item dropdown pe-2 d-flex align-items-center">
                                <a href="javascript:;" class="nav-link text-white p-0 nav-link-text ms-1"
                                    id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img class="avatar rounded-circle avatar-sm" src="{{ $user_image }}">
                                    {{ $user->username }}
                                    <i class="fas fa-angle-down"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                                    <li>
                                        <a class="dropdown-item text-dark" href="{{ route('wholesaler.logout') }}"><i
                                                class='bx bx-log-in-circle'></i>{{ labels('admin_labels.logout', 'Logout') }}</a>
                                    </li>
                                </ul>
                            </li>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>




    @php

        // Brand palette sampled from the Value Market logo mark (charcoal ink + gold) - same fallback
        // as x-admin.header, used whenever a store hasn't picked its own colors in Store settings.
        $primary_colour =
            isset($stores[0]->primary_color) && !empty($stores[0]->primary_color)
                ? $stores[0]->primary_color
                : '#333F49';
        $background_opacity_color = $primary_colour . '10';
        $secondary_color =
            isset($stores[0]->secondary_color) && !empty($stores[0]->secondary_color)
                ? $stores[0]->secondary_color
                : '#1B2128';
        $hover_color =
            isset($stores[0]->hover_color) && !empty($stores[0]->hover_color) ? $stores[0]->hover_color : '#4B5A67';
        $active_color =
            isset($stores[0]->active_color) && !empty($stores[0]->active_color) ? $stores[0]->active_color : '#20262C';

        // See x-admin.header for why this triplet is needed alongside --primary-theme-color.
        $hexToRgbTriplet = function ($hex) {
            $hex = ltrim((string) $hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
                return '51, 63, 73';
            }
            return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
        };
        $primary_theme_rgb = $hexToRgbTriplet($primary_colour);
    @endphp

    <style>
        * {
            --primary-theme-color: <?=$primary_colour ?>;
            --primary-theme-color-rgb: <?=$primary_theme_rgb ?>;
            --background_opacity_color: <?=$background_opacity_color ?>;
            --secondary-theme-color: <?=$secondary_color ?>;
            --hover-color: <?=$hover_color ?>;
            --active-color: <?=$active_color ?>;
        }
    </style>

    <!-- End Navbar -->
</div>
