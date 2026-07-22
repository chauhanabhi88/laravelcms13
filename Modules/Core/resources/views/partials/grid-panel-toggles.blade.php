{{-- Show/hide buttons for the Columns & Filters grid panels.
     Revealed and wired up by syncGridPanelToggles() in modules/core/js/backend/custom.js --}}
<button type="button" class="btn gp-toggle btn-fw" data-gp-panel="columns" aria-pressed="false" style="display:none;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/></svg>
    {{ trans('core::core.labels.columns') }}
</button>
<button type="button" class="btn gp-toggle" data-gp-panel="filters" aria-pressed="false" style="display:none;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>
    {{ trans('core::core.labels.filter') }}
    <span class="gp-toggle-badge" style="display:none;"></span>
</button>
