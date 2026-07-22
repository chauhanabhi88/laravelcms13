<?php

use Modules\Menu\Repositories\MenuRepository;

$user = Auth::user();

$menu = app(MenuRepository::class)->getMenu();

?>
<nav class="sidebar sidebar-offcanvas" id="sidebar-icon-only">
  <div class="sidebar-inner ps-enabled">
    <ul class="nav">
      <li class="nav-item brand-logo">
        <a class="nav-link" href="{{ route('admin.dashboard.index', updateUrlParams()) }}"><img src="{{ asset('modules/theme/backend/images/logo.png') }}" alt="logo"></a>
      </li>
      {!! $menu !!}
      <li class="nav-item">
        @can('admin.dashboard.clear_all_cache')
        <a class="nav-link" href="{{ route('admin.dashboard.clear_all_cache', updateUrlParams()) }}">
          <i class='menu-icon far fa-trash-alt'></i>
          <span class="menu-title">{{ trans("core::core.buttons.clear_all_cache") }}</span>
        </a>
        @endcan
      </li>
    </ul>
  </div>
</nav>


@push("js-stack")
<script type="text/javascript">
  var currentUrl = window.location.href;
  activeSidebar();
  function activeSidebar() {
    jQuery("nav.sidebar ul.nav li a").each(function(index, element) {
      if (currentUrl.search(jQuery(element).attr('href')) >= 0) {
        if (jQuery(element).parents("li.nav-item").hasClass("level-0")) {
          jQuery(element).parents("li.nav-item").addClass("active");
        }
        jQuery('#active_menu_id').val(jQuery(element).parents("li.nav-item").attr('menu-id'))
        if (!jQuery(element).parents('div.submenu').parent().hasClass("active")) {
          jQuery(element).parents('div.submenu').parent().addClass("active");
        }
        
      }
    });
  }
  if ($('.ps-enabled').length) {
    const psEnabled = new PerfectScrollbar('.ps-enabled');
  }
  $('.main-panel').click(function(e) {
    $(".sidebar .sidebar-inner > .nav > .nav-item").attr('toggle-status');

    $(".sidebar .sidebar-inner > .nav > .nav-item").find(".submenu").removeClass("open");
    $(".sidebar .sidebar-inner > .nav > .nav-item").not(".brand-logo").attr('toggle-status', 'closed');
    $(".sidebar .sidebar-inner > .nav > .nav-item").removeClass("active");
    activeSidebar();
  });
  $(".sidebar .sidebar-inner > .nav > .nav-item").not(".brand-logo").attr('toggle-status', 'closed');
  $(".sidebar .sidebar-inner .nav .nav-item").on('click', function() {
    $(".sidebar .sidebar-inner > .nav > .nav-item").removeClass("active");
    $(".sidebar .sidebar-inner > .nav > .nav-item").find(".submenu").removeClass("open");
    $(".sidebar .sidebar-inner > .nav > .nav-item").not(this).attr('toggle-status', 'closed');
    console.log();
    var toggleStatus = $(this).attr('toggle-status');
    if (toggleStatus == 'closed') {
      $(this).find(".submenu").addClass("open");
      $(this).attr('toggle-status', 'open');
      $(this).addClass("active");
    } else {
      // var collapseId = $(this).find(".nav-link").attr('aria-controls');
      // if(collapseId != undefined) {
      //   $(this).find(".nav-link").addClass("collapsed");
      //   jQuery(`#${collapseId}`).removeClass("show");
      // }
      $(this).find(".submenu").removeClass("open");
      $(this).not(".brand-logo").attr('toggle-status', 'closed');
      activeSidebar();
    }
  });
</script>
@endpush