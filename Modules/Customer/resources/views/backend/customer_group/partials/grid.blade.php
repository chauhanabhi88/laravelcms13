@php
    $langPath = config('customer.lang_path');
@endphp

{{ formStart(null,"POST" ,'admin.customer.group.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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
                @forelse ($collection as $customergroup)
                <tr>
                    @can('admin.customer.group.mass_delete')
                    <td>
                        @if(!in_array($customergroup->id, $notDeleteIds))
                        {{ normalCheckbox('selectedCategory[]','',$errors,$customergroup->id  , ['class' => "select-item", 'data-id' => $customergroup->id,'checked' => false ,'grid' => true])}}
                        @endif
                    </td>
                    @endcan
                    @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $customergroup[$columnCode] ?? null;
                        @endphp
                        @if($columnCode == 'created_at')
                            <td>{{ getFormatedDate($customergroup->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                    <td class="sticky-action">
                        @can('admin.customer.group.edit')
                        <button type="button" onclick="setLocation('{{ route('admin.customer.group.edit', updateUrlParams([$customergroup->id])) }}');" class="btn" title="{{ trans('customer::customer_group.labels.edit') }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                        @endcan
                        @can('admin.customer.group.delete')
                        @if(!in_array($customergroup->id, $notDeleteIds))
                        <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.customer.group.delete', updateUrlParams([$customergroup->id])) }}" title="{{ trans('customer::customer_group.labels.delete') }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                        @endif
                        @endcan
                        @can('admin.customer.group.edit')
                        <span data-placement="right" data-toggle="tooltip" title="{!! $statusOptions[$customergroup->is_default] !!}">
                            <label class="switch">
                                <input type="checkbox" class="status" data-id="{{$customergroup->id}}" <?php echo ($customergroup->is_default == 1) ? "checked" : "" ?>>
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
<!-- </div> -->
<!-- /.card-body -->
{!! formEnd() !!}
