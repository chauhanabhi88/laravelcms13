<ul class="catalog-tree__list" role="group" data-parent-id="{{ $parentId ?? '' }}">
    @foreach ($nodes as $node)
        @php
            $isEnabled = $node['status'] === (int) config('core.enabled');
            $inMenu = $node['show_in_menu'] === (int) config('core.yes');
            $isLeaf = $node['is_leaf'] === (int) config('core.yes');
        @endphp
        <li class="catalog-tree__node" role="treeitem" aria-expanded="{{ $node['children'] ? 'false' : 'true' }}"
            data-id="{{ $node['id'] }}"
            data-name="{{ $node['name'] }}"
            data-slug="{{ $node['slug'] }}"
            data-depth="{{ $node['depth'] }}"
            data-leaf="{{ $isLeaf ? trans('catalog::catalog_category.labels.yes') : trans('catalog::catalog_category.labels.no') }}"
            data-menu="{{ $inMenu ? trans('catalog::catalog_category.labels.yes') : trans('catalog::catalog_category.labels.no') }}"
            data-status="{{ $isEnabled ? trans('core::core.options.status.enable') : trans('core::core.options.status.disable') }}"
            data-children="{{ count($node['children']) }}"
            data-edit-url="{{ route('admin.catalog_category.edit', updateUrlParams([$node['id']])) }}"
            data-add-url="{{ route('admin.catalog_category.create', updateUrlParams() + ['parent_id' => $node['id']]) }}"
            data-attributes-url="{{ route('admin.catalog_category.attributes', updateUrlParams([$node['id']])) }}">
            <div class="catalog-tree__row" @if ($canMove) draggable="true" @endif>
                @if ($node['children'])
                    <button type="button" class="catalog-tree__toggle" data-tree-toggle aria-label="{{ $node['name'] }}"><i class="fas fa-caret-right"></i></button>
                @else
                    <span class="catalog-tree__toggle catalog-tree__toggle--empty"></span>
                @endif
                @if ($canMove)
                    <span class="catalog-tree__handle" aria-hidden="true"><i class="fas fa-grip-vertical"></i></span>
                @endif
                <a href="#" class="catalog-tree__label {{ $isEnabled ? '' : 'text-muted' }}" data-tree-select>{{ $node['name'] }}</a>
                @unless ($isEnabled)
                    <span class="badge badge-secondary">{{ trans('core::core.options.status.disable') }}</span>
                @endunless
                @unless ($inMenu)
                    <span class="badge badge-light" title="{{ trans('catalog::catalog_category.labels.show_in_menu') }}"><i class="fas fa-eye-slash"></i></span>
                @endunless
            </div>
            @if ($node['children'])
                @include('catalog::backend.catalog_category.partials.tree-nodes', ['nodes' => $node['children'], 'parentId' => $node['id'], 'canMove' => $canMove])
            @endif
        </li>
    @endforeach
</ul>
