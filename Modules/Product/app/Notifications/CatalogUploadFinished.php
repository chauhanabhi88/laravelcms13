<?php

declare(strict_types=1);

namespace Modules\Product\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Product\Models\CatalogUpload;

/**
 * Email to the uploader when a bulk upload finished ("Upload #12 completed: 480 saved,
 * 20 failed"). The Notifications module (M09) can add more channels.
 */
class CatalogUploadFinished extends Notification
{
    use Queueable;

    public function __construct(public readonly CatalogUpload $upload) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $replace = [
            'id' => $this->upload->id,
            'status' => $this->upload->statusLabel(),
            'success' => $this->upload->success_rows,
            'failed' => $this->upload->failed_rows,
            'file' => $this->upload->original_name,
        ];

        return (new MailMessage)
            ->subject(trans('product::seller.mail.upload_finished.subject', $replace))
            ->line(trans('product::seller.mail.upload_finished.body', $replace))
            ->action(trans('product::seller.mail.upload_finished.action'), frontRoute('seller.upload.show', [$this->upload->id]));
    }
}
