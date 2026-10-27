/*
 * Catalog category tree (admin): expand/collapse, filter, details pane and native HTML5
 * drag & drop. Dropping on the middle of a row makes the dragged category its child;
 * dropping on the top or bottom edge places it before or after that row. The move is
 * saved through admin.catalog_category.move, then the page reloads with the moved
 * category selected, so the tree always reflects the server state.
 */
(function () {
    'use strict';

    var panel = document.getElementById('catalog-tree-panel');
    if (!panel) {
        return;
    }

    var tree = panel.querySelector('.catalog-tree');
    var details = panel.querySelector('[data-tree-details]');
    var rootDrop = panel.querySelector('[data-root-drop]');
    var dragged = null;

    function nodeOf(element) {
        return element ? element.closest('.catalog-tree__node') : null;
    }

    function setExpanded(node, expanded) {
        if (node.querySelector(':scope > .catalog-tree__list')) {
            node.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    function expandAncestors(node) {
        var parent = nodeOf(node.parentElement);
        while (parent) {
            setExpanded(parent, true);
            parent = nodeOf(parent.parentElement);
        }
    }

    function pathOf(node) {
        var names = [];
        var current = node;
        while (current) {
            names.unshift(current.dataset.name);
            current = nodeOf(current.parentElement);
        }
        return names.join(' › ');
    }

    function select(node) {
        tree.querySelectorAll('.catalog-tree__node.is-selected').forEach(function (selected) {
            selected.classList.remove('is-selected');
        });
        node.classList.add('is-selected');
        expandAncestors(node);

        var body = details.querySelector('[data-details-body]');
        details.querySelector('[data-details-empty]').hidden = true;
        body.hidden = false;

        var fields = {
            name: node.dataset.name,
            path: pathOf(node),
            slug: node.dataset.slug,
            level: String(parseInt(node.dataset.depth, 10) + 1),
            leaf: node.dataset.leaf,
            children: node.dataset.children,
            menu: node.dataset.menu,
            status: node.dataset.status
        };
        Object.keys(fields).forEach(function (key) {
            var target = body.querySelector('[data-field="' + key + '"]');
            if (target) {
                target.textContent = fields[key];
            }
        });

        var actions = { edit: node.dataset.editUrl, add: node.dataset.addUrl, attributes: node.dataset.attributesUrl };
        Object.keys(actions).forEach(function (key) {
            var link = body.querySelector('[data-action="' + key + '"]');
            if (link) {
                link.href = actions[key];
            }
        });

        var addLink = body.querySelector('[data-action="add"]');
        if (addLink) {
            addLink.hidden = parseInt(node.dataset.depth, 10) + 1 >= parseInt(panel.dataset.maxDepth, 10);
        }
    }

    function notify(type, message) {
        if (window.customObj && typeof window.customObj.showMessage === 'function') {
            window.customObj.showMessage(type, message);
        } else {
            window.alert(message);
        }
    }

    /* Expand / collapse / select */
    tree.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-tree-toggle]');
        if (toggle) {
            var node = nodeOf(toggle);
            setExpanded(node, node.getAttribute('aria-expanded') !== 'true');
            return;
        }

        var label = event.target.closest('[data-tree-select]');
        if (label) {
            event.preventDefault();
            select(nodeOf(label));
        }
    });

    panel.querySelectorAll('[data-tree-expand]').forEach(function (button) {
        button.addEventListener('click', function () {
            var expand = button.dataset.treeExpand === '1';
            tree.querySelectorAll('.catalog-tree__node').forEach(function (node) {
                setExpanded(node, expand);
            });
        });
    });

    /* Filter by name: matching nodes and their ancestors stay visible */
    var search = panel.querySelector('[data-tree-search]');
    if (search) {
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            var nodes = tree.querySelectorAll('.catalog-tree__node');

            nodes.forEach(function (node) {
                node.hidden = term !== '';
            });
            if (term === '') {
                return;
            }
            nodes.forEach(function (node) {
                if (node.dataset.name.toLowerCase().indexOf(term) !== -1) {
                    var current = node;
                    while (current) {
                        current.hidden = false;
                        setExpanded(current, true);
                        current = nodeOf(current.parentElement);
                    }
                }
            });
        });
    }

    /* Drag & drop */
    function clearMarkers() {
        tree.querySelectorAll('.drop-before, .drop-after, .drop-inside').forEach(function (row) {
            row.classList.remove('drop-before', 'drop-after', 'drop-inside');
        });
        rootDrop.classList.remove('is-over');
    }

    function zoneOf(row, event) {
        var rect = row.getBoundingClientRect();
        var offset = event.clientY - rect.top;
        if (offset < rect.height * 0.25) {
            return 'before';
        }
        if (offset > rect.height * 0.75) {
            return 'after';
        }
        return 'inside';
    }

    function isInvalidTarget(targetNode) {
        return !dragged || targetNode === dragged || dragged.contains(targetNode);
    }

    function save(parentId, position) {
        var body = new FormData();
        body.append('id', dragged.dataset.id);
        body.append('parent_id', parentId || '');
        body.append('position', position);

        var movedId = dragged.dataset.id;

        fetch(panel.dataset.moveUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': panel.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: body,
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, json: json };
                });
            })
            .then(function (result) {
                if (!result.ok || result.json.type !== 'success') {
                    notify('error', (result.json && result.json.message) || 'Error');
                    return;
                }
                var url = new URL(panel.dataset.indexUrl, window.location.href);
                url.searchParams.set('selected', movedId);
                window.location.href = url.toString();
            })
            .catch(function () {
                notify('error', 'Error');
            });
    }

    tree.addEventListener('dragstart', function (event) {
        var row = event.target.closest('.catalog-tree__row');
        if (!row) {
            return;
        }
        dragged = nodeOf(row);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', dragged.dataset.id);
        dragged.classList.add('is-dragging');
        rootDrop.hidden = false;
    });

    tree.addEventListener('dragend', function () {
        if (dragged) {
            dragged.classList.remove('is-dragging');
        }
        dragged = null;
        clearMarkers();
        rootDrop.hidden = true;
    });

    tree.addEventListener('dragover', function (event) {
        var row = event.target.closest('.catalog-tree__row');
        var targetNode = nodeOf(row);
        if (!row || isInvalidTarget(targetNode)) {
            return;
        }
        event.preventDefault();
        clearMarkers();
        row.classList.add('drop-' + zoneOf(row, event));
    });

    tree.addEventListener('dragleave', function (event) {
        var row = event.target.closest('.catalog-tree__row');
        if (row && !row.contains(event.relatedTarget)) {
            row.classList.remove('drop-before', 'drop-after', 'drop-inside');
        }
    });

    tree.addEventListener('drop', function (event) {
        var row = event.target.closest('.catalog-tree__row');
        var targetNode = nodeOf(row);
        if (!row || isInvalidTarget(targetNode)) {
            return;
        }
        event.preventDefault();

        var zone = zoneOf(row, event);
        clearMarkers();

        if (zone === 'inside') {
            var childList = targetNode.querySelector(':scope > .catalog-tree__list');
            var count = childList ? childList.querySelectorAll(':scope > .catalog-tree__node').length : 0;
            save(targetNode.dataset.id, count);
            return;
        }

        var list = targetNode.parentElement;
        var siblings = Array.prototype.filter.call(list.children, function (sibling) {
            return sibling !== dragged;
        });
        var index = siblings.indexOf(targetNode) + (zone === 'after' ? 1 : 0);
        save(list.dataset.parentId, index);
    });

    rootDrop.addEventListener('dragover', function (event) {
        if (dragged) {
            event.preventDefault();
            rootDrop.classList.add('is-over');
        }
    });
    rootDrop.addEventListener('dragleave', function () {
        rootDrop.classList.remove('is-over');
    });
    rootDrop.addEventListener('drop', function (event) {
        if (!dragged) {
            return;
        }
        event.preventDefault();
        var roots = tree.querySelectorAll(':scope > .catalog-tree__list > .catalog-tree__node');
        save('', Array.prototype.filter.call(roots, function (node) { return node !== dragged; }).length);
    });

    /* Initial selection (after create / edit / move) */
    var selected = panel.dataset.selected && tree.querySelector('.catalog-tree__node[data-id="' + panel.dataset.selected + '"]');
    if (selected) {
        select(selected);
        selected.scrollIntoView({ block: 'nearest' });
    }
})();
