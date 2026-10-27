{{--
    One admin decision with a reason box and optional reason templates.
    $route, $label, $button (bootstrap colour), $templates (list of texts), $required, $confirm.
--}}
@php
    $formId = 'decision-'.\Illuminate\Support\Str::slug($route);
@endphp
<form method="POST" action="{{ route($route, updateUrlParams([$seller->id])) }}" id="{{ $formId }}" class="mb-3 border rounded p-3"
    @isset($confirm) onsubmit="return confirm(@js($confirm));" @endisset>
    @csrf
    <label class="font-weight-bold mb-1" for="{{ $formId }}-reason">{{ $label }}</label>
    @if (! empty($templates))
        <select class="custom-select custom-select-sm mb-2" onchange="if (this.value) { document.getElementById('{{ $formId }}-reason').value = this.value; } this.selectedIndex = 0;">
            <option value="">{{ trans('seller::seller.labels.templates') }}</option>
            @foreach ($templates as $template)
                <option value="{{ $template }}">{{ \Illuminate\Support\Str::limit($template, 70) }}</option>
            @endforeach
        </select>
    @endif
    <textarea name="reason" id="{{ $formId }}-reason" rows="2" maxlength="500" class="form-control mb-2" @if ($required ?? false) required @endif
        placeholder="{{ ($required ?? false) ? trans('seller::seller.labels.reason_placeholder') : trans('seller::seller.labels.note_placeholder') }}"></textarea>
    <button type="submit" class="btn btn-{{ $button }} btn-sm">{{ $label }}</button>
</form>
