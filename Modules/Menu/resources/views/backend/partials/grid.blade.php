
{{ formStart(null,"POST" ,'admin.menu.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
    @csrf
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
                    <th data-sortable="false">{{ trans('core::core.titles.actions') }}</th>
                </tr>
            </thead>
            <tbody>

                @forelse ($collection as $menu)
                    <tr>
                        @can('admin.menu.mass_delete')
                            <td>
                            {{ normalCheckbox('selectedCategory[]','',$errors,$menu->id  , ['class' => "select-item", 'data-id' => $menu->id ,'grid' => true])}}
                            </td>
                        @endcan
                        <td>{{ $menu->id }}</td>
                        							<td> {{ wordWrapper($menu->title) }} </td>
							<td> {{ wordWrapper($menu->link) }} </td>

                        <td>{{ getFormatedDate($menu->created_at, getGridDateFormat()) }}</td>
                        <td>
                            @can('admin.menu.edit')
                                <button type="button" onclick="setLocation('{{ route('admin.menu.edit', updateUrlParams([$menu->id])) }}');" class="btn"><i class="fas fa-edit"></i></button>
                            @endcan
                            @can('admin.menu.delete')
                                <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.menu.delete', updateUrlParams([$menu->id])) }}"><i class="fas fa-trash"></i></button>
                            @endcan
                            @can('admin.menu.edit')
                            <span data-placement="right" data-toggle="tooltip"  title="{!! $statusOptions[$menu->status] !!}">
                                <label class="switch">
                                    <input type="checkbox" class="status" data-id="{{$menu->id}}" {{ ($menu->status == 1) ? "checked" : ""}}>
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
    <!-- /.card-body -->
{!! formEnd() !!}
