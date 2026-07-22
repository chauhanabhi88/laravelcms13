<?php
namespace Modules\Pages\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Pages\Models\Pages;
use Modules\Pages\Repositories\PagesRepository;
use Modules\Banner\Repositories\BannerRepository;
use Modules\Customer\Repositories\CustomerRepository;
use Modules\Core\Http\Controllers\FrontendController;
use Modules\Banner\Repositories\BannerGroupRepository;

class PageController extends FrontendController
{
    protected $page = null;
    protected $pageEntity = null;
    protected $bannerGroupRepo = null;
    protected $bannerRepository = null;

    /**
     * Modules\Pages\Http\Controllers\PageController
     *
     * @param Modules\Pages\Repositories\PagesRepository $page
     * @param Modules\Pages\Models\Pages $pageEntity
     * @param Modules\Banner\Repositories\bannerGroupRepository $pageEntity
     */
    public function __construct(PagesRepository $page, Pages $pageEntity, BannerGroupRepository $bannerGroupRepo, BannerRepository $bannerRepository, CustomerRepository $customer){
        $this->page = $page;
        $this->pageEntity = $pageEntity;
        $this->bannerGroupRepo = $bannerGroupRepo;
        $this->bannerRepository = $bannerRepository;
        $this->member = $customer;
    }

    /**
     * Homepage action
     */
    public function index(Request $request)
    {
        try {
            $locale = $request->segment(1);
            $homePage = true;
            return view('pages::frontend.home',compact('homePage'));
        } catch (\Throwable $e) {
            return redirect()->route('homepage', updateUrlParams(['type' => config('core.route_type')]))->with("error", $e->getMessage());
        }
    }

    public function page(Request $request)
    {
        try {
            // $slug = $request->segment(count($request->segments()));
            $page = null;
            $slug = $request->slug;
            if(!empty($slug)) {
                $page = $this->page->findBySlug($slug);
            }
            if(!empty($page)) {
                $getMemberInfo = $this->member->getLoginUserInfo();
                $banner = $this->bannerRepository->getBannerByCode($slug);
                return view('pages::frontend.index', compact( 'page', 'banner', 'getMemberInfo' ));
            } else {
                return redirect(route('homepage', updateUrlParams(['type' => config('core.route_type')])));
            }
        } catch (\Throwable $e) {
            return redirect()->route('homepage', updateUrlParams(['type' => config('core.route_type')]))->with("error", $e->getMessage());
        }
    }

    public function wrongLangHome(Request $request)
    {
        return view('pages::frontend.wronglanghome');
    }
}
