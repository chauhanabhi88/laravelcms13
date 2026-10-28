<?php

declare(strict_types=1);

namespace Modules\Order\Support\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Order\Models\LabelBatch;

/**
 * Blade → PDF through dompdf (plan M14 §4; M17 renders its tax documents the same way).
 * Paper follows the label format: A4 portrait, or a 4 × 6 in thermal label. Remote resources
 * are never fetched; templates use inline CSS and the bundled DejaVu Sans (₹ and Devanagari
 * digits print correctly).
 */
class PdfRenderer
{
    /**
     * 4 × 6 inches in points (72 per inch).
     */
    public const THERMAL_PAPER = [0, 0, 288, 432];

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $format = LabelBatch::FORMAT_A4): string
    {
        return $this->fromHtml(view($view, $data + ['format' => $format])->render(), $format);
    }

    public function fromHtml(string $html, string $format = LabelBatch::FORMAT_A4): string
    {
        return Pdf::loadHTML($html)
            ->setPaper($format === LabelBatch::FORMAT_THERMAL ? self::THERMAL_PAPER : 'a4', 'portrait')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->output();
    }
}
