<?php

namespace Modules\Mail\Models;

use Arr;
use Illuminate\Database\Eloquent\Model;
use Modules\Block\Models\Block;

class MailTemplate extends Model
{
    protected $table = 'mail_templates';

    protected $fillable = [
        'name',
        'subject',
        'slug',
        'cc',
        'bcc',
        'body',
        'status',
    ];

    protected $_mailParams = null;

    // Please Don't remove below Line
    // AppendFunctionHere

    public function setMailParams($params)
    {
        if (! is_array($params)) {
            throw new Exception('mail params must be an array.');
        } elseif (! count($params)) {
            throw new Exception('mail params should not be empty.');
        }

        $this->_mailParams = $params;

        return $this;
    }

    protected function _replaceDataWithVariable(?string $content = null)
    {
        if (! $content) {
            return;
        }
        if (! $this->_mailParams) {
            return $content;
        }

        foreach ($this->_mailParams as $key => $params) {
            if (is_object($params)) {
                foreach ($params as $field => $object) {
                    $content = str_replace('##'.$field.'##', ($params->$field) ? $params->$field : '-', $content);
                }
            } elseif (is_array($params)) {
                foreach ($params as $field => $object) {
                    $content = str_replace('##'.$field.'##', ($params[$field]) ? $params[$field] : '-', $content);
                }
            } else {
                $content = str_replace('##'.$key.'##', ($params) ? $params : '-', $content);
            }
        }

        return $content;
    }

    protected function _replaceBlock()
    {
        $string = $this->replaceMedia();
        $string = $this->_replaceDataWithVariable($string);

        $string = str_replace('##base_url##', \URL::to('/'), $string);

        preg_match_all("/##block::([-a-zA-Z_\x7f-\xff][-a-zA-Z0-9_\x7f-\xff]*)##/", $string, $matches);
        $block = new Block;
        foreach ($matches[0] as $key => $var_name) {
            if (! isset($GLOBALS[$matches[1][$key]])) {
                $blockKey = $matches[1][$key];
                $blockData = $block->where('slug', $blockKey)->first();
                $GLOBALS[$matches[1][$key]] = ($blockData ? $blockData->getContent() : $matches[1][$key]);
            }
            $string = str_replace($var_name, $GLOBALS[$matches[1][$key]], $string);
        }

        return $string;
    }

    protected function replaceMedia()
    {
        $publicPath = public_path('Modules/CmsPages');

        return str_replace('##media##', $publicPath, $this->body);
    }

    public function getContent()
    {
        return $this->_replaceBlock();
    }

    public function getSubject()
    {
        return $this->_replaceDataWithVariable($this->subject);
    }

    public function getSenderEmail()
    {
        $senderEmail = settings('mail', 'sender_email');
        if (empty($senderEmail) || is_null($senderEmail)) {
            $senderEmail = config('mail.from.address');
        }

        return $senderEmail;
    }

    public function getSenderName()
    {
        $senderUserName = settings('mail', 'sender_name');
        if (empty($senderUserName) || is_null($senderUserName)) {
            $senderUserName = config('mail.from.name');
        }

        return $senderUserName;

    }

    public function sendMail($mailable, $receiverMail, $body)
    {
        $cc = [];
        $bcc = [];
        $recipientMail = [];

        $senderEmail = $this->getSenderEmail();
        $senderUserName = $this->getSenderName();

        if (isset($this->cc) && ! empty($this->cc)) {
            $cc = explode(',', trim($this->cc, ','));
            $cc = Arr::where($cc, function ($value, $key) {
                return $value !== null && $value !== false && $value !== '';
            });
        }

        if (isset($this->bcc) && ! empty($this->bcc)) {
            $bcc = explode(',', trim($this->bcc, ','));
            $bcc = Arr::where($bcc, function ($value, $key) {
                return $value !== null && $value !== false && $value !== '';
            });
        }

        if (isset($receiverMail) && ! empty($receiverMail)) {
            $recipientMail = explode(',', trim($receiverMail, ','));
            $recipientMail = Arr::where($recipientMail, function ($value, $key) {
                return $value !== null && $value !== false && $value !== '';
            });
        }

        return $mailable->to($recipientMail)
            ->from($senderEmail, $senderUserName)
            ->cc($cc)
            ->bcc($bcc)
            ->subject($this->subject)
            ->view('mail::body', compact('body'));
    }
}
