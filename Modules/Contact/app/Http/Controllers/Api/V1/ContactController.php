<?php

namespace Modules\Contact\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Contact\Models\Contact;
use Illuminate\Support\Facades\Validator;
use Modules\Contact\Emails\ContactMail;
use Illuminate\Support\Facades\Mail;
class ContactController extends Controller
{

    public function save(Request $request)
    {
        try {
            $data = $request->all();
            $rules = [
                "email" => "required|email",
                'contact_number' =>"regex:/^[0-9]{10}$/",
                "name" => "required|min:3",
            ];
            $validator = Validator::make($data, $rules, [
                'required' => trans('contact::contact.messages.required'),
                'min' => trans('contact::contact.messages.password_length'),
                'regex' => trans('contact::contact.messages.number_valid'),
                'email.email' => trans('contact::contact.messages.invalid_email'),
            ]);
            if ($validator->fails()) {
                return response()->json(["success" => false, "message" => $validator->errors()], 400);
            }

            $contact = Contact::create($data);
            Mail::send(new ContactMail($contact));

            return response()->json(['success' => true, 'data' => trans('contact::contact.messages.created_success')]);

        } catch (\Throwable $th) {
            return response()->json(["success" => false, "message" => $th->getMessage()], 500);
        }
    }
}
