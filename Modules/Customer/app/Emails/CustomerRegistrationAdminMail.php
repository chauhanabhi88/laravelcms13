<?php

namespace Modules\Customer\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Mail\Models\MailTemplate;
use Modules\Customer\Models\Customer;


class CustomerRegistrationAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The Customer instance.
     *
     * @var Customer
     */
    protected $customerEntity;

    
    public function __construct(Customer $customerEntity)
    {
       $this->customerEntity = $customerEntity;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        try{
                $body = '';
                $senderEmail = settings("mail","recipient_admin_email");
                $senderUserName = config('mail.username');
                $customer = $this->customerEntity;
                $mailRepository = new MailTemplate;
                $mailTemplate = $mailRepository->where(['slug'=>'admin-notification-customer-registartion','status'=>config("core.enabled")])->first(); 
                if(isset($mailTemplate) && !empty($mailTemplate)){
                    $body = $mailTemplate->setMailParams([
                        'customer_id'=>$customer->id,
                        'customer_name'=> $customer->first_name.' '.$customer->last_name,
                        'customer_email'=>$customer->email,
                        'customer_contact'=> $customer->contact_number,
                    ])->getContent();
                    $body = '<html>'.html_entity_decode($body).'</html>';

                    // return $this->to($senderEmail, $senderUserName)
                    // ->from($senderEmail, $senderUserName)
                    // ->subject($mailTemplate->subject)
                    // ->view('mail::body', compact('body'));
                    return $mailTemplate->sendMail($this, $senderEmail, $body);
                }  
            return $this->view('mail::body', compact('body'));
        }catch(\Throwable $e){
            return $e->getMessage();
        }
    }
}
