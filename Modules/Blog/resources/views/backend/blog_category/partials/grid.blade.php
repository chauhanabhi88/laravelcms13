@php
    $langPath = config('blog.lang_path');
@endphp
{{ formStart(null,"POST" ,'admin.blog_category.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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

                @forelse ($collection as $blog_category)
                <tr>
                    @can('admin.blog_category.mass_delete')
                    <td>
                    {{ normalCheckbox('selectedCategory[]','',$errors,$blog_category->id  , ['class' => "select-item", 'data-id' => $blog_category->id ,'grid' => true])}}
                    </td>
                    @endcan
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $blog_category[$columnCode] ?? null;
                        @endphp
                        @if($columnCode =='title')
                            <td>{{ wordWrapper($blog_category[$columnCode]) }}</td>
                        @elseif($columnCode == 'created_at')
                            <td>{{ getFormatedDate($blog_category->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.blog_category.edit')
                        <button type="button" onclick="setLocation('{{ route('admin.blog_category.edit', updateUrlParams([$blog_category->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.blog_category.delete')
                        <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.blog_category.delete', updateUrlParams([$blog_category->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.blog_category.edit')
                        <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$blog_category->status] !!}">
                            <label class="switch">
                                <input type="checkbox" class="status" data-id="{{$blog_category->id}}" {{ ($blog_category->status == 1) ? "checked" : ""}}>
                                <span class="slider round"></span>
                            </label>
                        </span>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td align="center" colspan="5"> {{ trans("core::core.messages.no_records") }} </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
{!! formEnd() !!}