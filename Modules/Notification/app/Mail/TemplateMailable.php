<?php

declare(strict_types=1);

namespace Modules\Notification\Mail;

use Illuminate\Mail\Mailable;
use Modules\Mail\Models\MailTemplate;

/**
 * A Mail module template rendered with ##placeholder## values into the mail::body view, the
 * way the existing mailables (e.g. CustomerEmailVerifyMail) do. Values are HTML-escaped
 * before they are put into the body; the subject uses the raw values.
 */
class TemplateMailable extends Mailable
{
    /**
     * @param  array<string, scalar|null>  $vars
     */
    public function __construct(
        public readonly MailTemplate $template,
        public readonly array $vars,
    ) {}

    public function build(): static
    {
        $plain = $this->stringValues(false);
        $subject = (clone $this->template)->setMailParams($plain)->getSubject();

        // Admin-edited bodies may be entity-encoded; decode the template, not the values.
        $template = clone $this->template;
        $template->body = html_entity_decode((string) $template->body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = '<html>'.$template->setMailParams($this->stringValues(true))->getContent().'</html>';

        $this->from($this->template->getSenderEmail(), $this->template->getSenderName())
            ->subject((string) $subject)
            ->view('mail::body', ['body' => $body]);

        foreach (['cc', 'bcc'] as $field) {
            $addresses = array_values(array_filter(array_map('trim', explode(',', (string) $this->template->{$field}))));

            if ($addresses !== []) {
                $this->{$field}($addresses);
            }
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    private function stringValues(bool $escape): array
    {
        $values = [];

        foreach ($this->vars as $key => $value) {
            $value = is_scalar($value) ? (string) $value : '';
            $values[(string) $key] = $escape ? e($value) : $value;
        }

        return $values;
    }
}
