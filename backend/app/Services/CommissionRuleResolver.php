<?php

namespace App\Services;

use App\Models\MarketerCommissionRule;
use Illuminate\Database\Eloquent\Model;

class CommissionRuleResolver
{
    /**
     * Order: marketer+category (walking parents) -> marketer scope default
     * -> [platform+category -> platform scope default] when $includePlatform.
     */
    public function resolve(?string $marketerId, string $scope, Model|string|null $category = null, bool $includePlatform = false): ?MarketerCommissionRule
    {
        $type = MarketerCommissionRule::categoryClassFor($scope);
        $ids = $this->categoryChain($category, $type);

        $owners = $marketerId ? [$marketerId] : [];
        if ($includePlatform || ! $marketerId) {
            $owners[] = null;
        }

        foreach ($owners as $owner) {
            $rows = MarketerCommissionRule::where('scope', $scope)
                ->when($owner, fn ($q) => $q->where('marketer_id', $owner), fn ($q) => $q->whereNull('marketer_id'))
                ->where(fn ($q) => $q->whereNull('category_id')
                    ->orWhere(fn ($q) => $q->where('category_type', $type)->whereIn('category_id', $ids ?: ['-'])))
                ->get()
                ->keyBy(fn ($r) => $r->category_id ?? '_default');

            foreach ($ids as $id) {
                if ($rows->has($id)) {
                    return $rows->get($id);
                }
            }
            if ($rows->has('_default')) {
                return $rows->get('_default');
            }
        }

        return null;
    }

    public function calculate(?string $marketerId, string $scope, Model|string|null $category, int|string $base, int $quantity = 1, bool $includePlatform = false, bool $round = false): int
    {
        return $this->resolve($marketerId, $scope, $category, $includePlatform)?->resolveAmount($base, $quantity, $round) ?? 0;
    }

    /** @return string[] category id then ancestors */
    private function categoryChain(Model|string|null $category, string $type): array
    {
        if ($category === null || $category === '') {
            return [];
        }
        if (is_string($category)) {
            $id = $category;
            $category = $type::find($id);
            if (! $category) {
                return [$id];
            }
        }
        $ids = [];
        $node = $category;
        while ($node && ! in_array($node->getKey(), $ids, true) && count($ids) < 20) {
            $ids[] = $node->getKey();
            $node = method_exists($node, 'parent') ? $node->parent : null;
        }

        return $ids;
    }
}
