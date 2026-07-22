@php
    $langPath = config('customer.lang_path');
@endphp
{{ formStart(null, "POST", 'admin.customer.filters', updateUrlParams(), ['id' => 'main_form'])}}
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
                            {{ normalCheckbox('selectedCategory[]', '', $errors, $customer->id, ['class' => "select-item", 'data-id' => $customer->id, 'checked' => false, 'grid' => true])}}
                        </td>
                    @endcan
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $customer[$columnCode] ?? null;
                        @endphp
                        @if($columnCode == 'profile_picture')
                            @php
                                $og_image_param = [
                                    'module' => \Config::get('customer.name'),
                                    'image' => $customer->profile_picture,
                                ];
                                $thumbnail_image_param = [
                                    'image-type' => 'thumbnail',
                                    'module' => Config::get('customer.name'),
                                    'image' => $customer->profile_picture,
                                    'defualt-image' => true,
                                ];
                            @endphp
                            <td>
                                @if(getImageUrl($og_image_param))
                                    <a href="{{getImageUrl($og_image_param)}}" target="_BLANK">
                                        <img src="{{getImageUrl($thumbnail_image_param)}}" width=100 alt="introduction">
                                    </a>
                                @else
                                    <img src="{{getImageUrl($thumbnail_image_param)}}" width=100 alt="introduction">
                                @endif
                            </td>
                        @elseif(in_array($columnCode,['first_name','email']))
                            <td>{{ wordWrapper($customer[$columnCode]) }}</td>
                        @elseif($columnCode == 'created_at')
                            <td>{{ getFormatedDate($customer->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.customer.edit')
                            <button type="button"
                                onclick="setLocation('{{ route('admin.customer.edit', updateUrlParams([$customer->id])) }}');"
                                class="btn"><span data-placement="right" data-toggle="tooltip"
                                    title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.customer.delete')
                            <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation"
                                data-action-target="{{ route('admin.customer.delete', updateUrlParams([$customer->id])) }}"><span
                                    data-placement="right" data-toggle="tooltip"
                                    title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.customer.edit')
                            <span data-placement="right" data-toggle="tooltip"
                                title="{!! $statusOptions[$customer->status] !!}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{$customer->id}}" <?php            echo ($customer->status == 1) ? "checked" : "" ?>>
                                    <span class="slider round"></span>
                                </label>
                            </span>
                        @endcan
                    </td>
                     @if((settings('core', 'email_verification') == config('core.yes')))
                            @if((!empty($customer->email_verified_at)))
                                <td align='center'><span class='title' data-placement='bottom' data-toggle='tooltip'
                                        title="{{ trans('customer::customer.labels.email_verified') }}"><i
                                            class="fa fa-check-circle email-verified-icon"></i></span></td>
                            @else
                                <td align='center'><span class='title' data-placement='bottom' data-toggle='tooltip'
                                        title="{{ trans('customer::customer.labels.email_unverified') }}"><i
                                            class="fa fa-check-circle email-unverified-icon"></i></span></td>
                            @endif
                        @endif
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