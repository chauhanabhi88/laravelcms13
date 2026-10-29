{{-- Scope, category, seller, effective dates and status fields shared by the commission and fee rule forms ($rule may be null). --}}
<div class="form-row">
    <div class="form-group col-md-4">
        <label for="scope">{{ trans('finance::finance.rule.scope') }}</label><span class="asterisk"> *</span>
        <select id="scope" name="scope" class="custom-select @error('scope') is-invalid @enderror">
            @foreach ($scopes as $value => $label)
                <option value="{{ $value }}" @selected(old('scope', $rule?->scope ?? 'global') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('scope')<label class="error">{{ $message }}</label>@enderror
    </div>
    <div class="form-group col-md-4">
        <label for="catalog_category_id">{{ trans('finance::finance.rule.catalog_category_id') }}</label>
        <select id="catalog_category_id" name="catalog_category_id" class="custom-select @error('catalog_category_id') is-invalid @enderror">
            <option value="">{{ trans('finance::finance.rule.any') }}</option>
            @foreach ($categoryOptions as $value => $label)
                <option value="{{ $value }}" @selected((string) old('catalog_category_id', $rule?->catalog_category_id) === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('catalog_category_id')<label class="error">{{ $message }}</label>@enderror
    </div>
    <div class="form-group col-md-4">
        <label for="seller_id">{{ trans('finance::finance.rule.seller_id') }}</label>
        <select id="seller_id" name="seller_id" class="custom-select @error('seller_id') is-invalid @enderror">
            <option value="">{{ trans('finance::finance.rule.any') }}</option>
            @foreach ($sellerOptions as $value => $label)
                <option value="{{ $value }}" @selected((string) old('seller_id', $rule?->seller_id) === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('seller_id')<label class="error">{{ $message }}</label>@enderror
    </div>
</div>
<p class="small text-muted">{{ trans('finance::finance.rule.scope_hint') }}</p>
<div class="form-row">
    <div class="form-group col-md-4">
        <label for="effective_from">{{ trans('finance::finance.rule.effective_from') }}</label><span class="asterisk"> *</span>
        <input type="date" id="effective_from" name="effective_from" value="{{ old('effective_from', $rule?->effective_from?->toDateString() ?? now()->toDateString()) }}" class="form-control @error('effective_from') is-invalid @enderror" required>
        @error('effective_from')<label class="error">{{ $message }}</label>@enderror
    </div>
    <div class="form-group col-md-4">
        <label for="effective_to">{{ trans('finance::finance.rule.effective_to') }}</label>
        <input type="date" id="effective_to" name="effective_to" value="{{ old('effective_to', $rule?->effective_to?->toDateString()) }}" class="form-control @error('effective_to') is-invalid @enderror">
        @error('effective_to')<label class="error">{{ $message }}</label>@enderror
    </div>
    <div class="form-group col-md-4">
        <label for="status">{{ trans('finance::finance.rule.status') }}</label>
        <select id="status" name="status" class="custom-select">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected((string) old('status', $rule?->status ?? config('core.enabled')) === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
