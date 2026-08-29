{{--
    Edit-form counterpart of components.product.custom_fields - same field set/naming, but pre-filled from
    $productCustomFieldValues (ProductCustomFieldValue rows for this product, grouped by custom_field_id -
    see Admin\ProductController::update()/edit()). A field with no saved value yet renders blank, same as
    the "add" form.
--}}
@if (isset($customFields) && $customFields->count())
    <div class="row mt-4">
        <h6>{{ labels('admin_labels.custom_fields', 'Custom Fields') }}</h6>
        @foreach ($customFields as $field)
            @php
                $savedValue = optional(($productCustomFieldValues ?? collect())->get($field->id, collect())->first())->value;
            @endphp
            <div class="col-md-6 mt-3">
                <div class="form-group">
                    <label for="custom_field_{{ $field->id }}" class="form-label">{{ $field->name }}
                        @if ($field->required)
                            <span class='text-asterisks text-sm'>*</span>
                        @endif
                    </label>

                    @switch($field->type)
                        @case('textarea')
                            <textarea class="form-control" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]"
                                @if ($field->required) required @endif
                                @if ($field->field_length) maxlength="{{ $field->field_length }}" @endif>{{ $savedValue }}</textarea>
                        @break

                        @case('number')
                            <input type="number" class="form-control" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]" value="{{ $savedValue }}"
                                @if ($field->min !== null) min="{{ $field->min }}" @endif
                                @if ($field->max !== null) max="{{ $field->max }}" @endif
                                @if ($field->required) required @endif>
                        @break

                        @case('date')
                            <input type="date" class="form-control" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]" value="{{ $savedValue }}"
                                @if ($field->required) required @endif>
                        @break

                        @case('color')
                            <input type="color" class="form-control form-control-color" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]" value="{{ $savedValue ?: '#000000' }}"
                                @if ($field->required) required @endif>
                        @break

                        @case('file')
                            @if ($savedValue)
                                <div class="mb-2"><a href="{{ app(\App\Services\MediaService::class)->getMediaImageUrl($savedValue) }}"
                                        target="_blank">{{ $savedValue }}</a>
                                    <input type="hidden" name="custom_fields[{{ $field->id }}][0][old_value]"
                                        value="{{ $savedValue }}">
                                </div>
                            @endif
                            <input type="file" class="form-control" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]"
                                @if ($field->required && !$savedValue) required @endif>
                        @break

                        @case('radio')
                            <div>
                                @foreach ($field->options ?? [] as $optionIndex => $option)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio"
                                            id="custom_field_{{ $field->id }}_{{ $optionIndex }}"
                                            name="custom_fields[{{ $field->id }}][0][value]" value="{{ $option }}"
                                            @if ((string) $savedValue === (string) $option) checked @endif
                                            @if ($field->required) required @endif>
                                        <label class="form-check-label"
                                            for="custom_field_{{ $field->id }}_{{ $optionIndex }}">{{ $option }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @break

                        @case('dropdown')
                            <select class="form-control form-select" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]"
                                @if ($field->required) required @endif>
                                <option value="">{{ labels('admin_labels.select', 'Select') }}</option>
                                @foreach ($field->options ?? [] as $option)
                                    <option value="{{ $option }}"
                                        @if ((string) $savedValue === (string) $option) selected @endif>{{ $option }}</option>
                                @endforeach
                            </select>
                        @break

                        @case('checkbox')
                            <div>
                                @foreach ($field->options ?? [] as $optionIndex => $option)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox"
                                            id="custom_field_{{ $field->id }}_{{ $optionIndex }}"
                                            name="custom_fields[{{ $field->id }}][{{ $optionIndex }}][value]"
                                            value="{{ $option }}"
                                            @if ((string) $savedValue === (string) $option) checked @endif>
                                        <label class="form-check-label"
                                            for="custom_field_{{ $field->id }}_{{ $optionIndex }}">{{ $option }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @break

                        @default
                            <input type="text" class="form-control" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]" value="{{ $savedValue }}"
                                @if ($field->field_length) maxlength="{{ $field->field_length }}" @endif
                                @if ($field->required) required @endif>
                    @endswitch
                </div>
            </div>
        @endforeach
    </div>
@endif
