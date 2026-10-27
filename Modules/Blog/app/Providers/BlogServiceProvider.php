<?php

namespace Modules\Blog\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Blog\Models\Blog;
use Modules\Blog\Models\BlogCategory;
use Modules\Blog\Models\BlogPost;
use Modules\Blog\Models\BlogPostCategory;
use Modules\Blog\Models\BlogPostComment;
use Modules\Blog\Repositories\Cache\CacheBlogCategoryDecorator;
use Modules\Blog\Repositories\Cache\CacheBlogDecorator;
use Modules\Blog\Repositories\Cache\CacheBlogPostCategoryRelationDecorator;
use Modules\Blog\Repositories\Cache\CacheBlogPostCommentDecorator;
use Modules\Blog\Repositories\Cache\CacheBlogPostDecorator;
use Modules\Blog\Repositories\Eloquent\EloquentBlogCategoryRepository;
use Modules\Blog\Repositories\Eloquent\EloquentBlogPostCategoryRelationRepository;
use Modules\Blog\Repositories\Eloquent\EloquentBlogPostCommentRepository;
use Modules\Blog\Repositories\Eloquent\EloquentBlogPostRepository;
use Modules\Blog\Repositories\Eloquent\EloquentBlogRepository;

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
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
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
            $sourcePath => $viewPath,
        ], 'views');

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
            $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'blog');
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
            "Modules\Blog\Repositories\BlogPostRepository",
            function () {
                $repository = new EloquentBlogPostRepository(new BlogPost);

                if (! getModule('blog', 'cache')) {
                    return $repository;
                }

                return new CacheBlogPostDecorator($repository);
            }
        );

        $this->app->bind(
            "Modules\Blog\Repositories\BlogCategoryRepository",
            function () {
                $repository = new EloquentBlogCategoryRepository(new BlogCategory);

                if (! getModule('blog', 'cache')) {
                    return $repository;
                }

                return new CacheBlogCategoryDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Blog\Repositories\BlogRepository',
            function () {
                $repository = new EloquentBlogRepository(new Blog);

                if (! getModule('blog', 'cache')) {
                    return $repository;
                }

                return new CacheBlogDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Blog\Repositories\BlogPostCategoryRelationRepository',
            function () {
                $repository = new EloquentBlogPostCategoryRelationRepository(new BlogPostCategory);

                if (! getModule('blog', 'cache')) {
                    return $repository;
                }

                return new CacheBlogPostCategoryRelationDecorator($repository);
            }
        );

        $this->app->bind(
            'Modules\Blog\Repositories\BlogPostCommentRepository',
            function () {
                $repository = new EloquentBlogPostCommentRepository(new BlogPostComment);

                if (! getModule('blog', 'cache')) {
                    return $repository;
                }

                return new CacheBlogPostCommentDecorator($repository);
            }
        );
    }
}
