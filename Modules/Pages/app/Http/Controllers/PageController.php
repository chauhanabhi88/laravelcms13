<?php

namespace Modules\Pages\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Banner\Repositories\BannerGroupRepository;
use Modules\Banner\Repositories\BannerRepository;
use Modules\Block\Repositories\BlockRepository;
use Modules\Blog\Repositories\BlogPostRepository;
use Modules\Core\Http\Controllers\FrontendController;
use Modules\Pages\Repositories\PagesRepository;
use Nwidart\Modules\Facades\Module;

class PageController extends FrontendController
{
    public function __construct(
        protected PagesRepository $page,
        protected BannerGroupRepository $bannerGroupRepo,
        protected BannerRepository $bannerRepository,
        protected BlockRepository $blockRepository,
    ) {
        parent::__construct();
    }

    /**
     * Homepage: hero slides (Banner group), content Blocks and featured blog posts.
     * Every section is optional and hidden when its content is missing.
     */
    public function index(Request $request): View
    {
        $locale = app()->getLocale();

        return view('pages::frontend.home', [
            'slides' => $this->homeSlides($locale),
            'intro' => $this->block(config('pages.home.blocks.intro'), $locale),
            'cta' => $this->block(config('pages.home.blocks.cta'), $locale),
            'posts' => $this->homePosts(),
        ]);
    }

    /**
     * CMS page by slug. Unknown or disabled pages are a 404.
     */
    public function page(Request $request): View
    {
        $page = $this->page->findPublishedBySlug((string) $request->route('slug'));

        abort_if(! $page, 404);

        $banner = $this->bannerRepository->getBannerByCode($page->slug);

        return view('pages::frontend.index', [
            'page' => $page,
            'bannerUrl' => ! empty($banner['image']) ? getImageUrl(['module' => 'banner', 'image' => $banner['image']]) : null,
        ]);
    }

    public function wrongLangHome(Request $request)
    {
        return view('pages::frontend.wronglanghome');
    }

    /**
     * @return array<int, array{image: ?string, title: ?string, content: ?string, url: ?string}>
     */
    protected function homeSlides(string $locale): array
    {
        if (! Module::isEnabled('Banner') || ! ($code = config('pages.home.banner_group'))) {
            return [];
        }

        return collect($this->bannerGroupRepo->getBannersByGroup($code, $locale))
            ->sortBy('sort_order')
            ->map(fn (array $banner) => [
                'image' => getImageUrl(['module' => 'banner', 'image' => $banner['image'] ?? null]),
                'title' => $banner['translations'][0]['title'] ?? null,
                'content' => $banner['translations'][0]['content'] ?? null,
                'url' => $banner['url'] ?? null,
            ])
            ->filter(fn (array $slide) => $slide['image'] || $slide['title'])
            ->values()
            ->all();
    }

    protected function block(?string $slug, string $locale): ?object
    {
        if (! $slug || ! Module::isEnabled('Block')) {
            return null;
        }

        $block = $this->blockRepository->getBlockContent($slug, $locale);

        return $block && ($block->title || $block->content) ? $block : null;
    }

    /**
     * Featured posts, topped up with the latest ones.
     */
    protected function homePosts(): Collection
    {
        if (! Module::isEnabled('Blog')) {
            return collect();
        }

        try {
            $posts = app(BlogPostRepository::class);
            $limit = (int) config('pages.home.featured_posts', 3);
            $featured = $posts->getFeaturedPosts($limit);

            if ($featured->count() >= $limit) {
                return $featured;
            }

            $latest = collect($posts->getPublishedPosts([], $limit * 2)->items())
                ->reject(fn ($post) => $featured->contains('id', $post->id));

            return $featured->concat($latest)->take($limit)->values();
        } catch (\Throwable $e) {
            Log::error($e);

            return collect();
        }
    }
}
