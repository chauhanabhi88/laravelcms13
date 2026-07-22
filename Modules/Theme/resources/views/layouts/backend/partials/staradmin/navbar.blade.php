<?php
$user = Auth::user();
?>
<div class="navbar fixed-top">
  <div class="navbar-menu-wrapper d-flex align-items-center w-100">
    <ul class="nav navbar-nav-right ml-auto">
      <li class="nav-item dropdown d-none d-xl-inline-block user-dropdown">
        <a class="nav-link dropdown-toggle" id="UserDropdown" href="#" data-toggle="dropdown" aria-expanded="false">
          <img class="img-xs rounded-circle" src="{{ asset('modules/theme/backend/images/logo.png') }}" alt="Profile image">
          <span class="profile-text">{{$user->name}}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
          <a class="dropdown-item mt-2 " href="{{ route('admin.user.editProfile', updateUrlParams()) }}"> Manage Accounts </a>
          <div class="wrapper px-4 pt-2">
            <a class="btn btn-rounded btn-sm btn-outline-primary mr-2" href="{{ route('backend_logout',updateUrlParams()) }}">Logout</a>
            <button type="button" class="btn btn-rounded btn-sm btn-outline-secondary">Cancel</button>
          </div>
        </div>
      </li>
    </ul>
    <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center pr-0" type="button" data-toggle="offcanvas">
      <span class="mdi mdi-menu"></span>
    </button>
  </div>
</div>
