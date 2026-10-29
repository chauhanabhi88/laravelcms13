<?php

declare(strict_types=1);

namespace Modules\Review\DTO;

use Illuminate\Http\UploadedFile;

/**
 * What the customer submits for a review (web form or API): stars, optional title and text,
 * new photos. Blank text becomes null.
 */
final readonly class ReviewSubmission
{
    public ?string $title;

    public ?string $body;

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function __construct(
        public int $rating,
        ?string $title = null,
        ?string $body = null,
        public array $photos = [],
    ) {
        $this->title = self::clean($title, true);
        $this->body = self::clean($body, false);
    }

    /**
     * Trim, drop control characters, squeeze blank lines; single-line fields lose line breaks.
     */
    public static function clean(?string $text, bool $singleLine): ?string
    {
        $text = (string) preg_replace('/[^\P{C}\n]+/u', '', str_replace("\r\n", "\n", (string) $text));
        $text = $singleLine
            ? (string) preg_replace('/\s+/u', ' ', $text)
            : (string) preg_replace("/\n{3,}/", "\n\n", (string) preg_replace('/[ \t]+/u', ' ', $text));
        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
