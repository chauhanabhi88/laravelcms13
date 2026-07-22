@php
    $langPath = config('banner.lang_path');
@endphp

{{ formStart(null,"POST" ,'admin.banner.filters' ,updateUrlParams(), ['id' => 'main_form'])}}
@csrf
@include('core::partials.columns')
@include('core::partials.filters')

<!-- /.card-header -->

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
                @forelse ($collection as $banner)
                <tr>
                    @can('admin.banner.mass_delete')
                    
                    <td>{{ normalCheckbox('selectedCategory[]','',$errors,$banner->id  , ['class' => "select-item", 'data-id' => $banner->id ,'grid' => true])}}</td>
                    <!-- <td><input type="checkbox" name="selectedCategory[]" value="{{$banner->id}}" id="<?php //echo "item_".$banner->id; ?>" class="select-item" data-id=<?php //echo $banner->id; ?> ></td> -->
                    @endcan
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $banner[$columnCode] ?? null;
                        @endphp
                        @if($columnCode == 'image')
                            <td>
                                @php
                                $og_image_param = [
                                'module' => Config::get('banner.name'),
                                'image' => $banner->image,
                                ];
                                $thumbnail_image_param = [
                                'image-type' => 'thumbnail',
                                'module' => Config::get('banner.name'),
                                'image' => $banner->image,
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
                        @elseif($columnCode=='title')
                            <td>{{ wordWrapper($banner->title, false, 20) }}</td>
                        @elseif($columnCode=='banner_group_title')
                            <td>{{ (isset($bannerGroups[$banner->group_id]) && $bannerGroups[$banner->group_id]) ? wordWrapper($bannerGroups[$banner->group_id], false, 20) : "-" }} </td>
                        @elseif($columnCode == 'created_at')
                            <td>{{ getFormatedDate($banner->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.banner.edit')
                        <button type="button" onclick="setLocation('{{ route('admin.banner.edit', updateUrlParams([$banner->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.banner.delete')
                        <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.banner.delete', updateUrlParams([$banner->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.banner.edit')
                        <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$banner->status] !!}">
                            <label class="switch">
                                <input type="checkbox" class="status" data-id="{{$banner->id}}" {{ ($banner->status == 1) ? "checked" : ""}}>
                                <span class="slider round"></span>
                            </label>
                        </span>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td align="center" colspan="9"> {{ trans("core::core.messages.no_records") }} </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
<!-- /.card-body -->
{{ formEnd() }}