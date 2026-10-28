{{-- Template form shared by create and edit. $notificationTemplate is null on create. --}}
@php
    $template = $notificationTemplate ?? null;
    $selectedEvent = (string) old('event_key', $template?->event_key ?? '');
    $selectedChannel = (string) old('channel', $template?->channel ?? 'sms');
    $selectedAudience = (string) old('audience', $template?->audience ?? ($catalogue[$selectedEvent]['audience'] ?? 'customer'));
    $selectedStatus = (int) old('status', $template?->status ?? config('core.enabled'));
@endphp
<div class="row">
    <div class="col-lg-8">
        <div class="card card-info card-outline">
            <div class="card-header"><strong>{{ trans('notification::notification_template.titles.general') }}</strong></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="event_key">{{ trans('notification::notification_template.labels.event_key') }}</label><span class="asterisk"> *</span>
                        <select id="event_key" name="event_key" class="custom-select required {{ $errors->has('event_key') ? 'is-invalid' : '' }}">
                            <option value="">-- {{ trans('core::core.labels.select') }} --</option>
                            @foreach ($events as $value => $label)
                                <option value="{{ $value }}" data-audience="{{ $catalogue[$value]['audience'] ?? '' }}" @selected($selectedEvent === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('event_key')<label class="error">{{ $message }}</label>@enderror
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="channel">{{ trans('notification::notification_template.labels.channel') }}</label><span class="asterisk"> *</span>
                        <select id="channel" name="channel" class="custom-select required {{ $errors->has('channel') ? 'is-invalid' : '' }}">
                            @foreach ($channels as $value => $label)
                                <option value="{{ $value }}" @selected($selectedChannel === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('channel')<label class="error">{{ $message }}</label>@enderror
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="audience">{{ trans('notification::notification_template.labels.audience') }}</label><span class="asterisk"> *</span>
                        <select id="audience" name="audience" class="custom-select required {{ $errors->has('audience') ? 'is-invalid' : '' }}">
                            @foreach ($audiences as $value => $label)
                                <option value="{{ $value }}" @selected($selectedAudience === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('audience')<label class="error">{{ $message }}</label>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="title">{{ trans('notification::notification_template.labels.title') }}</label>
                    <input type="text" id="title" name="title" maxlength="150" class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}" value="{{ old('title', $template?->title) }}">
                    <small class="form-text text-muted">{{ trans('notification::notification_template.help.title') }}</small>
                    @error('title')<label class="error">{{ $message }}</label>@enderror
                </div>

                <div class="form-group">
                    <label for="body">{{ trans('notification::notification_template.labels.body') }}</label><span class="asterisk"> *</span>
                    <textarea id="body" name="body" rows="5" maxlength="2000" class="form-control required {{ $errors->has('body') ? 'is-invalid' : '' }}">{{ old('body', $template?->body) }}</textarea>
                    <small class="form-text text-muted">{{ trans('notification::notification_template.help.body') }}</small>
                    @error('body')<label class="error">{{ $message }}</label>@enderror
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="provider_template_id">{{ trans('notification::notification_template.labels.provider_template_id') }}</label>
                        <input type="text" id="provider_template_id" name="provider_template_id" maxlength="60" class="form-control {{ $errors->has('provider_template_id') ? 'is-invalid' : '' }}" value="{{ old('provider_template_id', $template?->provider_template_id) }}">
                        <small class="form-text text-muted">{{ trans('notification::notification_template.help.provider_template_id') }}</small>
                        @error('provider_template_id')<label class="error">{{ $message }}</label>@enderror
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="status">{{ trans('core::core.labels.status') }}</label><span class="asterisk"> *</span>
                        <select id="status" name="status" class="custom-select">
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($selectedStatus === (int) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<label class="error">{{ $message }}</label>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-info card-outline">
            <div class="card-header"><strong>{{ trans('notification::notification_template.titles.placeholders') }}</strong></div>
            <div class="card-body">
                @if ($placeholders !== [])
                    <p class="text-muted mb-2">{{ trans('notification::notification_template.help.placeholders') }}</p>
                    <p>
                        @foreach ($placeholders as $placeholder)
                            <code class="d-inline-block mr-2 mb-1">&#123;&#123;{{ $placeholder }}&#125;&#125;</code>
                        @endforeach
                    </p>
                @endif
                @if ($selectedEvent !== '')
                    <p class="text-muted mb-0">{{ trans('notification::notification_template.help.mail', ['slug' => \Modules\Notification\Support\EventDefinition::mailSlugFor($selectedEvent, $selectedAudience)]) }}</p>
                @else
                    <p class="text-muted mb-0">{{ trans('notification::notification_template.help.body') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

@push('js-stack')
<script type="text/javascript">
    jQuery(document).on('change', '#event_key', function() {
        var audience = $(this).find(':selected').data('audience');
        if (audience) {
            $('#audience').val(audience);
        }
    });
</script>
@endpush
