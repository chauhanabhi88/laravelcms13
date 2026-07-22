<!-- start theme -->

{{ formStart(null,"POST" ,'admin.theme.store' ,updateUrlParams(), ['id' => 'main_form','enctype'=>'multipart/form-data'])}}

    @php
        $themeOptions = getThemes();
        $themeOptions = ($themeOptions) ? $themeOptions : [];
    @endphp
    
    {{ normalHidden("setting",json_encode($themeOptions) , 'setting' ,['id' => 'theme_value'])}}
    <div class="row">
        <div class="col-12">
            <div class="card card-info card-outline">
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped table-hover text-nowrap">
                        <tbody>
                            <tr>
                                <td>{{ normalLabel(trans("theme::theme.labels.bodySmallText"), '' , [])}}</td>
                                <td>
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            {{ normalCheckbox('body-small-text','',$errors, null,  ['class'=>'custom-control-input', 'id'=>'body-small-text' , 'checked' => isset($theme['body']) ? (strpos($theme['body'],'text-sm') !== false) : false]) }}
                                            <label class="custom-control-label" for="body-small-text"></label>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>{{normalLabel(trans("theme::theme.labels.sidebarNav") , '' ,[])}}</td>
                                <td>
                                    <div class="col-4">
                                        {{ normalSelect('sidebar-nav','',$errors, [''=>'Default','nav-flat'=>'Flat Style','nav-child-indent'=>'Child Indent'], getSelected($theme,'.nav-sidebar', array('nav-flat','nav-child-indent')), ['id'=>'sidebar-nav', 'class'=>'custom-select']) }}
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>{{normalLabel(trans("theme::theme.labels.legacyStyle") , '' , [])}}</td>
                                <td>
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            {{  normalCheckbox('legacy-style', '',$errors,null, ['class'=>'custom-control-input', 'id'=>'legacy_style' , 'checked' => isset($theme['.nav-sidebar']) ? (strpos($theme['.nav-sidebar'],'nav-legacy') !== false) : false]) }}
                                            <label class="custom-control-label" for="legacy_style"></label>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>{{normalLabel(trans("theme::theme.labels.sidebarCollapse"), '' , [])}}</td>
                                <td>
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            {{  normalCheckbox('sidebar-collapse', '',,$errors,null, ['class'=>'custom-control-input', 'id'=>'sidebar_collapse', 'checked' => isset($theme['.sidebar-mini']) ? (strpos($theme['.sidebar-mini'],'sidebar-collapse') !== false) : false]) }}
                                            <label class="custom-control-label" for="sidebar_collapse"></label>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                             <tr>
                                <td>{{normalLabel(trans("theme::theme.labels.logo"), '' , [])}}</td>
                                <td>
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            {{  normalCheckbox('defualtlogo', '',$errors,null , ['class'=>'custom-control-input', 'id'=>'canclelogo', 'checked' => (isset($theme['logo'])) ? false :true]) }}
                                            <label class="custom-control-label" for="canclelogo"></label>
                                            {{normalLabel(trans("theme::theme.labels.defualtlogo"), '' , [])}}
                                            <div class="input-group mb-3">
                                                <div class="custom-file">
                                                    {{ normalFile('logo','',$errors,['class'=>'custom-file-label form-control','id'=>'filelogo']) }}
                                                    <label class="custom-file-label" for="filelogo">{{ trans("theme::theme.labels.choose_file") }}</label>
                                                </div>
                                                    <div class="input-group-append">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{trans("theme::theme.labels.navbar_variants")}}</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap mb-3">
                        <div class="bg-primary elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-primary'></div>
                        <div class="bg-secondary elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-secondary' ></div>
                        <div class="bg-info elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-info'></div>
                        <div class="bg-success elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-success'></div>
                        <div class="bg-danger elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-danger'></div>
                        <div class="bg-indigo elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-indigo'></div>
                        <div class="bg-purple elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-purple'></div>
                        <div class="bg-pink elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-pink'></div>
                        <div class="bg-teal elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-teal'></div>
                        <div class="bg-cyan elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-cyan'></div>
                        <div class="bg-dark elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-gray'></div>
                        <div class="bg-gray-dark elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-gray'></div>
                        <div class="bg-gray elevation-2 customeNavbarVariants" data-color='navbar-dark navbar-gray'></div>
                        <div class="bg-light elevation-2 customeNavbarVariants" data-color='navbar-light navbar-white'></div>
                        <div class="bg-warning elevation-2 customeNavbarVariants" data-color='navbar-light navbar-warning'></div>
                        <div class="bg-white elevation-2 customeNavbarVariants" data-color='navbar-light navbar-white'></div>
                        <div class="bg-orange elevation-2 customeNavbarVariants" data-color='navbar-light navbar-orange'></div>
                    </div>
                </div>
            </div>
        
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{trans("theme::theme.labels.dark_sidebar_variants")}}</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap mb-3">
                        <div class="bg-primary elevation-2 darkSidebarVariants" data-color='primary'></div>
                        <div class="bg-warning elevation-2 darkSidebarVariants" data-color='warning'></div>
                        <div class="bg-info elevation-2 darkSidebarVariants" data-color='info'></div>
                        <div class="bg-danger elevation-2 darkSidebarVariants" data-color='danger'></div>
                        <div class="bg-success elevation-2 darkSidebarVariants" data-color='success'></div>
                        <div class="bg-indigo elevation-2 darkSidebarVariants" data-color='indigo'></div>
                        <div class="bg-navy elevation-2 darkSidebarVariants" data-color='navy'></div>
                        <div class="bg-purple elevation-2 darkSidebarVariants" data-color='purple'></div>
                        <div class="bg-fuchsia elevation-2 darkSidebarVariants" data-color='fuchsia'></div>
                        <div class="bg-pink elevation-2 darkSidebarVariants" data-color='pink'></div>
                        <div class="bg-maroon elevation-2 darkSidebarVariants" data-color='maroon'></div>
                        <div class="bg-orange elevation-2 darkSidebarVariants" data-color='orange'></div>
                        <div class="bg-lime elevation-2 darkSidebarVariants" data-color='lime'></div>
                        <div class="bg-teal elevation-2 darkSidebarVariants" data-color='teal'></div>
                        <div class="bg-olive elevation-2 darkSidebarVariants" data-color='olive'></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{trans("theme::theme.labels.light_sidebar_variants")}}</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap mb-3">
                        <div class="bg-primary elevation-2 lightSidebarVariants" data-color='primary'></div>
                        <div class="bg-warning elevation-2 lightSidebarVariants" data-color='warning'></div>
                        <div class="bg-info elevation-2 lightSidebarVariants" data-color='info'></div>
                        <div class="bg-danger elevation-2 lightSidebarVariants" data-color='danger'></div>
                        <div class="bg-success elevation-2 lightSidebarVariants" data-color='success'></div>
                        <div class="bg-indigo elevation-2 lightSidebarVariants" data-color='indigo'></div>
                        <div class="bg-navy elevation-2 lightSidebarVariants" data-color='navy'></div>
                        <div class="bg-purple elevation-2 lightSidebarVariants" data-color='purple'></div>
                        <div class="bg-fuchsia elevation-2 lightSidebarVariants" data-color='fuchsia'></div>
                        <div class="bg-pink elevation-2 lightSidebarVariants" data-color='pink'></div>
                        <div class="bg-maroon elevation-2 lightSidebarVariants" data-color='maroon'></div>
                        <div class="bg-orange elevation-2 lightSidebarVariants" data-color='orange'></div>
                        <div class="bg-lime elevation-2 lightSidebarVariants" data-color='lime'></div>
                        <div class="bg-teal elevation-2 lightSidebarVariants" data-color='teal'></div>
                        <div class="bg-olive elevation-2 lightSidebarVariants" data-color='olive'></div>
                    </div>
                </div>
            </div>
        
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{trans("theme::theme.labels.brand_logo_variants")}}</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap mb-3">
                        <div class="bg-primary elevation-2 brandLogovariants " data-color='primary'></div>
                        <div class="bg-secondary elevation-2 brandLogovariants " data-color='secondary'></div>
                        <div class="bg-info elevation-2 brandLogovariants " data-color='info'></div>
                        <div class="bg-success elevation-2 brandLogovariants " data-color='success'></div>
                        <div class="bg-danger elevation-2 brandLogovariants " data-color='danger'></div>
                        <div class="bg-indigo elevation-2 brandLogovariants " data-color='indigo'></div>
                        <div class="bg-purple elevation-2 brandLogovariants " data-color='purple'></div>
                        <div class="bg-pink elevation-2 brandLogovariants " data-color='pink'></div>
                        <div class="bg-teal elevation-2 brandLogovariants " data-color='teal'></div>
                        <div class="bg-cyan elevation-2 brandLogovariants " data-color='cyan'></div>
                        <div class="bg-dark elevation-2 brandLogovariants " data-color='dark'></div>
                        <div class="bg-gray-dark elevation-2 brandLogovariants " data-color='gray-dark'></div>
                        <div class="bg-gray elevation-2 brandLogovariants " data-color='gray'></div>
                        <div class="bg-light elevation-2 brandLogovariants " data-color='light'></div>
                        <div class="bg-warning elevation-2 brandLogovariants " data-color='warning'></div>
                        <div class="bg-white elevation-2 brandLogovariants " data-color='white'></div>
                        <div class="bg-orange elevation-2 brandLogovariants " data-color='orange'></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{{ formEnd() }}
@push('js-stack')
    <script>
        $('input[type="file"]'). change(function(e){
        var fileName = e. target. files[0]. name;
        $(".custom-file-label").html(fileName);
        });
    </script>
@endpush
