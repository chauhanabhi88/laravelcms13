@php
    $perPage = settings("core", "per_page");
    $perPageSelect = array(); 
    if($perPage) { 
        $perPage = array_filter(explode(",", $perPage));
        sort($perPage);
        foreach ($perPage as $value) {
            $perPageSelect[$value] = $value;
        }
    }
@endphp

{{ normalHidden("order_by",$request->get("order_by", "id") , 'order_by')}}
{{ normalHidden("dir",$request->get("dir", "DESC") , 'dir')}}
{{ normalHidden("last_page",$collection->lastPage() , 'last_page')}}
@php
    $totalRecords = $collection->total();
@endphp
<div class="row">
    <div class="col-sm-12 col-md-7">
        <ul class="pagination">
            <li class="{{ ($collection->currentPage() == 1) ? ' disabled' : '' }}" aria-disabled="true" aria-label="« {{ trans('core::core.buttons.prev') }}">
                @php
                    $prevPage = $collection->currentPage() - 1;
                @endphp
                <a href="javascript:void(0);" aria-hidden="true" data-page="{{ $prevPage }}" class="page-link"><i class="fa fa-caret-left"></i></a>
            </li>
            <li class="page-field">
            
            {{ normalText("page","page", $errors,$collection->currentPage(),['id' => 'current_page', 'class' => 'form-control','hide_label'=>true,"form_div"=>false])}}

            {{ trans('core::core.labels.of')." ".$collection->lastPage() }} 
            </li>
            <li class="{{ ($collection->currentPage() == $collection->lastPage()) ? ' disabled' : '' }}">
                @php
                    $nextPage = $collection->currentPage() + 1;
                @endphp
                <a href="javascript:void(0);" data-page="{{$nextPage}}" aria-label="{{ trans('core::core.buttons.next') }}" class="page-link"><i class="fa fa-caret-right"></i></a>
            </li>
            <li class="record_info">
                <label class="selected_records" id="selected-records">0</label> {{ trans('core::core.labels.record_selected') }}
            </li>
            <li class="record_info">
                @if ($totalRecords == 1)
                    <label class="total_records" id="total-records">{{ $collection->total() }}</label> {{ trans('core::core.labels.record_found') }}
                @else
                    <label class="total_records" id="total-records">{{ $collection->total() }}</label> {{ trans('core::core.labels.records_found') }}
                @endif
            </li>
        </ul>
    </div>
    <div class="col-sm-12 col-md-5">
        <div class="per_page">
            <label>{{ trans('core::core.labels.show') }}</label>
            {{ normalSelect("per_page","per_page",$errors, $perPageSelect,$request->get("per_page", settings("core", "default_per_page")),  ['class' => 'custom-select perpage-select','hide_label'=>true,"form_div"=>false]) }}
            {{ trans('core::core.labels.entries') }}
        </div>
    </div>
</div>
@push('js-stack')
    <script type="text/javascript">
        jQuery("#current_page").val("{{ $collection->currentPage() }}");
        jQuery('body').delegate('tr.data-heading th.default-sort', 'click', function() {
            var dir = 'desc';
            if(jQuery(this).hasClass("sorting_desc") == true) {
                jQuery(this).addClass("sorting_asc");
                dir = 'asc';
            }
            else if(jQuery(this).hasClass("sorting_asc") == true) {
                jQuery(this).addClass("sorting_desc");
            }
            else {
                jQuery(this).addClass("sorting_desc");
            }
            jQuery("#order_by").val(jQuery(this).attr("data-field"));
            jQuery("#dir").val(dir);
            var formId = jQuery("#collection").find("form").attr("id");
            customObj.setFormId(formId).saveForm();
        });

        jQuery('body').delegate('.page-link', 'click', function() {
            if(jQuery(this).parent().hasClass("disabled") == false && jQuery(this).parent().hasClass("active") == false) {
                jQuery("html, body").animate({ scrollTop: 0 }, "slow");
                var page = jQuery(this).attr('data-page');
                var formId = jQuery("#collection").find("form").attr("id");
                var lastPage = jQuery("#last_page").val();
                if(parseInt(page) > parseInt(lastPage)) {
                    page = lastPage;
                }
                sumbmitForm("current_page", page, formId, true);
            }
        });

        jQuery('body').delegate('.perpage-select', 'change', function() {
            jQuery("html, body").animate({ scrollTop: 0 }, "slow");
            var perPage = jQuery(this).val();
            jQuery("#current_page").val(1);
            var formId = jQuery("#collection").find("form").attr("id");
            sumbmitForm("per_page", perPage, formId, true);
        });
    </script>
@endpush