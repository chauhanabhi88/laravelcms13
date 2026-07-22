<?php
$user = Auth::user();
?>

<header class="header-mobile header-mobile-2 d-block d-lg-none">
    <div class="header-mobile__bar">
        <div class="container-fluid">
            <div class="header-mobile-inner">
                <a class="logo" href="">
                    <img src="{{ asset('modules/theme/backend/images/logo.png') }}" alt="Admin" />
                </a>
                <button class="hamburger hamburger--slider" type="button">
                    <span class="hamburger-box">
                        <span class="hamburger-inner"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>
    <nav class="navbar-mobile">
        <div class="container-fluid">
            @if ($sidebarMenu)
                <ul class="navbar-mobile__list list-unstyled">                        
                    @foreach ($sidebarMenu as $group => $items)
                        @if ($group == 'core::core.menu.single')
                            @foreach ($items as $menuItem)
                                @if ($authUser->can($menuItem['route']))
                                    <li class="">
                                        <a href="{{ route($menuItem['route'], updateUrlParams()) }}" class="">
                                            <i class="{{ (isset($menuItem['icon']) && $menuItem['icon']) ? $menuItem['icon'] : 'far fa-circle' }}"></i>
                                            {{ trans($menuItem['title']) }}
                                            @if(isset($menuItem['create']) && !empty($menuItem['create']))
                                                @if($authUser->can($menuItem['create']))
                                                <a href="{{route($menuItem['create'], updateUrlParams())}}" class="expand-div"><i class="fas fa-plus fa-sm" ></i></a>
                                                @endif
                                            @endif
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        @else
                            <li class="has-sub">
                                <a href="#" class="js-arrow">
                                    <i class="{{ (isset($menuItem['group_icon']) && $menuItem['group_icon']) ? $menuItem['group_icon'] : 'fas fa-layer-group' }}"></i>
                                        {{ trans($group) }}
                                </a>
                                <ul class="navbar-mobile-sub__list list-unstyled js-sub-list">
                                    @foreach ($items as $menuItem)
                                        @if ($authUser->can($menuItem['route']))
                                            <li class="">
                                                <a href="{{ route($menuItem['route'], updateUrlParams()) }}" class="">
                                                    <i class="{{ (isset($menuItem['icon']) && $menuItem['icon']) ? $menuItem['icon'] : 'far fa-circle' }}"></i>
                                                    {{ trans($menuItem['title']) }}
                                                    @if(isset($menuItem['create']) && !empty($menuItem['create']))
                                                        @if($authUser->can($menuItem['create']))
                                                        <a href="{{route($menuItem['create'], updateUrlParams())}}" class="expand-div"><i class="fas fa-plus fa-sm" ></i></a>
                                                        @endif
                                                    @endif
                                                </a>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </nav>
</header>
<div class="sub-header-mobile-2 d-block d-lg-none">
    <div class="header__tool">
        <div class="account-wrap">
            <div class="account-item account-item--style2 clearfix js-item-menu">
                <div class="image">
                    <img height="50px" width="50px" src="{{ asset('modules/theme/backend/images/logo.png') }}" />
                </div>
                <div class="content">
                    <a class="js-acc-btn" href="#">{{$user->name}}</a>
                </div>
                <div class="account-dropdown js-dropdown">
                    <div class="info clearfix">
                        <div class="image">
                            <a href="#">
                                <img height="50px" width="50px" src="{{ asset('modules/theme/backend/images/logo.png') }}" />
                            </a>
                        </div>
                        <div class="content">
                            <h5 class="name">
                                <a href="#">{{$user->name}}</a>
                            </h5>
                            <span class="email">{{$user->email}}</span>
                        </div>
                    </div>
                    <div class="account-dropdown__body">
                        <div class="account-dropdown__item">
                           <a href="{{ route('admin.user.editProfile', updateUrlParams()) }}">
                           <i class="zmdi zmdi-account"></i>Account</a>
                        </div>
                        <div class="account-dropdown__footer">
                           <a href="{{ route('backend_logout',updateUrlParams()) }}">
                           <i class="zmdi zmdi-power"></i>Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>