@php
    $langPath = config('block.lang_path');
@endphp
{{ formStart(null,"POST" ,'admin.block.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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
                @forelse ($collection as $block)
                <tr>
                    @can('admin.block.mass_delete')
                    <td>
                    {{ normalCheckbox('selectedCategory[]','',$errors,$block->id  , ['class' => "select-item", 'data-id' => $block->id,'checked' => false ,'grid' => true])}}
                    </td>
                    @endcan
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $block[$columnCode] ?? null;
                        @endphp
                        @if(in_array($columnCode,['title','is_enabled']))
                            @php
                                $value = $columnCode == 'is_enabled' 
                                        ? (isset($statusOptions) && $statusOptions[$block['is_enabled']] ? $statusOptions[$block['is_enabled']] : "")
                                        : $block[$columnCode];
                            @endphp
                            <td>{{ wordWrapper($value) }}</td>
                        @elseif($columnCode == 'created_at')
                            <td>{{ getFormatedDate($block->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.block.edit')
                        <button type="button" onclick="setLocation('{{ route('admin.block.edit', updateUrlParams([$block->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.block.delete')
                        <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.block.delete', updateUrlParams([$block->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endcan
                        @can('admin.block.edit')
                        <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$block->is_enabled] !!}">
                            <label class="switch">
                                <input type="checkbox" class="status" data-id="{{$block->id}}" {{ ($block->is_enabled == 1) ? "checked" : ""}}>
                                <span class="slider round"></span>
                            </label>
                        </span>
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
