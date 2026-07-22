
{{ formStart(null,"POST" ,'' ,updateUrlParams(), ['id' => 'search_frm'])}}

        
{!! formEnd() !!}

<div class="row">
            <div class="col-12">
                <div id="collection">
                
                    @include('importreport::backend.partials.competitor_mapping')
                </div>
            </div>
            
        </div>

        <div class="row">
            <div class="col-12">
                <div id="collection">
                
                    @include('importreport::backend.partials.prisync_vertical_report')
                </div>
            </div>
            
        </div>