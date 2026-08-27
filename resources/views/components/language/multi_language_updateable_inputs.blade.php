@props(['languages', 'data', 'nameKey', 'nameValue', 'inputName'])
@php
    $decoded = json_decode($data ?? '{}');
@endphp
@foreach ($languages as $lang)
    @if ($lang->code !== 'en')
        <div class="tab-pane fade" id="content-{{ $lang->code }}" role="tabpanel"
            aria-labelledby="tab-{{ $lang->code }}">
            <div class="mb-3">
                <label for="{{ $inputName }}_{{ $lang->code }}" class="form-label">
                    {{ labels($nameKey, $nameValue) }} ({{ $lang->language }})
                </label>
                <input type="text" class="form-control" id="{{ $inputName }}_{{ $lang->code }}"
                    name="{{ $inputName }}[{{ $lang->code }}]"
                    value="{{ old($inputName . '.' . $lang->code, $decoded->{$lang->code} ?? '') }}">
            </div>
        </div>
    @endif
@endforeach
