@php
    $langPath = 'blog::blog_post.labels';
@endphp
{{ formStart(null,"POST" ,'admin.blog_post.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}

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

            @forelse ($collection as $blog_post)
            <tr>
                @can('admin.blog_post.mass_delete')
                <td>
                {{ normalCheckbox('selectedCategory[]','',$errors,$blog_post->id  , ['class' => "select-item", 'data-id' => $blog_post->id ,'grid' => true])}}
                </td>
                @endcan
                @foreach ($columns as $column)
                    @php 
                        if($column['checkbox_checked'] == 0){
                            continue;
                        }
                        $columnCode = $column['code'];
                        $value = $blog_post[$columnCode] ?? null;
                    @endphp
                    @if($columnCode == 'image')
                        <td>
                            @php
                            $og_image_param = [
                            "module" => Config::get("blog.name")."/".Config::get("blog.blog_post_name"),
                            "image" => $blog_post->image,
                            ];
                            $resize_image_param = [
                            "image-type" => "resize",
                            "image-size" => 100,
                            "module" => Config::get("blog.name")."/".Config::get("blog.blog_post_name"),
                            "image" => $blog_post->image,
                            "defualt-image" => true,
                            ];
                            @endphp
                            @if(getImageUrl($og_image_param))
                            <a href="{{getImageUrl($og_image_param)}}" target="_BLANK">
                                <img src="{{getImageUrl($resize_image_param)}}" alt="introduction">
                            </a>
                            @else
                            <img src="{{getImageUrl($resize_image_param)}}" alt="introduction">
                            @endif
                        </td>
                    @elseif(in_array($columnCode,['title','slug','auther']))
                        <td>{{ wordWrapper($blog_post[$columnCode]) }}</td>
                    @elseif($columnCode == 'created_at')
                        <td>{{ getFormatedDate($blog_post->created_at, getGridDateFormat()) }}</td>
                    @else
                        <td>{{$value}}</td>
                    @endif
                @endforeach
                <td class="sticky-action">
                    @can('admin.blog_post.edit')
                    <button type="button" onclick="setLocation('{{ route('admin.blog_post.edit', updateUrlParams([$blog_post->id])) }}');" class="btn btn-flat"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                    @endcan
                    @can('admin.blog_post.delete')
                    <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.blog_post.delete', updateUrlParams([$blog_post->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                    @endcan
                    @can('admin.blog_post.edit')
                    <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$blog_post->status] !!}">
                        <label class="switch">
                            <input type="checkbox" class="status" data-id="{{$blog_post->id}}" {{ ($blog_post->status == 1) ? "checked" : ""}}>
                            <span class="slider round"></span>
                        </label>
                    </span>
                    @endcan
                </td>
            </tr>
            @empty
            <tr>
                <td align="center" colspan="6"> {{ trans("core::core.messages.no_records") }} </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
{!! formEnd() !!}