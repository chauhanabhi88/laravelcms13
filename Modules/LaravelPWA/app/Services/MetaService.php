<?php

namespace Modules\LaravelPWA\Services;

class MetaService
{
    public function render()
    {
        return "<?php \$config = (new \Modules\LaravelPWA\Services\ManifestService)->generate(); echo \$__env->make( 'laravelpwa::meta' , ['config' => \$config])->render(); ?>";
    }

}