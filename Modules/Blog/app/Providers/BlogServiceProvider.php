<?php

namespace Modules\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->registerBindings();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/blog.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php', 'blog'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/blog');

        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_map(function ($path) {
            return $path;
        }, [$sourcePath]), 'blog');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/blog');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'blog');
        } else {
            $this->loadTranslationsFrom(__DIR__ .'/../../resources/lang', 'blog');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function registerBindings()
    { 
      
        $this->app->bind(
            "Modules\Blog\Repositories\Folder4Repository",
            function () {
                $repository = new \Modules\Blog\Repositories\Eloquent\EloquentFolder4Repository(new \Modules\Blog\Models\Folder4);

                if (! getModule("blog", "cache")) {
                    return $repository;
                }

                return new \Modules\Blog\Repositories\Cache\CacheFolder4Decorator($repository);
            }
        );
            
		  $this->app->bind(
            "Modules\Blog\Repositories\BlogPostRepository",
            function () {
                $repository = new \Modules\Blog\Repositories\Eloquent\EloquentBlogPostRepository(new \Modules\Blog\Models\BlogPost);

                if (! getModule("blog", "cache")) {
                    return $repository;
                }

                return new \Modules\Blog\Repositories\Cache\CacheBlogPostDecorator($repository);
            }
        );
            
		  $this->app->bind(
            "Modules\Blog\Repositories\BlogCategoryRepository",
            function () {
                $repository = new \Modules\Blog\Repositories\Eloquent\EloquentBlogCategoryRepository(new \Modules\Blog\Models\BlogCategory);

                if (! getModule("blog", "cache")) {
                    return $repository;
                }

                return new \Modules\Blog\Repositories\Cache\CacheBlogCategoryDecorator($repository);
            }
        );
            
		$this->app->bind(
            'Modules\Blog\Repositories\BlogRepository',
            function () {
                $repository = new \Modules\Blog\Repositories\Eloquent\EloquentBlogRepository(new \Modules\Blog\Models\Blog());

                if (! getModule("blog", "cache")) {
                    return $repository;
                }

                return new \Modules\Blog\Repositories\Cache\CacheBlogDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Blog\Repositories\BlogPostCategoryRelationRepository',
            function () {
                $repository = new \Modules\Blog\Repositories\Eloquent\EloquentBlogPostCategoryRelationRepository(new \Modules\Blog\Models\BlogPostCategory());

                if (! getModule("blog", "cache")) {
                    return $repository;
                }

                return new \Modules\Blog\Repositories\Cache\CacheBlogPostCategoryRelationDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Blog\Repositories\BlogPostCommentRepository',
            function () {
                $repository = new \Modules\Blog\Repositories\Eloquent\EloquentBlogPostCommentRepository(new \Modules\Blog\Models\BlogPostComment());

                if (! getModule("blog", "cache")) {
                    return $repository;
                }

                return new \Modules\Blog\Repositories\Cache\CacheBlogPostCommentDecorator($repository);
            }
        );
    }
}
