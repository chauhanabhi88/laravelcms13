@php
    $langPath = config('pages.lang_path');
@endphp
{{ formStart(null,"POST" ,'admin.page.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
@csrf
@include('core::partials.columns')
@include('core::partials.filters')

    <div class="card-header">
        @include('core::partials.pagination')
    </div>
    <!-- /.card-header -->

    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped table-hover text-nowrap">
            <thead>
                <tr class="data-heading">
                    @include('core::partials.sorting')
                    <th data-sortable="false" class="sticky-action">{{ trans('core::core.titles.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($collection as $page)
                <tr>
                    @can('admin.page.mass_delete')
                    <td>
                    {{ normalCheckbox('selectedCategory[]','',$errors,$page->id  , ['class' => "select-item", 'data-id' => $page->id,'checked' => false ,'grid' => true])}}
                    </td>
                    @endcan
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $page[$columnCode] ?? null;
                        @endphp
                        @if(in_array($columnCode,['title','status']))
                            @php
                                $value = $columnCode == 'status' 
                                        ? (isset($statusOptions) && $statusOptions[$page['status']] ? $statusOptions[$page['status']] : "")
                                        : $page[$columnCode];
                            @endphp
                            <td>{{ wordWrapper($value) }}</td>
                        @elseif($columnCode == 'created_at')
                            <td>{{ getFormatedDate($page->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        <a class="btn" target="_blank" href="{{ config('core.translation_front') ? URL::to('/' . app()->getLocale() . '/' . $page->slug) : URL::to('/'. $page->slug)}}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.preview')}}"><i class="fas fa-eye"></i></span></a>
                        @can('admin.page.edit')
                        <button type="button" onclick="setLocation('{{ route('admin.page.edit', updateUrlParams([$page->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.page.delete')
                        <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.page.delete', updateUrlParams([$page->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.page.edit')
                        <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$page->status] !!}">
                            <label class="switch">
                                <input type="checkbox" class="status" data-id="{{$page->id}}" {{ ($page->status == 1) ? "checked" : ""}}>
                                <span class="slider round"></span>
                            </label>
                        </span>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td align="center" colspan="8">{{ trans("core::core.messages.no_records") }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
<!-- /.card-body -->
{!! formEnd() !!}
