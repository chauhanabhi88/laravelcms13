@extends('theme::layouts.backend.master')

@section('title')
    {{ trans("core::core.titles.entity") }}
@endsection

@section('content-header')
    <!-- Content Header (Page header) -->
    <div class="page-title-header row d-none d-sm-flex">
        <div class="page-header col-sm-12 d-flex pb-4 pt-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">{{ trans("core::core.titles.entity") }}</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-right">
                    <button class="btn btn-secondary btn-fw" onclick="setLocation('{{ route('admin.module.index', [app()->getLocale()]) }}')">{{ trans("core::core.buttons.cancel") }}</button>
                    <button class="btn btn-primary btn-fw save" data-form-id="main_form">{{ trans("core::core.buttons.save") }}</button>
                    <button class="btn btn-primary btn-fw savencontinue" data-form-id="main_form">{{ trans("core::core.buttons.savencontinue") }}</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->
@stop

@section('content')
    <!-- Main content -->
    <div class="container-fluid">
        <!-- /.row -->
        <div class="row">
            <div class="col-12">
                <div class="card card-info card-outline">
                    <!-- /.card-header -->
                    {{ formStart(null,"POST" ,'admin.entity.save' ,updateUrlParams(), ['id' => 'main_form'])}}
                    {{ normalHidden("snc",0 , 'snc' ,['class' => 'snc'])}}
                    <div class="card-body table-responsive" id="entityData">
                        <div class="col-12">
                            <div class="tab-content" id="custom-tabs-three-tabContent">
                                <div class="tab-pane fade show active" id="custom-tabs-three-page-info" page="tabpanel" aria-labelledby="custom-tabs-three-page-info-tab">
                                    {{ normalHidden("entity[module]",$module , '' ,[])}}
                                    <div id="editEntity" class="">
                                        @include('core::backend.partials.create-entity-form')
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{ formEnd() }}
                    {{-- Create Entity Over  --}}
                </div>
                <!-- /.card -->
                {{-- Entity List Start --}}
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <h3 class="card-title">{{ trans("core::core.titles.entities") }}</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        @foreach($entityCollection as $entity)
                            <div class="row mt-2">
                                <div class="col-sm-3"><label class="col-form-label">{{ $entity['name'] }}</label></div>
                                <div class="col-sm-6">{{ get_class($entity['object']) }}</div>
                                <div class="col-sm-3"><a href="javascript:void(0)" data-module="{{ $module }}" data-entity="{{ $entity['name'] }}" onclick="editEntity(this)" class="btn btn-primary"><i class="fa fa-edit"></i></a></div>
                                <!-- /.col -->
                            </div>
                            <!-- /.row -->
                        @endforeach
                    </div>
                </div>
                {{-- Entity List Over Here --}}
            </div>
        </div>
    </div>
    <!-- /.container-fluid -->
    {{-- @include('core::partials.delete-modal') --}}
@stop
@push('js-stack')
    <script type="text/javascript">
        function editEntity(element) 
        {
            var data = {
                entity : jQuery(element).data('entity'),
                module : jQuery(element).data('module'),
                _token : '{{ csrf_token() }}'
            }
            customObj.setParams(data).setMethod('PUT').setUrl(" {{ route('admin.entity.edit', updateUrlParams()) }}").getContent();
        }
    </script>
@endpush
