<?php

use App\Models\DayType;
use App\Models\NdRateGroup;
use App\Models\RateGroup;

return [
    'rate-groups' => [
        'name' => 'Rate Group',
        'model' => RateGroup::class,
        'primary_key' => 'rate_group_id',
        'log_table' => 'tbl_rate_groups',
        'list_only' => true,
        'order' => ['rate_group_code' => 'asc'],
        'search' => ['rate_group_code', 'description'],
        'with' => ['rateBasis'],
        'columns' => [
            ['key' => 'rate_group_code', 'label' => 'Rate Group Code'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'rateBasis.rate_basis', 'label' => 'Rate Basis'],
        ],
    ],
    'nd-rate-groups' => [
        'name' => 'Night Diff. Rate Group',
        'model' => NdRateGroup::class,
        'primary_key' => 'nd_rate_group_id',
        'log_table' => 'tbl_nd_rate_groups',
        'list_only' => true,
        'order' => ['nd_rate_group_code' => 'asc'],
        'search' => ['nd_rate_group_code', 'description'],
        'with' => ['rateBasis'],
        'columns' => [
            ['key' => 'nd_rate_group_code', 'label' => 'ND Rate Group Code'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'rateBasis.rate_basis', 'label' => 'Rate Basis'],
            ['key' => 'tm_start', 'label' => 'Time Start'],
            ['key' => 'tm_end', 'label' => 'Time End'],
        ],
    ],
    'day-types' => [
        'name' => 'Day Type',
        'model' => DayType::class,
        'primary_key' => 'day_type_id',
        'log_table' => 'tbl_day_types',
        'order' => ['day_type_code' => 'asc'],
        'search' => ['day_type_code', 'description'],
        'with' => ['dayOfWeek'],
        'columns' => [
            ['key' => 'day_type_code', 'label' => 'Day Type Code'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'is_restday', 'label' => 'Restday', 'type' => 'check'],
            ['key' => 'is_special_holiday', 'label' => 'Special Holiday', 'type' => 'check'],
            ['key' => 'is_legal_holiday', 'label' => 'Legal Holiday', 'type' => 'check'],
            ['key' => 'dayOfWeek.day', 'label' => 'Day of the Week'],
        ],
        'fields' => [
            ['name' => 'day_type_code', 'label' => 'Day Type Code', 'type' => 'text', 'rules' => ['required', 'string', 'min:1', 'max:4'], 'unique' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'rules' => ['required', 'string', 'min:1', 'max:45']],
            ['name' => 'is_restday', 'label' => 'Restday', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
            ['name' => 'is_special_holiday', 'label' => 'Special Holiday', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
            ['name' => 'is_legal_holiday', 'label' => 'Legal Holiday', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
            ['name' => 'day_id', 'label' => 'Day of the Week', 'type' => 'select', 'source' => 'days', 'nullable' => true, 'rules' => ['nullable', 'integer', 'exists:lu_days,day_id']],
        ],
    ],
];
