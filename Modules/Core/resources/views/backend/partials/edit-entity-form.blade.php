<div class="form-group">
    <div class="col-md-12">
        <div class="form-control">{{ $entity }}</div>
        {!! normalHidden("entity[name]", $entity , '' , []) !!}
        {!! normalHidden("entity[module]", $module, '' , []) !!}
    </div>
</div>

<div class="form-group">
    <div class="col-md-12">
        <button type="button" class="btn btn-primary btn-fw" onclick="addJoin()"> {{ trans('core::core.buttons.add_join') }} </button>
    </div>
</div>

<table id="joinTable" class="table table-hover">
    <thead>
        <tr>
            <th>{{ trans("core::core.labels.join_type") }}</th>
            <th> {{ trans("core::core.labels.module") }} </th>
            <th> {{ trans("core::core.labels.entity") }} </th>
            <th> {{ trans("core::core.labels.foreign_key") }} </th>
            {{-- <th> {{ trans("core::core.labels.action") }} </th> --}}
        </tr>
    </thead>
    <tbody id="joinTbody">
        @foreach ($joinData as $joinItem)
            <tr>
                <td><div class="form-control">{{ (isset($entityOption[$joinItem->join_type]) && $entityOption[$joinItem->join_type]) ? $entityOption[$joinItem->join_type] : "" }}</div></td>
                <td><div class="form-control">{{ $joinItem->target_module }}</div></td>
                <td><div class="form-control">{{ $joinItem->target_entity }}</div></td>
                <td><div class="form-control">{{ $joinItem->target_foreign_key }}</div></td>
                {{-- <td> <button type="button" disabled class="btn"> <i class="fas fa-trash"></i> </button></td> --}}
            </tr>
        @endforeach
    </tbody>
</table>