@extends('finance::backend.partials.rule-form', ['entity' => 'commission_rule'])

@section('rule_fields')
    <div class="form-row">
        <div class="form-group col-md-4">
            <label for="type">{{ trans('finance::finance.rule.type') }}</label><span class="asterisk"> *</span>
            <select id="type" name="type" class="custom-select @error('type') is-invalid @enderror">
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', $rule?->type ?? 'percent') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('type')<label class="error">{{ $message }}</label>@enderror
        </div>
        <div class="form-group col-md-4">
            <label for="value">{{ trans('finance::finance.rule.value') }}</label><span class="asterisk"> *</span>
            <input type="number" step="0.01" min="0" id="value" name="value" value="{{ old('value', $rule?->value ?? '0') }}" class="form-control @error('value') is-invalid @enderror" required>
            @error('value')<label class="error">{{ $message }}</label>@enderror
        </div>
        <div class="form-group col-md-4">
            <label for="applies_to">{{ trans('finance::finance.rule.applies_to') }}</label><span class="asterisk"> *</span>
            <select id="applies_to" name="applies_to" class="custom-select">
                @foreach ($appliesToOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('applies_to', $rule?->applies_to ?? 'item_price') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
@endsection
