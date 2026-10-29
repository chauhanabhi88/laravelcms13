@extends('finance::backend.partials.rule-form', ['entity' => 'fee_rule'])

@section('rule_fields')
    <div class="form-row">
        <div class="form-group col-md-3">
            <label for="code">{{ trans('finance::finance.rule.code') }}</label><span class="asterisk"> *</span>
            <select id="code" name="code" class="custom-select @error('code') is-invalid @enderror">
                @foreach ($codeOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('code', $rule?->code) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('code')<label class="error">{{ $message }}</label>@enderror
        </div>
        <div class="form-group col-md-3">
            <label for="type">{{ trans('finance::finance.rule.type') }}</label><span class="asterisk"> *</span>
            <select id="type" name="type" class="custom-select @error('type') is-invalid @enderror">
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', $rule?->type ?? 'flat') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-3">
            <label for="value">{{ trans('finance::finance.rule.value') }}</label><span class="asterisk"> *</span>
            <input type="number" step="0.01" min="0" id="value" name="value" value="{{ old('value', $rule?->value ?? '0') }}" class="form-control @error('value') is-invalid @enderror" required>
            @error('value')<label class="error">{{ $message }}</label>@enderror
        </div>
        <div class="form-group col-md-3">
            <label for="charged_to">{{ trans('finance::finance.rule.charged_to') }}</label><span class="asterisk"> *</span>
            <select id="charged_to" name="charged_to" class="custom-select">
                @foreach ($chargedToOptions as $value => $label)
                    <option value="{{ $value }}" @selected(old('charged_to', $rule?->charged_to ?? 'platform') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="small text-muted">{{ trans('finance::finance.rule.fee_hint') }}</p>
@endsection
