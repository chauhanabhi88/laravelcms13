@php
    $langPath = config('customer.lang_path');
@endphp
{{ formStart(null,"POST" ,'admin.customer.deletedcustomerfilters' ,updateUrlParams(), ['id' => 'search_frm','enctype'=>'multipart/form-data'])}}
@csrf
@include('core::partials.columns')
@include('core::partials.filters')


    <div class="card-header">
        @include('core::partials.pagination')
    </div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped table-hover text-nowrap">
            <thead>
                <tr class="data-heading">
                    @include('core::partials.sorting')
                    <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($collection as $customer)
                <tr>
                    @can('admin.customer.mass_delete')
                    <td>
                    {{ normalCheckbox('selectedCategory[]','',$errors,$customer->id  , ['class' => "select-item", 'data-id' => $customer->id,'checked' => false ,'grid' => true])}}
                    </td>
                    @endcan
                    <!-- display selected columns data -->
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $customer[$columnCode] ?? null;
                        @endphp
                        @if($columnCode == 'profile_picture')
                            <td>
                                @php
                                $og_image_param = [
                                'module' => Config::get('customer.name'),
                                'image' => $customer->profile_picture,
                                ];
                                $thumbnail_image_param = [
                                'image-type' => 'thumbnail',
                                'module' => Config::get('customer.name'),
                                'image' => $customer->profile_picture,
                                'defualt-image' => true,
                                ];
                                @endphp
                                @if(getImageUrl($og_image_param))
                                <a href="{{getImageUrl($og_image_param)}}" target="_BLANK">
                                    <img src="{{getImageUrl($thumbnail_image_param)}}" height=100 width=150 alt="introduction">
                                </a>
                                @else
                                <img src="{{getImageUrl($thumbnail_image_param)}}" height=100 width=150 alt="introduction">
                                @endif
                            </td>
                        @elseif(in_array($columnCode, ['created_at','deleted_at']))
                                <td>{{ getFormatedDate($customer[$columnCode], getGridDateFormat()) }}</td>
                        @elseif(in_array($columnCode, ['first_name','email']))
                        @php
                            $value = $columnCode == 'first_name' ? $customer->first_name." ".$customer->last_name : $customer[$columnCode];
                        @endphp
                                <td>{{ wordWrapper($value) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action td-center">
                        @can('admin.customer.restore')
                        <button type="button" class="btn" data-message="{{ trans('customer::customer.messages.restore_modal') }}" data-toggle="modal" data-target="#modal-restore" data-action-target="{{ route('admin.customer.restore',updateUrlParams([encrypt_It($customer->id)])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.restore')}}"><i class='fas fa-trash-restore'></i></span></button>
                        @endcan

                        @can('admin.deletedCustomer.delete')
                        <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.deletedCustomer.delete', updateUrlParams([$customer->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td align="center" colspan="8"> {{ trans("core::core.messages.no_records") }} </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
{!! formEnd() !!}
