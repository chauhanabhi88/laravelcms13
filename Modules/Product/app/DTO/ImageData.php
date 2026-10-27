<?php

declare(strict_types=1);

namespace Modules\Product\DTO;

/**
 * An already stored product image (ProductImageService::store()), in display order.
 */
final readonly class ImageData
{
    public function __construct(
        public string $path,
        public ?int $attributeOptionId = null,
        public ?string $alt = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $contentHash = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            path: (string) ($data['path'] ?? ''),
            attributeOptionId: isset($data['attribute_option_id']) && $data['attribute_option_id'] !== '' ? (int) $data['attribute_option_id'] : null,
            alt: isset($data['alt']) && $data['alt'] !== '' ? (string) $data['alt'] : null,
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
            contentHash: isset($data['content_hash']) && $data['content_hash'] !== '' ? (string) $data['content_hash'] : null,
        );
    }

    /**
     * @return array{path: string, attribute_option_id: int|null, alt: string|null, width: int|null, height: int|null, content_hash: string|null}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'attribute_option_id' => $this->attributeOptionId,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'content_hash' => $this->contentHash,
        ];
    }
}
