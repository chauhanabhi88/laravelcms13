{{-- Report navigation: only the reports the admin may open; the period is kept. --}}
<nav class="rpt-tabs" aria-label="{{ trans('report::report.title') }}">
    <ul class="nav nav-pills">
        @foreach ($tabs as $tab)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $report ? 'active' : '' }}" href="{{ route('admin.report.'.$tab, updateUrlParams([$range->toQuery()])) }}"
                   @if ($tab === $report) aria-current="page" @endif>{{ trans('report::report.reports.'.$tab) }}</a>
            </li>
        @endforeach
    </ul>
</nav>
