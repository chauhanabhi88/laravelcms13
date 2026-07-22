<div class="right-box">
    <div class="right-box-wrapper my-account-links">
        <div class="bookings">
            <div class="title booking">{{trans("customer::customer.labels.booking_label")}}</div>
                <a href="upcoming-bookings.html" class="links">{{trans('customer::customer.labels.view_upcoming_booking')}}</a><br>
                <a href="past-bookings.html"  class="links">{{trans('customer::customer.labels.view_past_booking')}}</a>
            </div>
            <div class="profile">
                <div class="title profile">{{trans('customer::customer.labels.profile')}}</div>
                <a href="{{ route('customer.profile.edit', updateUrlParams(['type' => config('core.route_type', Auth::user()->id])) }}" class="links">{{trans('customer::customer.labels.edit_profile')}}</a><br>
                <a href="{{ route('customer.change-password', updateUrlParams(['type' => config('core.route_type', Auth::user()->id])) }}" class="links">{{trans('customer::customer.labels.change_password')}}</a><br>
                <a href="{{ route('customer.card-list',updateUrlParams(['type' => config('core.route_type')])) }}" class="links">Credit Cards</a>
            </div>
    </div>
</div>
