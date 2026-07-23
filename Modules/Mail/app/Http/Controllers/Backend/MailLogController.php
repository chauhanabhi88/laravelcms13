<?php

namespace Modules\Mail\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Mail\Repositories\MailLogRepository;

class MailLogController extends BackendController
{
    protected $mailLogRepo = null;

    public function __construct(MailLogRepository $mailLogRepo)
    {
        parent::__construct();
        $this->mailLogRepo = $mailLogRepo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            $perPage = getPerPageForModule('mail_log', $request->get('per_page'));
            $request->merge(['per_page' => $perPage]);
            $collection = $this->mailLogRepo->pagination($request);
            $filters = $this->mailLogRepo->getFilters($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);

            return view('mail::backend.mail_log.index', compact('request', 'collection', 'columns', 'filters', 'activeMenuId'));

        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function filters(Request $request)
    {
        try {
            $perPage = getPerPageForModule('mail_log', $request->get('per_page'));
            $request->merge(['per_page' => $perPage]);
            $filters = $this->mailLogRepo->getFilters($request);
            $collection = $this->mailLogRepo->pagination($request);
            $activeMenuId = getActiveMenuId($request, 'admin.mail_log.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('mail::backend.mail_log.partials.grid', compact('request', 'collection', 'columns', 'filters', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString(),
                ],
                'message' => $request->get('message'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
