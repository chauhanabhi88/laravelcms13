<?php

namespace Modules\Contact\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Contact\Models\Contact;
use Modules\Contact\Repositories\ContactRepository;
use Modules\Contact\Http\Requests\CreateRequest;
use Modules\Contact\Http\Requests\UpdateRequest;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Menu\Models\Menu;

class ContactController extends BackendController
{
    /**
     * @var ContactRepository
     */
    private $contact;

    /**
     * @var UserEntity
     */
    private $contactEntity;


    public function __construct(ContactRepository $contact, Contact $contactEntity)
    {
        parent::__construct();
        $this->contact = $contact;
        $this->contactEntity = $contactEntity;
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        try {

            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config("contact.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            // $columns = $this->contact->sortColumns();
            $collection = $this->contact->pagination($request);
            $filters = $this->contact->getFilters($request);
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            return view('contact::backend.index', compact('request', 'collection', 'columns', 'filters', 'activeMenuId'));
        } catch (\Throwable $e) {
            dd($e->getMessage());
            return redirect(route("admin.dashboard.index", updateUrlParams()))->with("error", $e->getMessage() . $e->getTraceAsString());
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
                $perPage = getPerPageForModule(config("contact.name"), $request->get("per_page"));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config("contact.name"), $request);
            // $columns = $this->contact->sortColumns();
            $filters = $this->contact->getFilters($request);
            $collection = $this->contact->pagination($request);
            $activeMenuId = getActiveMenuId($request, 'admin.contact.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('contact::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString()
                ]
            ]);
        } catch (\Exception $e) {
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
    public function create()
    {
        try {
            return view("contact::backend.create");
        } catch (\Throwable $e) {
            return redirect(route("admin.contact.index", updateUrlParams()))->with("error", $e->getMessage());
        }
    }


    public function export(Request $request)
    {
        try {
            $data = $this->contact->export($request);
            $columnNames = ['name', 'email', 'contact_number', 'created_at'];
            if (empty($data)) {
                return $this->contact->exportCsv($columnNames, [], 'ContactEnquiries');
            } else {
                return $this->contact->exportCsv($columnNames, $data, 'ContactEnquiries');
            }
        } catch (\Throwable $e) {
            return redirect()->route('admin.contact.index', updateUrlParams())->with("error", $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        // try
        // {
        //     $params = $request->all();
        //     $contact = $this->contact->create($params['contact']);
        //     if(isset($params['snc']) && $params['snc']) {
        //         return redirect("backend/contact/edit/".$contact->id)->with("success", trans("contact::contact.messages.created_success"));
        //     }
        //     return redirect("backend/contact")->with("success", trans("contact::contact.messages.created_success"));
        // } catch (\Throwable $e) {
        //     return redirect("backend/contact/create")->with("error", $e->getMessage());
        // }
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
                throw new \Exception(trans("contact::contact.messages.data_invalid"));
            }
            $contact = $this->contact->find($id);
            if (!$contact) {
                throw new \Exception(trans("contact::contact.messages.data_invalid"));
            }
            return view('contact::backend.edit', compact('contact'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.contact.index', updateUrlParams())->with("error", $e->getMessage() . $e->getTraceAsString());
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
                throw new \Exception(trans("contact::contact.messages.data_invalid"));
            }
            $params = $request->all();

            $contact = $this->contact->find($id);
            if (!$contact) {
                throw new \Exception(trans("contact::contact.messages.data_invalid"));
            }

            $this->contact->update($contact, $params['contact']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.contact.edit', updateUrlParams([$id]))->with("success", trans("contact::contact.messages.updated_success"));
            }
            return redirect()->route('admin.contact.index', updateUrlParams())->with("success", trans("contact::contact.messages.updated_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.contact.edit', updateUrlParams([$id]))->with("error", $e->getMessage());
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
            $this->contact->deleteRecord($request);
            return redirect()->route('admin.contact.index', updateUrlParams())->with("success", trans("contact::contact.messages.deleted_success"));

        } catch (\Throwable $e) {
            return redirect()->route('admin.contact.index', updateUrlParams())->with("error", $e->getMessage() . $e->getTraceAsString());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->contact->destroyMultiple($request);
            return redirect()->route('admin.contact.index', updateUrlParams())->with("success", trans("contact::contact.messages.deleted_success"));
        } catch (\Throwable $e) {
            return redirect()->route('admin.contact.index', updateUrlParams())->with("error", $e->getMessage() . $e->getTraceAsString());
        }
    }

    public function view(Request $request)
    {
        try {
            $id = $request->id;
            $contact = $this->contact->find($id);
            if (!$contact) {
                throw new \Exception(trans("core::core.messages.not_found"));
            }
            return view('contact::backend.view', compact('contact'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.contact.index', updateUrlParams())->with("error", $e->getMessage() . $e->getTraceAsString());
        }
    }
}
