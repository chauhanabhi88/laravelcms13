<?php

use Modules\Menu\Repositories\MenuRepository;

$user = Auth::user();

$sidebarMenu = app(MenuRepository::class)->getMenu();

?>
<nav class="sidebar sidebar-offcanvas" id="sidebar-icon-only">
  <div class="sidebar-inner ps-enabled">
    <ul class="nav" id="nav">
        @include('theme::layouts.backend.partials.staradmin.sidebar')
    </ul>
  </div>
</nav>


@push("js-stack")
<script type="text/javascript">
  var currentUrl = window.location.href;
  $(function() {
    jQuery("nav.sidebar ul.nav li a").each(function(index, element) {
      if (jQuery(element).attr('href') == currentUrl) {
        if (jQuery(element).parents("li.nav-item").hasClass("level-0")) {
          jQuery(element).parents("li.nav-item").addClass("active");
        }
        if (!jQuery(element).parents('div.submenu').parent().hasClass("active")) {
          jQuery(element).parents('div.submenu').parent().addClass("active");
        }
      }
    });
  });
  if ($('.ps-enabled').length) {
    const psEnabled = new PerfectScrollbar('.ps-enabled');
  }
  $(".sidebar .sidebar-inner > .nav > .nav-item").not(".brand-logo").attr('toggle-status', 'closed');
  $(".sidebar .sidebar-inner .nav .nav-item").on('click', function() {
    $(".sidebar .sidebar-inner > .nav > .nav-item").removeClass("active");
    $(".sidebar .sidebar-inner > .nav > .nav-item").find(".submenu").removeClass("open");
    $(".sidebar .sidebar-inner > .nav > .nav-item").not(this).attr('toggle-status', 'closed');
    var toggleStatus = $(this).attr('toggle-status');
    if (toggleStatus == 'closed') {
      $(this).find(".submenu").addClass("open");
      $(this).attr('toggle-status', 'open');
      $(this).addClass("active");
    } else {
      $(this).find(".submenu").removeClass("open");
      $(this).not(".brand-logo").attr('toggle-status', 'closed');
      jQuery("nav.sidebar ul.nav li a").each(function(index, element) {
        if (jQuery(element).attr('href') == currentUrl) {
          if (jQuery(element).parents("li.nav-item").hasClass("level-0")) {
            jQuery(element).parents("li.nav-item").addClass("active");
          }
          if (!jQuery(element).parents('div.submenu').parent().hasClass("active")) {
            jQuery(element).parents('div.submenu').parent().addClass("active");
          }
        }
      });
    }
  });
</script>
@endpush