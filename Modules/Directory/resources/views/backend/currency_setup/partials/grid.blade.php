<div class="accordion md-accordion">
    <div class="card card-info card-outline">
        <div class="card-header">
            <a class="btn-tool">
                <h3 class="mb-0">{{ trans("directory::directory.titles.currency_setup") }}</h3>
            </a>
            <div class="card-tools">
                <button class="btn btn-primary btn-fw save" data-form-id="currency_setup_form">{{ trans("core::core.buttons.save") }}</button>
            </div>
        </div>
        <div class="card-body">
            {{ formStart(null,"POST" ,'admin.directory.save' ,updateUrlParams(), ['id' => 'currency_setup_form'])}}
            <div class="form-group">
                <div class="select2-purple">
                    {{ normalSelect("currencySetup[currency][]","directory::directory.labels.allowed_currencies",$errors, $currencyOptions,$allowedCurrencies,  ["id" => "allowed_currencies","class" => "select2 form-control required","multiple" => "multiple"]) }}
                </div>
            </div>
            <div class="form-group">
                {{ normalSelect("currencySetup[base_currency]","directory::directory.labels.base_currency",$errors, [],null,  ["id" => "base_currency","class" => "form-control required","placeholder" => "Select ".trans("directory::directory.labels.base_currency")]) }}
            </div>
            <div class="form-group">
                {{ normalSelect("currencySetup[display_currency]","directory::directory.labels.default_display_currency",$errors, [],null,  ["id" => "display_currency","class" => "form-control required","placeholder" => "Select ".trans("directory::directory.labels.default_display_currency")]) }}
            </div>
            {{ formEnd() }}
        </div>
    </div>
</div>

<div class="accordion md-accordion">
    <div class="card card-info card-outline">
        <div class="card-header">
            <a class="btn-tool">
                <h3 class="mb-0">{{ trans("directory::directory.titles.currency_symbol") }}</h3>
            </a>
            <div class="card-tools">
                <button class="btn btn-primary btn-fw save" data-form-id="currency_symbol_form">{{ trans("core::core.buttons.save") }}</button>
            </div>
        </div>
        <div class="card-body">
            {{ formStart(null,"POST" ,'admin.directory.save' ,updateUrlParams(), ['id' => 'currency_symbol_form'])}}
            @foreach ($currencyData as $currency)
            <div class="form-group col-sm-2 float-left">
                {{ normalText("symbol[$currency->label]",$currency->label, $errors,($currency->symbol) ? $currency->symbol : null,["class" => "form-control required"])}}
            </div>
            @endforeach

            {{ formEnd() }}
        </div>
    </div>
</div>

<div class="accordion md-accordion">
    <div class="card card-info card-outline">
        <div class="card-header">
            <a class="btn-tools">
            <h3 class="mb-0">{{ trans("directory::directory.titles.currency_rate") }}</h3>
            </a>
            <div class="card-tools">
                <button class="btn btn-primary btn-fw save" data-form-id="currency_rate_form">{{ trans("core::core.buttons.save") }}</button>
            </div>
        </div>
        <div class="card-body" style="display: flex;">
            @if(isset($rateData) && !empty($rateData))
            <label class="col-sm-1 card-title" style="line-height: 90px;">1 {!! $baseCurrency !!} =</label>
            @else
            <label class="col-sm-1 card-title" style="line-height: 90px;">{!! $baseCurrency !!}</label>
            @endif
            {{ formStart(null,"POST" ,'admin.directory.save' ,updateUrlParams(), ['id' => 'currency_rate_form'])}}
            @foreach ($rateData as $key => $value)
            <div class="form-group col-sm-2" style="min-width: fit-content;">
                {{ normalText("rate[$key]",$key, $errors,($value) ? $value : null,["class" => "form-control required"])}}
            </div>
            @endforeach

            {{ formEnd() }}
        </div>
    </div>
</div>