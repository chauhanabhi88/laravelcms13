{{-- dompdf page shell for order documents (PdfRenderer). $format: a4_4up | thermal_4x6. Inline CSS only: remote assets are disabled. --}}
@php
    $thermal = ($format ?? 'a4_4up') === 'thermal_4x6';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: {{ $thermal ? '8pt' : '28pt 32pt' }}; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: {{ $thermal ? '7.5pt' : '9pt' }}; color: #0f172a; line-height: 1.35; margin: 0; }
        h1 { font-size: {{ $thermal ? '10pt' : '14pt' }}; margin: 0 0 4pt; }
        h2 { font-size: {{ $thermal ? '8.5pt' : '10.5pt' }}; margin: 0 0 3pt; text-transform: uppercase; letter-spacing: .4pt; color: #334155; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: {{ $thermal ? '2pt 3pt' : '4pt 5pt' }}; vertical-align: top; text-align: left; }
        .grid th { background: #f1f5f9; border: 1px solid #cbd5e1; font-size: {{ $thermal ? '7pt' : '8pt' }}; }
        .grid td { border: 1px solid #e2e8f0; }
        .box { border: 1px solid #94a3b8; padding: {{ $thermal ? '4pt' : '7pt' }}; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .center { text-align: center; }
        .strong { font-weight: bold; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; }
        .awb { font-size: {{ $thermal ? '11pt' : '13pt' }}; font-weight: bold; letter-spacing: 1pt; }
        .chip { display: inline-block; border: 1px solid #0f766e; color: #0f766e; padding: 1pt 5pt; font-weight: bold; }
        .page-break { page-break-after: always; }
        .spacer { height: {{ $thermal ? '5pt' : '10pt' }}; }
        .signature { height: 34pt; border-bottom: 1px solid #94a3b8; }
    </style>
</head>

<body>
    @yield('content')
</body>

</html>
