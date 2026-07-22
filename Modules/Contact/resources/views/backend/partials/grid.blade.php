@php
    $langPath = config('contact.lang_path');
@endphp
{{ formStart(null,"post" ,'admin.contact.filters',updateUrlParams(), ['id' => 'search_frm'])}}
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
                @forelse ($collection as $contact)
                    <tr>
                        @can('admin.contact.mass_delete')
                            
                            <td>{{ normalCheckbox('selectedCategory[]','',$errors,$contact->id  , ['class' => "select-item", 'id' => $contact->id ,'grid' => true,'data-id' => $contact->id])}}</td>
                        @endcan
                        @foreach ($columns as $column)
                            @php 
                                if($column['checkbox_checked'] == 0){
                                    continue;
                                }
                                $columnCode = $column['code'];
                                $value = $contact[$columnCode] ?? null;
                            @endphp
                            @if(in_array($columnCode,['name','email']))
                                <td>{{ wordWrapper($contact[$columnCode],false,40) }}</td>
                            @elseif($columnCode == 'created_at')
                                <td>{{ getFormatedDate($contact->created_at, getGridDateFormat()) }}</td>
                            @else
                                <td>{{$value}}</td>
                            @endif
                        @endforeach
                        <td class="sticky-action">
                            @can('admin.contact.view')
                                <button type="button" class="btn" onclick="setLocation('{{ route('admin.contact.view', updateUrlParams(['id' => $contact->id])) }}');" title="{{ trans('contact::contact.titles.edit_contact') }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-eye"></i></span></button>
                            @endcan

                            @can('admin.contact.delete')
                                <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.contact.delete', updateUrlParams(['id' => $contact->id])) }}" title="{{ trans('contact::contact.titles.delete_contact') }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td align="center" colspan="7"> {{ trans("core::core.messages.no_records") }} </td>
                    </tr>
                @endforelse
                
            </tbody>
        </table>
    </div>
<!-- /.card-body -->
{{ formEnd()}}