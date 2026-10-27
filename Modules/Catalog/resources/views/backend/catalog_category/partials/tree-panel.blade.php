{{--
    Two panes: the category tree (drag & drop to re-parent / reorder, see modules/catalog/js/tree.js)
    and the details of the selected node, filled from the node's data attributes.
--}}
@php
    $canMove = auth()->user()?->can('admin.catalog_category.move');
@endphp
<div class="row catalog-tree-panel mb-4" id="catalog-tree-panel"
    data-move-url="{{ route('admin.catalog_category.move', updateUrlParams()) }}"
    data-index-url="{{ route('admin.catalog_category.index', updateUrlParams()) }}"
    data-csrf="{{ csrf_token() }}"
    data-selected="{{ $selectedId ?: '' }}"
    data-max-depth="{{ $maxDepth }}">
    <div class="col-lg-5 mb-3 mb-lg-0">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong>{{ trans('catalog::catalog_category.titles.tree') }}</strong>
                <span>
                    <button type="button" class="btn btn-sm btn-link" data-tree-expand="1">{{ trans('catalog::catalog_category.labels.expand_all') }}</button>
                    <button type="button" class="btn btn-sm btn-link" data-tree-expand="0">{{ trans('catalog::catalog_category.labels.collapse_all') }}</button>
                </span>
            </div>
            <div class="card-body">
                <input type="search" class="form-control form-control-sm mb-2" data-tree-search placeholder="{{ trans('catalog::catalog_category.labels.search_tree') }}" aria-label="{{ trans('catalog::catalog_category.labels.search_tree') }}">
                @if ($canMove)
                    <p class="text-muted small mb-2">{{ trans('catalog::catalog_category.labels.drag_help') }}</p>
                @endif
                <div class="catalog-tree" role="tree" aria-label="{{ trans('catalog::catalog_category.titles.tree') }}">
                    @if ($tree === [])
                        <p class="text-muted mb-0">{{ trans('core::core.messages.no_records') }}</p>
                    @else
                        @include('catalog::backend.catalog_category.partials.tree-nodes', ['nodes' => $tree, 'parentId' => null, 'canMove' => $canMove])
                    @endif
                </div>
                <div class="catalog-tree__root-drop" data-root-drop hidden>{{ trans('catalog::catalog_category.labels.root') }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><strong>{{ trans('catalog::catalog_category.titles.details') }}</strong></div>
            <div class="card-body" data-tree-details>
                <p class="text-muted mb-0" data-details-empty>{{ trans('catalog::catalog_category.labels.select_node') }}</p>
                <div data-details-body hidden>
                    <h5 class="mb-1" data-field="name"></h5>
                    <p class="text-muted small mb-3" data-field="path"></p>
                    <table class="table table-sm mb-3">
                        <tr><th class="w-25">{{ trans('catalog::catalog_category.labels.slug') }}</th><td data-field="slug"></td></tr>
                        <tr><th>{{ trans('catalog::catalog_category.labels.depth') }}</th><td data-field="level"></td></tr>
                        <tr><th>{{ trans('catalog::catalog_category.labels.is_leaf') }}</th><td data-field="leaf"></td></tr>
                        <tr><th>{{ trans('catalog::catalog_category.labels.children_count') }}</th><td data-field="children"></td></tr>
                        <tr><th>{{ trans('catalog::catalog_category.labels.show_in_menu') }}</th><td data-field="menu"></td></tr>
                        <tr><th>{{ trans('catalog::catalog_category.labels.status') }}</th><td data-field="status"></td></tr>
                    </table>
                    @can('admin.catalog_category.edit')
                        <a href="#" class="btn btn-primary btn-sm" data-action="edit"><i class="fas fa-edit"></i> {{ trans('core::core.labels.edit') }}</a>
                    @endcan
                    @can('admin.catalog_category.create')
                        <a href="#" class="btn btn-outline-primary btn-sm" data-action="add"><i class="fas fa-plus"></i> {{ trans('catalog::catalog_category.labels.add_child') }}</a>
                    @endcan
                    @can('admin.catalog_category.attributes')
                        <a href="#" class="btn btn-outline-secondary btn-sm" data-action="attributes"><i class="fas fa-list"></i> {{ trans('catalog::catalog_category.labels.manage_attributes') }}</a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>
