<?php

namespace App\Traits;

use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HasDataTable
{
    /**
     * Build a DataTables-compatible JSON response for server-side processing.
     *
     * @param  Request   $request         The incoming DataTables AJAX request.
     * @param  Builder   $query           Base Eloquent query (without pagination/ordering applied).
     * @param  array     $columns         Column definitions. Each entry may have:
     *                                     - 'searchable_columns' (array)  columns to search on globally
     *                                     - 'orderable_column'  (string)  column used for ORDER BY
     * @param  callable  $rowTransformer  Closure(model) → array  transforms each row for the response.
     */
    public function dataTableResponse(
        Request $request,
        Builder|QueryBuilder $query,
        array $columns,
        callable $rowTransformer
    ): JsonResponse {
        $draw = (int) $request->input('draw', 1);
        $totalRecords = (clone $query)->count();

        // ── Global search ──────────────────────────────────────────────────
        $searchValue = $request->input('search.value', '');
        if ($searchValue !== '' && $searchValue !== null) {
            $searchable = array_filter(
                $columns,
                fn($c) => !empty($c['searchable_columns'])
            );

            if (!empty($searchable)) {
                $query->where(function (Builder|QueryBuilder $q) use ($searchable, $searchValue) {
                    foreach ($searchable as $col) {
                        foreach ($col['searchable_columns'] as $dbCol) {
                            $q->orWhere($dbCol, 'like', '%' . $searchValue . '%');
                        }
                    }
                });
            }
        }

        // ── Per-column search ───────────────────────────────────────────────
        foreach ($request->input('columns', []) as $i => $col) {
            $colSearch = $col['search']['value'] ?? '';
            if ($colSearch !== '' && isset($columns[$i]['searchable_columns'])) {
                foreach ($columns[$i]['searchable_columns'] as $dbCol) {
                    $query->where($dbCol, 'like', '%' . $colSearch . '%');
                }
            }
        }

        // ── Ordering ────────────────────────────────────────────────────────
        $orderApplied = false;
        foreach ($request->input('order', []) as $ord) {
            $colIndex = (int) ($ord['column'] ?? 0);
            $dir = strtolower($ord['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            if (isset($columns[$colIndex]['orderable_column'])) {
                $query->orderBy($columns[$colIndex]['orderable_column'], $dir);
                $orderApplied = true;
            }
        }

        // ── Count after search filters ──────────────────────────────────────
        $filteredRecords = (clone $query)->count();

        // ── Paginate ─────────────────────────────────────────────────────────
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length < 1)
            $length = 25;

        $data = $query
            ->skip($start)
            ->take($length)
            ->get()
            ->map($rowTransformer)
            ->values();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Helper: apply extra filter scopes from request input.
     * Call inside the controller before passing $query to dataTableResponse().
     *
     *   $query = $this->applyFilters($query, $request, [
     *       'status'     => fn($q, $v) => $q->where('status', $v),
     *       'vendor_id'  => fn($q, $v) => $q->where('vendor_id', $v),
     *       'date_from'  => fn($q, $v) => $q->whereDate('created_at', '>=', $v),
     *       'date_to'    => fn($q, $v) => $q->whereDate('created_at', '<=', $v),
     *   ]);
     */
    protected function applyFilters(Builder|QueryBuilder|Relation $query, Request $request, array $scopes): Builder|QueryBuilder|Relation
    {
        foreach ($scopes as $param => $scope) {
            $value = $request->input($param);
            if ($value !== null && $value !== '' && !is_array($value)) {
                $scope($query, $value);
            }
        }
        return $query;
    }
}
