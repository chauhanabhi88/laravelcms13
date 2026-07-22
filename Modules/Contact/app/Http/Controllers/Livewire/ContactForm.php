<?php

namespace Modules\Contact\Http\Controllers\Livewire;

use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Mail;
use Modules\Contact\Emails\ContactMail;
use Modules\Contact\Repositories\ContactRepository;

class ContactForm extends Component
{
    #[Validate]
    public $name = '';
    public $email = '';
    public $content = '';

    public function rules()
    {
        return [
            'name' => 'required|min:6|max:255',
            'email' => 'required|email',
            'content' => 'required'
        ];
    }

    public function render()
    {
        return view('contact::frontend.livewire.contact-form');
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function save()
    {
        $params = $this->validate();
        try {
            $contactRepository = app(ContactRepository::class);
            $contact = $contactRepository->create($params);
            
            /* Send Mail */
            Mail::send(new ContactMail($contact));

            return redirect()->route('contact.index')->with('success', trans("contact::contact_front.messages.success"));
        } catch (\Throwable $e) {
            return redirect()->route('contact.index', updateUrlParams(['type' => config('core.route_type')]))->with('error', $e->getMessage());
        }
    }
}
