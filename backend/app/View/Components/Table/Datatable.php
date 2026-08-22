<?php

namespace App\View\Components\Table;

use Illuminate\View\Component;

class Datatable extends Component
{
    public string $id;
    public string $url;
    public array $columns;
    public array $filters;
    public array $bulkActions;
    public ?array $createAction;
    public int $pageLength;
    public array $order;
    public bool $selectable;
    public bool $responsive;

    /** JSON-encoded options passed to window.initDataTable() */
    public string $optionsJson;

    public function __construct(
        string $id,
        string $url,
        array $columns = [],
        array $filters = [],
        array $bulkActions = [],
        ?array $createAction = null,
        int $pageLength = 25,
        array $order = [[0, 'desc']],
        bool $selectable = false,
        bool $responsive = true,
    ) {
        $this->id = $id;
        $this->url = $url;
        $this->columns = $columns;
        $this->filters = $filters;
        $this->bulkActions = $bulkActions;
        $this->createAction = $createAction;
        $this->pageLength = $pageLength;
        $this->order = $order;
        $this->selectable = $selectable;
        $this->responsive = $responsive;

        // Build the JS options object passed to initDataTable()
        $jsColumns = [];
        foreach ($columns as $col) {
            $jsCol = ['data' => $col['data'] ?? null, 'name' => $col['name'] ?? ''];
            if (isset($col['orderable']))
                $jsCol['orderable'] = $col['orderable'];
            if (isset($col['searchable']))
                $jsCol['searchable'] = $col['searchable'];
            if (isset($col['className']))
                $jsCol['className'] = $col['className'];
            if (isset($col['width']))
                $jsCol['width'] = $col['width'];
            if (isset($col['render']))
                $jsCol['render'] = $col['render'];  // raw JS expression string
            $jsColumns[] = $jsCol;
        }

        if ($selectable) {
            array_unshift($jsColumns, [
                'data' => null,
                'name' => 'select',
                'orderable' => false,
                'searchable' => false,
                'className' => 'dt-select-col',
                'render' => '__DT_SELECT_CHECKBOX__', // replaced by JS
            ]);
        }

        $this->optionsJson = json_encode([
            'url' => $url,
            'columns' => $jsColumns,
            'pageLength' => $pageLength,
            'order' => $order,
            'selectable' => $selectable,
            'responsive' => $responsive,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function render()
    {
        return view('components.table.datatable');
    }
}
