<div class="accordion md-accordion" id="accordionEx" role="tablist" aria-multiselectable="true">
    <div class="card">
        <div class="card-header change-pointer collapsed" role="tab" id="headingOne1" data-toggle="collapse" data-target="#collapseOne1" aria-expanded="false">
            <a class="btn-tool">
                <h5 class="mb-0">{{trans('core::core.labels.filter')}}</h5>
            </a>
        </div>
        <div class="card-body table-responsive">
            <div class="col-md-12 filter-main">
                <div class="row">
                    <div class="col-2">
                    {{ trans("language::language.titles.module") }}
                    </div>
                    <div class="col-6">
                        <select class="form-control required translation_module" name="translation_module">
                            @if(isset($data) && !empty($data))
                            @foreach($data as $key => $module)
                            <option value='{{$key}}' {{$moduleName == $key ? "selected='selected'" : '' }}>{{$module}}</option>
                            @endforeach
                            @endif
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-group col-md-5 mt-2">
            <button type="button" class="btn btn-primary translation_module_submit_button btn-fw">{{trans('core::core.buttons.search')}}</button>
            <button type="button" class="btn btn-secondary translation_module_reset_button btn-fw">{{trans('core::core.buttons.reset')}}</button>

        </div>
    </div>
</div>
<div class="card card-info card-outline">
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped table-hover text-nowrap" id="transTable">
            <thead>
                <tr class="data-heading">
                    <th>{{ trans("language::language.labels.label") }}</th>
                    @foreach ($languages as $key => $value)
                    <th>{{$key}}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @if(isset($result) && !empty($result))
                @forelse ($result as $value)
                <tr>
                    <td>{{$value['display']}}</td>
                    @foreach ($languages as $key => $langValue)
                    <td style="position:relative;"><a href="#" class="translation" data-pk="{{ $key }}__-__{{ $value['display'] }}">{{ (array_key_exists($key,$value))?$value[$key]:''}}</a></td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td align="center" colspan="7"> {{ trans("core::core.messages.no_records") }} </td>
                </tr>
                @endforelse
                @endif
            </tbody>
        </table>
    </div>
    <!-- /.card-body -->
</div>
@push('js-stack')
<script type="text/javascript">
    $(document).ready(function() {
        $('#transTable').DataTable({
            "pageLength": 50
        });

    });
    $(".translation_module_submit_button").click(function(){
        var selectedModule = $(".translation_module").children("option:selected").val();
        if(selectedModule.length === 0){
            $(location).attr("href","{{route('admin.translation.index',updateUrlParams([]))}}");
        }else{
            $(location).attr("href","{{route('admin.translation.index',updateUrlParams([]))}}"+"?moduleName="+selectedModule);
        }
    });
    $(".translation_module_reset_button").click(function(){
        $(location).attr("href","{{route('admin.translation.index',updateUrlParams([]))}}");
    });

    $('.translation').editable({
        url: function(params) {
            var splitKey = params.pk.split("__-__");
            var locale = splitKey[0];
            var key = splitKey[1];
            var value = params.value;
            if (!locale || !key) {
                return false;
            }

            $.ajax({
                url: '{{ route("admin.translation.update",[app()->getLocale()]) }}',
                method: 'POST',
                data: {
                    locale: locale,
                    key: key,
                    value: value,
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    if (res.message == 'sucess') {
                        location.reload();
                    }
                }
            })
        },
        type: 'text',
        mode: 'inline',
        send: 'always',
        /* Always send, because we have no 'pk' which editable expects */

    });
</script>
@endpush
