<?php

namespace Modules\Core\Http\Middleware;

use Closure;

class Localization
{
    public function handle($request, Closure $next)
    {
        $reqestedLocale = $request->segment(1);

        if($reqestedLocale && checkSupportedLocale($reqestedLocale)) {

            if(!(\Session::has("locale")) || \Session::get("locale") !== $reqestedLocale) {
                \Session::put("locale", $reqestedLocale);
                // No cache flush here: the locale is part of the repository
                // cache key now, so switching language no longer needs to wipe
                // the cache - which it previously did for every other user too.
            }
            app()->setLocale($reqestedLocale);

            if ( $request->path() == app()->getLocale() . '/' . config("core.admin-prefix")) {
                return redirect(route('admin.dashboard.index', updateUrlParams()));
            } 

        } else {
            $currentUrl = explode('/', $request->path());
            if (config('core.translation') && ($request->path() == app()->getLocale() . '/' . config("core.admin-prefix") || (isset($currentUrl) && !empty($currentUrl) && in_array(config("core.admin-prefix"), $currentUrl)))) {
                return redirect(route('admin.dashboard.index', updateUrlParams()));
            }
            return redirect()->route("wrong_lang_home", updateUrlParams(['type' => config('core.route_type')]));
        }

        return $next($request);
    }
}
