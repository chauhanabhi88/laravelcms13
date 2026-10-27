<?php

namespace Modules\Contact\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Block\Repositories\BlockRepository;
use Modules\Contact\Emails\ContactMail;
use Modules\Contact\Http\Requests\StoreContactRequest;
use Modules\Contact\Repositories\ContactRepository;
use Modules\Core\Http\Controllers\FrontendController;
use Nwidart\Modules\Facades\Module;

class ContactController extends FrontendController
{
    public function __construct(protected ContactRepository $contact)
    {
        parent::__construct();
    }

    public function index(): View
    {
        $info = Module::isEnabled('Block')
            ? app(BlockRepository::class)->getBlockContent('contact_info', app()->getLocale())
            : null;

        return view('contact::frontend.index', compact('info'));
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        try {
            $contact = $this->contact->create($request->safe()->only('name', 'email', 'contact_number', 'content'));
        } catch (\Throwable $e) {
            Log::error($e);

            return redirect(frontRoute('contact.index'))->withInput()->with('error', trans('core::core.messages.unexpected_error'));
        }

        /* The enquiry is saved; a mail failure must not be reported as a failed submit. */
        try {
            Mail::send(new ContactMail($contact));
        } catch (\Throwable $e) {
            Log::error($e);
        }

        return redirect(frontRoute('contact.index'))->with('success', trans('contact::contact_front.messages.success'));
    }
}
