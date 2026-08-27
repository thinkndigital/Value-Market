@props(['languages'])
@foreach ($languages as $lang)
    @if ($lang->code !== 'en')
        <li class="nav-item" role="presentation">
            <button class="language-nav-link nav-link" id="tab-{{ $lang->code }}" data-bs-toggle="tab"
                data-bs-target="#content-{{ $lang->code }}" type="button" role="tab"
                aria-controls="content-{{ $lang->code }}" aria-selected="false">
                {{ $lang->language }}
            </button>
        </li>
    @endif
@endforeach
