<?php
view()->composer('theme::layouts.frontend.masterhome', \Modules\Core\Composers\AssetsViewComposer::class);
view()->composer('theme::layouts.frontend.master', \Modules\Core\Composers\AssetsViewComposer::class);
view()->composer('theme::layouts.backend.master', \Modules\Core\Composers\AssetsViewComposer::class);
