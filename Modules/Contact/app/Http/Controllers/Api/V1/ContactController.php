<?php

namespace Modules\Contact\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Modules\Contact\Emails\ContactMail;
use Modules\Contact\Repositories\ContactRepository;

class ContactController extends Controller
{
    /**
     * @var ContactRepository
     */
    private $contact;

    public function __construct(ContactRepository $contact)
    {
        $this->contact = $contact;
    }

    public function save(Request $request)
    {
        try {
            $data = $request->all();
            $rules = [
                'email' => 'required|email|max:255',
                'contact_number' => 'nullable|regex:/^[0-9]{10}$/|max:20',
                'name' => 'required|min:3|max:255',
                'content' => 'required',
            ];
            $validator = Validator::make($data, $rules, [
                'required' => trans('contact::contact.messages.required'),
                'min' => trans('contact::contact.messages.password_length'),
                'regex' => trans('contact::contact.messages.number_valid'),
                'email.email' => trans('contact::contact.messages.invalid_email'),
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()], 400);
            }

            $contact = $this->contact->create($data);
            Mail::send(new ContactMail($contact));

            return response()->json(['success' => true, 'data' => trans('contact::contact.messages.created_success')]);

        } catch (\Throwable $th) {
            Log::error($th);

            return response()->json(['success' => false, 'message' => trans('core::core.messages.unexpected_error')], 500);
        }
    }
}
