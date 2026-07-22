<div class="form-group">
    <div class="col-md-12">
        {!! normalHidden('newEntity', true , '' , []) !!}
        {!! normalText("entity[name]", "core::core.labels.entity_name", $errors, null, ["class" => "form-control required"]) !!}
        {!! normalText("entity[fillable]", "core::core.labels.fillable", $errors, null, ["class" => "form-control required", "append" => trans("core::core.comment.comma_separated")]) !!}
    </div>
</div>

<div class="form-group">
    <div class="col-md-12">
        <button type="button" class="btn btn-primary btn-fw" onclick="addJoin()"> {{ trans('core::core.buttons.add_join') }} </button>
    </div>
</div>

<table id="joinTable" class="table table-hover">
    <thead>
        <tr>
            <th>{{ trans("core::core.labels.join_type") }}</th>
            <th> {{ trans("core::core.labels.module") }} </th>
            <th> {{ trans("core::core.labels.entity") }} </th>
            <th> {{ trans("core::core.labels.foreign_key") }} </th>
            {{-- <th> {{ trans("core::core.labels.action") }} </th> --}}
        </tr>
    </thead>
    <tbody id="joinTbody"></tbody>
</table>
@push('js-stack')
    <script type="text/javascript">
        if(jQuery('#joinTbody').children('tr').length < 1) {
            jQuery('#joinTable').hide(); 
        }
        var i = 0;
        function addJoin() {
            i++
            var html = '<tr>\
                <td>{!! normalSelect("join[#i#][type]","",$errors, $entityOption, NULL, ["id"=>"join[#i#][type]", "class" => "custom-select required" ]) !!}</td>\
                <td>{!! normalSelect("join[#i#][module]","",$errors, $moduleList, NULL, ["id" =>"join[#i#][module]", "class" => "custom-select required moduleSelect", "onChange" => "loadEntity(this)"]) !!}</td>\
                <td>{!! normalSelect("join[#i#][entity]","",$errors, [], NULL, ["id" =>"join[#i#][entity]", "disabled"=>true, "class" => "custom-select required entitySelect",  "onChange" => "loadColumns(this)"]) !!}</td>\
                <td>{!! normalSelect("join[#i#][key]","",$errors, [], NULL, ["id" =>"join[#i#][key]", "disabled"=>true, "class" => "custom-select required columnSelect"]) !!}</td>\
                </tr>';
            html = html.replace(/#i#/g, i);
            jQuery('#joinTbody').append(jQuery(html));
            jQuery('#joinTable').show();
        }

        function removeJoin(element){
            if(jQuery('#joinTbody').children('tr').length < 2)
            {
                jQuery('#joinTable').hide();
            }
            jQuery(element).parent().parent().remove();
        }

        function loadEntity(element)
        {
            if(jQuery(element).val()){
                data = {
                    _token : '{{ csrf_token() }}',
                    moduleName : jQuery(element).val()
                }
                var response = customObj.setParams(data).setUrl('{{ route('admin.entity.loadEntity', updateUrlParams()) }}').setMethod("POST").getContent();
                if(response.type == 'success'){
                    jQuery(element).parent().parent().find('.entitySelect').attr('disabled',false);
                    jQuery(element).parent().parent().find('.entitySelect').html(response.content.html);
                }
            }else{
                jQuery(element).parent().parent().find('.entitySelect').val('');
                jQuery(element).parent().parent().find('.entitySelect').attr('disabled',true);

                jQuery(element).parent().parent().find('.columnSelect').val('');
                jQuery(element).parent().parent().find('.columnSelect').attr('disabled',true);
            }
        }

        function loadColumns(element){
            if(jQuery(element).val()){
                var moduleName = jQuery(element).parent().parent().find('.moduleSelect').val();
                data = {
                    _token : '{{ csrf_token() }}',
                    entityName : jQuery(element).val(),
                    moduleName : moduleName
                }
                var response = customObj.setParams(data).setUrl('{{ route('admin.entity.loadColumns', updateUrlParams()) }}').setMethod("POST").getContent();
                if(response.type == 'success'){
                    jQuery(element).parent().parent().find('.columnSelect').attr('disabled',false);
                    jQuery(element).parent().parent().find('.columnSelect').html(response.content.html);
                }
            } else{
                jQuery(element).parent().parent().find('.columnSelect').val('');
                jQuery(element).parent().parent().find('.columnSelect').attr('disabled',true);
            }
        }
        jQuery('#main_form').validate();
    </script>
@endpush
