<?php

namespace Modules\Mail\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Mail\Models\MailTemplate;
use Modules\Mail\Repositories\MailRepository;
use Modules\Mail\Http\Requests\UpdateRequest;
use Modules\Mail\Http\Requests\CreateRequest;
use Mail;
use Modules\Menu\Models\Menu;

class MailController extends BackendController
{

    protected $mail = null;
    protected $mailEntity = null;

    public function __construct(MailRepository $mail, MailTemplate $mailEntity)
    {
        parent::__construct();
        $this->mail = $mail;
        $this->mailEntity = $mailEntity;
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("mail.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            $statusOptions = $this->mail->getStatusOptions(true);
            $collection = $this->mail->pagination($request);
            $filters = $this->mail->getFilters($request, $statusOptions);
            // $columns = $this->mail->sortColumns($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            return view('mail::backend.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index',updateUrlParams())->with("error", $e->getMessage());
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
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("mail.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("mail.name"), $request);
            $statusOptions = $this->mail->getStatusOptions(true);
            $filters = $this->mail->getFilters($request, $statusOptions);
            $collection = $this->mail->pagination($request);
            // $columns = $this->mail->sortColumns($request);
            $activeMenuId = getActiveMenuId($request, 'admin.mail.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('mail::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ],
                'message' => $request->get('message'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    function create(MailRepository $mail)
    {
        try {
            $this->getAssetManager()->addAsset("modules/theme/backend/js/jquery.slug.js");
            $this->getAssetManager()->addAsset('modules/pages/js/summernote.min.js');
            $this->getAssetManager()->addAsset('modules/pages/css/summernote.css');
            return view('mail::backend.create', compact('mail'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        try {
            $params = $request->all();
            $params['mail']['status'] = (isset($params['mail']['status'])) ? config('core.enabled') : config('core.disabled');
            $params['mail']['body'] = $this->replaceSummernoteImageContent($params['mail']['body'], \Config::get('mail.mail_name'));
            $mail  = $this->mail->create($params['mail']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.mail.edit', updateUrlParams([$mail->id]))->with("success", trans("mail::mail.messages.created_success"));
            }
            return redirect()->route('admin.mail.index',updateUrlParams())->with("success", trans("mail::mail.messages.created_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.mail.create',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("mail::mail.messages.data_invalid"));
            }
            $mail = $this->mail->find($id);
            if (!$mail) {
                throw new \Exception(trans("mail::mail.messages.data_invalid"));
            }
            $this->getAssetManager()->addAsset("modules/theme/backend/js/jquery.slug.js");
            $this->getAssetManager()->addAsset('modules/pages/js/summernote.min.js');
            $this->getAssetManager()->addAsset('modules/pages/css/summernote.css');
            $mailRespository = $this->mail;
            $statusOptions = $mailRespository->getStatusOptions(true);
            return view('mail::backend.edit', compact('mail', 'mailRespository', 'statusOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.mail.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(UpdateRequest $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("mail::mail.messages.data_invalid"));
            }
            $params = $request->all();

            $mail = $this->mail->find($id);
            if (!$mail) {
                throw new \Exception(trans("mail::mail.messages.data_invalid"));
            }
            $params['mail']['status'] = (isset($params['mail']['status'])) ? config('core.enabled') : config('core.disabled');
            $params['mail']['body'] = $this->replaceSummernoteImageContent($params['mail']['body'], \Config::get('mail.mail_name'));
            $this->mail->update($mail, $params['mail']);

            if (isset($params['snp']) && $params['snp']) {
                return redirect()->route('admin.mail.edit', updateUrlParams([$id]))->withInput(['snp' => '1'])->with("success", trans("mail::mail.messages.updated_success"));
            }

            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.mail.edit', updateUrlParams([$id]))->with("success", trans("mail::mail.messages.updated_success"));
            }
            return redirect()->route('admin.mail.index',updateUrlParams())->with("success", trans("mail::mail.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.mail.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function delete(Request $request)
    {
        try {           
            $this->mail->deleteRecord($request);
            return redirect()->route('admin.mail.index',updateUrlParams())->with("success", trans("mail::mail.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.mail.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->mail->destroyMultiple($request);
            return redirect()->route('admin.mail.index',updateUrlParams())->with("success", trans("mail::mail.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.mail.index',updateUrlParams())->with("error", $e->getMessage());
        }
    }


    public function perview(Request $request)
    {
        try {
            $body = '';
            $id = $request->id;
            if (!$id) {
                throw new \Exception(trans("mail::mail.messages.data_invalid"));
            }
            $mail = $this->mail->find($id);
            if (!$mail) {
                throw new \Exception(trans("mail::mail.messages.data_invalid"));
            }
            if(isset($mail->body) && !empty($mail->body)) {
                $body = '<html>'.html_entity_decode($mail->body).'</html>';
                return view('mail::body', compact('body'));
            }
            return view('mail::body', compact('body'));
        } catch (\Throwable $e) {
            return redirect()->back()->with("error", $e->getMessage());
        }
    }

    public function sendMail()
    {
        try {
            $templateId = 2;
            $params = ['name' => 'Chirag Kanjariya', 'date' => date('d-m-Y')];
            $this->send($templateId, $params);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function send($mailTemplateId, $params = null)
    {
        try {
            if (!$mailTemplateId) {
                return;
            }
            $mailTemplate = new MailTemplate;
            $mailTemplate = $mailTemplate->where('id', $mailTemplateId)->where('status', 1)->first();
            if (!$mailTemplate) {
                return;
            }
            if ($params) {
                $mailTemplate->setMailParams($params);
            }
            $sent = Mail::html($mailTemplate->getContent(), function ($message) use ($mailTemplate) {
                $message->to(explode(',', $mailTemplate->to))->subject($mailTemplate->getSubject());
                $message->from("jessy.loren@gmail.com", "Jessy Loren");
            });
            return true;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    public function updateStatus(Request $request)
    {
        if ($request->get('id')) {
            $id = $request->get('id');
            $status = $request->get('status');
            $mailRow = $this->mail->find($id);
            $status = ($status == 1) ? 1 : 2;
            $params = array('status' => $status);
            $this->mail->update($mailRow, $params);
        }
        $gridRequest = new Request();
        $gridRequest->merge([
            'active_menu_id' => $request->get('active_menu_id'),
            'message' => trans("core::core.messages.status_change_success")
        ]);
        return $this->filters($gridRequest);
    }
}
