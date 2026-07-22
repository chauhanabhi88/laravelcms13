@php
    $langPath = 'blog::blog_post_comment.labels';
@endphp
{{ formStart(null,"POST" ,'admin.blog_post_comment.filters' ,updateUrlParams(), ['id' => 'search_frm'])}}
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

                @forelse ($collection as $comment)
                    <tr>
                        @can('admin.blog_post_comment.mass_delete')
                            <td>
                            {{ normalCheckbox('selectedCategory[]','',$errors,$comment->id  , ['class' => "select-item", 'data-id' => $comment->id ,'grid' => true])}}
                            </td>
                        @endcan
                        @foreach ($columns as $column)
                        @php 
                            if($column['checkbox_checked'] == 0){
                                continue;
                            }
                            $columnCode = $column['code'];
                            $value = $comment[$columnCode] ?? null;
                        @endphp
                        @if($columnCode =='title')
                            <td>{{ wordWrapper($comment[$columnCode]) }}</td>
                        @elseif(in_array($columnCode,['subject','first_name','status','title']))
                        @php
                            $value = $columnCode == 'first_name' 
                                    ?  $comment->first_name. " " .$comment->last_name
                                    :  ($columnCode == 'status' 
                                        ? (isset($statusOptions) && !empty($statusOptions) ? wordWrapper($statusOptions[$comment->status]) : " ")
                                        : $comment['code']);
                        
                        @endphp
                            <td>{{ wordWrapper($value) }}</td>
                        @elseif($columnCode == 'created_at')
                            <td>{{ getFormatedDate($comment->created_at, getGridDateFormat()) }}</td>
                        @else
                            <td>{{$value}}</td>
                        @endif
                    @endforeach
                        <td class="sticky-action">
                            @can('admin.blog_post_comment.edit')
                                <button type="button" onclick="setLocation('{{ route('admin.blog_post_comment.edit', updateUrlParams([$comment->id])) }}');" class="btn"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.edit')}}"><i class="fas fa-edit"></i></span></button>
                            @endcan
                            @can('admin.blog_post_comment.delete')
                                <button type="button" class="btn" data-toggle="modal" data-target="#modal-delete-confirmation" data-action-target="{{ route('admin.blog_post_comment.delete', updateUrlParams([$comment->id])) }}"><span data-placement="right" data-toggle="tooltip" title="{{trans('core::core.labels.delete')}}"><i class="fas fa-trash"></i></span></button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td align="center" colspan="10"> {{ trans("core::core.messages.no_records") }} </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
{!! formEnd() !!}
