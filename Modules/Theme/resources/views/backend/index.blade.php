@extends('theme::layouts.backend.master')
@section('title')
    {{ trans("theme::theme.titles.head_title") }}
@endsection
@section('content-header')
    <!-- Content Header (Page header) -->
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">{{ trans("theme::theme.titles.head_title") }}</h1>
            </div>
            <div class="col-sm-6">
				<div class="float-right">
					<button type="button" class="btn btn-info save" data-form-id="main_form">{{ trans("theme::theme.buttons.save") }}</button>
					<button type="button" class="btn btn-danger" data-toggle="modal" data-target="#resetModal">{{ trans("theme::theme.buttons.reset_settings") }}</button>
				</div>
            </div>
        </div>
    </div>
@stop

@section('content')
	<?php $theme = getThemes() ?>
	<div class="container-fluid">
        <!-- /.row -->
		<div id="collection">
			@include('theme::backend.partials.themeform')
		</div>
    </div>
	@include('theme::backend.partials.reset-modal')
@stop

@push('js-stack')
<script type="text/javascript">
jQuery(document).ready(function() 
{             
    jQuery('#body-small-text').change(function() {
        if(this.checked) {
          	addElement('body','text-sm');
          	jQuery('body').addClass('text-sm');
        }
        else
        {
			removeElement('body','text-sm');
			jQuery('body').removeClass('text-sm');
        }     
    })

    jQuery('#sidebar-nav').change(function() {
		removeElement('.nav-sidebar','nav-flat')
		jQuery('.nav-sidebar').removeClass('nav-flat')
       
		jQuery('.nav-sidebar').removeClass('nav-child-indent')
		removeElement('.nav-sidebar','nav-child-indent')
          
		var nav_sidebar = jQuery( "#sidebar-nav option:selected" ).val();
		addElement('.nav-sidebar',nav_sidebar);
		jQuery('.nav-sidebar').addClass(nav_sidebar);
    })

    jQuery('#legacy_style').change(function() {
        if(this.checked) {
			addElement('.nav-sidebar','nav-legacy');
			jQuery('.nav-sidebar').addClass('nav-legacy')
        }
        else
        {
			removeElement('.nav-sidebar','nav-legacy')
			jQuery('.nav-sidebar').removeClass('nav-legacy')
        }     
    });

	jQuery('#sidebar_collapse').change(function() {

        if(this.checked) {
			addElement('.sidebar-mini','sidebar-collapse');
			jQuery('.sidebar-mini').addClass('sidebar-collapse')
        }
        else
        {
        	removeElement('.sidebar-mini','sidebar-collapse');
			jQuery('.sidebar-mini').removeClass('sidebar-collapse')
			
        }     
    });
		@if((!isset($theme)) || (!isset($theme['logo'])))
	    	document.getElementById("filelogo").disabled = true;
	    @endif
	    
	    jQuery('#canclelogo').change(function() {

	        if(this.checked) {
	        	document.getElementById("filelogo").disabled = true;
			}
	        else {
	        	document.getElementById("filelogo").disabled = false;
	       }     
	    });

    jQuery('.customeNavbarVariants').click(function() 
    {
		var colors = new Array('navbar-primary','navbar-secondary','navbar-info','navbar-success','navbar-danger','navbar-indigo','navbar-purple','navbar-pink','navbar-teal','navbar-cyan','navbar-dark','navbar-gray','navbar-gray','navbar-light','navbar-warning','navbar-white','navbar-orange');

		jQuery('.main-header').removeClass('navbar-dark');
		jQuery('.main-header').removeClass('navbar-light');
		
		removeElement('.main-header','navbar-dark');
		removeElement('.main-header','navbar-light');
		
		colors.map(function (color) {
			removeElement('.main-header',color);
			jQuery('.main-header').removeClass(color)
		});
		var color = jQuery(this).attr('data-color');
		addElement('.main-header',color);
		jQuery('.main-header').addClass(color)
    });
       
    jQuery('.darkSidebarVariants').click(function() 
    {
		var colors = ['primary','warning','info','danger','success','indigo','lightblue','navy','purple','fuchsia','pink','maroon','orange','lime','teal','olive'];
		
		colors.map(function (color) 
		{
			var lightcolorclass ='sidebar-light-'+color;
			removeElement('.main-sidebar',lightcolorclass);
			jQuery('.main-sidebar').removeClass(lightcolorclass);
		
			var darkcolorclass ='sidebar-dark-'+color;
			removeElement('.main-sidebar',darkcolorclass);
			jQuery('.main-sidebar').removeClass(darkcolorclass);

		});

		var color = jQuery(this).attr('data-color');
		jQuery('.main-sidebar').addClass("sidebar-dark-"+color)
		addElement('.main-sidebar','sidebar-dark-'+color);
    });

    jQuery('.lightSidebarVariants').click(function() 
    {
            var colors = ['primary','warning','info','danger','success','indigo','lightblue','navy','purple','fuchsia','pink',
                    'maroon','orange','lime','teal','olive'];
            colors.map(function (color) 
            {
				var lightcolorclass ='sidebar-dark-'+color;
				removeElement('.main-sidebar',lightcolorclass);
				jQuery('.main-sidebar').removeClass(lightcolorclass);
		
				var darkcolorclass ='sidebar-light-'+color;
				removeElement('.main-sidebar',darkcolorclass);
				jQuery('.main-sidebar').removeClass(darkcolorclass);
            });

            var color = jQuery(this).attr('data-color');
            jQuery('.main-sidebar').addClass("sidebar-light-"+color)
            addElement('.main-sidebar','sidebar-light-'+color);
    });

    jQuery('.brandLogovariants').click(function() 
    {
        var colors = ['primary','secondary','info','success','danger','indigo','purple','pink','teal','cyan','dark', 'gray-dark','gray','light','warning','white','orange'];

        colors.map(function (color) 
        {
            var navbarcolorClass='navbar-'+color;
            removeElement('.brand-link',navbarcolorClass);
            jQuery('.brand-link').removeClass(navbarcolorClass);
        });
        var color = jQuery(this).attr('data-color');
        var navbarcolorClass='navbar-'+color;
        jQuery('.brand-link').addClass(navbarcolorClass)
        addElement('.brand-link',navbarcolorClass);
    });
});


	function addElement(key,value)
	{
    	var classJson =  jQuery.parseJSON(document.getElementById("theme_value").value);
		if((typeof(key) != 'undefined' && key) && (typeof(value) != 'undefined'&& value) )
		{
			if(Array.isArray(classJson))
			{
				var parse_obj = (classJson.length>0) ? classJson :{};
			}
			else
			{
				var parse_obj = classJson;
			}

			if(typeof(parse_obj[key])!='undefined')
			{
				parse_obj[key] = parse_obj[key]+" "+value;
			}
			else
			{
				parse_obj [key] = value;
			}
			document.getElementById("theme_value").value = JSON.stringify(parse_obj);
		}
	}
  
	function removeElement(key,value)
	{
		if(((typeof(key) != 'undefined')&& key) && ((typeof(value) != 'undefined')&& value) )
		{
			var classJson =  jQuery.parseJSON(document.getElementById("theme_value").value);
			if(Array.isArray(classJson))
			{
				var parse_obj = (classJson.length>0) ? classJson :{};
			}
			else
			{
				var parse_obj = classJson;
			}

			if(typeof(parse_obj[key]) != 'undefined')
			{
				var classvalue = parse_obj[key];
				parse_obj[key] = classvalue.replace(value,"").trim();
				if(parse_obj[key] == "")
				{
					delete parse_obj[key]
				}
				document.getElementById("theme_value").value = JSON.stringify(parse_obj);        
			}
		}
	}
</script>
@endpush
<?php
function getSelected($theme,$key,$classArray)
{
	if(isset($theme[$key])) 
	{
		foreach ($classArray as $value)
		{
			if(strpos($theme[$key],$value) !== false)
			{
				return $value;
			}
		}
		return null;;
	}
}
?>