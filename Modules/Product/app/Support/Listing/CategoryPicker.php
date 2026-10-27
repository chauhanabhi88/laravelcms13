<?php

declare(strict_types=1);

namespace Modules\Product\Support\Listing;

use Illuminate\Support\Str;
use Modules\Catalog\Services\CategoryTreeService;

/**
 * Leaf categories a seller can list under (enabled, with every ancestor enabled), for the
 * wizard's type-ahead and browse tree and the bulk template picker.
 *
 * @phpstan-type Leaf array{id: int, name: string, breadcrumb: string}
 */
class CategoryPicker
{
    public function __construct(protected CategoryTreeService $tree) {}

    /**
     * Every listable leaf, "Ethnic Wear › Kurtis › Anarkali" breadcrumbs, in tree order.
     *
     * @return list<Leaf>
     */
    public function leaves(): array
    {
        $leaves = [];

        $walk = function (array $nodes, array $trail) use (&$walk, &$leaves): void {
            foreach ($nodes as $node) {
                $path = [...$trail, (string) $node['name']];

                if ($node['children'] === [] && (int) $node['is_leaf'] === (int) config('core.yes')) {
                    $leaves[] = ['id' => (int) $node['id'], 'name' => (string) $node['name'], 'breadcrumb' => implode(' › ', $path)];
                }

                $walk($node['children'], $path);
            }
        };

        $walk($this->tree->tree(), []);

        return $leaves;
    }

    /**
     * Leaves whose name or breadcrumb contains every word of $query; name matches first.
     *
     * @return list<Leaf>
     */
    public function search(string $query, int $limit = 20): array
    {
        $words = array_values(array_filter(preg_split('/\s+/', Str::lower(trim($query))) ?: []));

        if ($words === []) {
            return [];
        }

        $matches = array_filter($this->leaves(), function (array $leaf) use ($words): bool {
            $haystack = Str::lower($leaf['breadcrumb']);

            foreach ($words as $word) {
                if (! str_contains($haystack, $word)) {
                    return false;
                }
            }

            return true;
        });

        usort($matches, fn (array $a, array $b): int => [! str_contains(Str::lower($a['name']), $words[0]), $a['breadcrumb']] <=> [! str_contains(Str::lower($b['name']), $words[0]), $b['breadcrumb']]);

        return array_slice(array_values($matches), 0, $limit);
    }

    public function isListable(int $categoryId): bool
    {
        return in_array($categoryId, array_column($this->leaves(), 'id'), true);
    }

    /**
     * The enabled tree (nested, with "children") for the browse panel.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(): array
    {
        return $this->tree->tree();
    }

    /**
     * Select options: leaf id => breadcrumb.
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        return array_column($this->leaves(), 'breadcrumb', 'id');
    }

    public function breadcrumb(int $categoryId): string
    {
        return $this->tree->breadcrumb($categoryId);
    }
}
