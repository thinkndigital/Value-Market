{{--
    Renders one input per active CustomField (Task: "Custom Fields" - store-defined extra attributes a
    product can carry, e.g. a text/number/file/date/radio/dropdown/checkbox/color/textarea value). Included
    by both the "add" forms (products.blade.php, combo_products.blade.php, admin and seller) - always blank,
    never pre-filled; the edit forms use components.product.update_custom_fields instead.

    Field names match exactly what Admin\ProductController::store()/Seller\ProductController::store() (and
    the combo-product equivalents) read back: custom_fields[{field id}][0][value].
--}}
@if (isset($customFields) && $customFields->count())
    <div class="row mt-4">
        <h6>{{ labels('admin_labels.custom_fields', 'Custom Fields') }}</h6>
        @foreach ($customFields as $field)
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
                                @if ($field->field_length) maxlength="{{ $field->field_length }}" @endif></textarea>
                        @break

                        @case('number')
                            <input type="number" class="form-control" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]"
                                @if ($field->min !== null) min="{{ $field->min }}" @endif
                                @if ($field->max !== null) max="{{ $field->max }}" @endif
                                @if ($field->required) required @endif>
                        @break

                        @case('date')
                            <input type="date" class="form-control" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]"
                                @if ($field->required) required @endif>
                        @break

                        @case('color')
                            <input type="color" class="form-control form-control-color" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]"
                                @if ($field->required) required @endif>
                        @break

                        @case('file')
                            <input type="file" class="form-control" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]"
                                @if ($field->required) required @endif>
                        @break

                        @case('radio')
                            <div>
                                @foreach ($field->options ?? [] as $optionIndex => $option)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio"
                                            id="custom_field_{{ $field->id }}_{{ $optionIndex }}"
                                            name="custom_fields[{{ $field->id }}][0][value]" value="{{ $option }}"
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
                                    <option value="{{ $option }}">{{ $option }}</option>
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
                                            value="{{ $option }}">
                                        <label class="form-check-label"
                                            for="custom_field_{{ $field->id }}_{{ $optionIndex }}">{{ $option }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @break

                        @default
                            <input type="text" class="form-control" id="custom_field_{{ $field->id }}"
                                name="custom_fields[{{ $field->id }}][0][value]"
                                @if ($field->field_length) maxlength="{{ $field->field_length }}" @endif
                                @if ($field->required) required @endif>
                    @endswitch
                </div>
            </div>
        @endforeach
    </div>
@endif
