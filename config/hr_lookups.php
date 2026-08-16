<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\EmployeeDepartment;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\Program;
use App\Models\Rank;

return [
    'campuses' => [
        'name' => 'Campuses',
        'model' => Campus::class,
        'primary_key' => 'campus_id',
        'log_table' => 'tbl_campuses',
        'icon' => 'campus',
        'sort_order' => 2,
        'order' => ['campus_name' => 'asc'],
        'search' => ['campus_code', 'campus_name'],
        'columns' => [
            ['key' => 'campus_code', 'label' => 'Code'],
            ['key' => 'campus_name', 'label' => 'Name'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
        ],
        'fields' => [
            ['name' => 'campus_code', 'label' => 'Campus Code', 'type' => 'text', 'rules' => ['required', 'string', 'max:20'], 'unique' => true],
            ['name' => 'campus_name', 'label' => 'Campus Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'address', 'label' => 'Address', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:30']],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:255']],
            ['name' => 'website', 'label' => 'Website', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
        ],
    ],
    'designations' => [
        'name' => 'Designations',
        'model' => Designation::class,
        'primary_key' => 'designation_id',
        'log_table' => 'tbl_designations',
        'icon' => 'designation',
        'sort_order' => 3,
        'order' => ['designation_name' => 'asc'],
        'search' => ['designation_name'],
        'columns' => [
            ['key' => 'designation_name', 'label' => 'Designation'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
        ],
        'fields' => [
            ['name' => 'designation_name', 'label' => 'Designation Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'unique' => true],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
        ],
    ],
    'positions' => [
        'name' => 'Positions',
        'model' => Position::class,
        'primary_key' => 'position_id',
        'log_table' => 'tbl_positions',
        'icon' => 'position',
        'sort_order' => 4,
        'order' => ['position_name' => 'asc'],
        'search' => ['position_name'],
        'columns' => [
            ['key' => 'position_name', 'label' => 'Position'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
        ],
        'fields' => [
            ['name' => 'position_name', 'label' => 'Position Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:200'], 'unique' => true],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
        ],
    ],
    'ranks' => [
        'name' => 'Ranks',
        'model' => Rank::class,
        'primary_key' => 'rank_id',
        'log_table' => 'tbl_ranks',
        'icon' => 'rank',
        'sort_order' => 5,
        'order' => ['rank_name' => 'asc'],
        'search' => ['rank_name'],
        'columns' => [
            ['key' => 'rank_name', 'label' => 'Rank'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
        ],
        'fields' => [
            ['name' => 'rank_name', 'label' => 'Rank Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:200'], 'unique' => true],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
        ],
    ],
    'employment-types' => [
        'name' => 'Employment Types',
        'model' => EmploymentType::class,
        'primary_key' => 'employment_type_id',
        'log_table' => 'tbl_employment_types',
        'icon' => 'employment-type',
        'sort_order' => 6,
        'order' => ['sort_order' => 'asc', 'type_name' => 'asc'],
        'search' => ['type_code', 'type_name'],
        'columns' => [
            ['key' => 'type_code', 'label' => 'Code'],
            ['key' => 'type_name', 'label' => 'Type'],
            ['key' => 'sort_order', 'label' => 'Sort'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
        ],
        'fields' => [
            ['name' => 'type_code', 'label' => 'Type Code', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:20'], 'unique' => true],
            ['name' => 'type_name', 'label' => 'Type Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'unique' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rules' => ['nullable', 'string']],
            ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0']],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
        ],
    ],
    'employee-departments' => [
        'name' => 'Employee Departments',
        'model' => EmployeeDepartment::class,
        'primary_key' => 'employee_department_id',
        'log_table' => 'tbl_employee_departments',
        'icon' => 'department',
        'sort_order' => 7,
        'order' => ['sort_order' => 'asc', 'department_name' => 'asc'],
        'search' => ['department_name', 'department_code'],
        'columns' => [
            ['key' => 'department_name', 'label' => 'Department'],
            ['key' => 'sort_order', 'label' => 'Sort'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
        ],
        'fields' => [
            ['name' => 'department_code', 'label' => 'Department Code', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:50'], 'unique' => true],
            ['name' => 'department_name', 'label' => 'Department Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:150'], 'unique' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rules' => ['nullable', 'string']],
            ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0']],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
        ],
    ],
    'colleges' => [
        'name' => 'Colleges',
        'model' => College::class,
        'primary_key' => 'college_id',
        'log_table' => 'tbl_colleges',
        'icon' => 'college',
        'sort_order' => 8,
        'order' => ['college_name' => 'asc'],
        'search' => ['college_code', 'college_name'],
        'with' => ['campus'],
        'columns' => [
            ['key' => 'campus.campus_name', 'label' => 'Campus'],
            ['key' => 'college_code', 'label' => 'Code'],
            ['key' => 'college_name', 'label' => 'College'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
        ],
        'fields' => [
            ['name' => 'campus_id', 'label' => 'Campus', 'type' => 'select', 'source' => 'campuses', 'rules' => ['required', 'exists:tbl_campuses,campus_id']],
            ['name' => 'college_code', 'label' => 'College Code', 'type' => 'text', 'rules' => ['required', 'string', 'max:20']],
            ['name' => 'college_name', 'label' => 'College Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rules' => ['nullable', 'string']],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
        ],
    ],
    'programs' => [
        'name' => 'Programs',
        'model' => Program::class,
        'primary_key' => 'program_id',
        'log_table' => 'tbl_programs',
        'icon' => 'program',
        'sort_order' => 9,
        'order' => ['program_name' => 'asc'],
        'search' => ['program_code', 'program_name'],
        'with' => ['campus'],
        'columns' => [
            ['key' => 'campus.campus_name', 'label' => 'Campus'],
            ['key' => 'program_code', 'label' => 'Code'],
            ['key' => 'program_name', 'label' => 'Program'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
        ],
        'fields' => [
            ['name' => 'campus_id', 'label' => 'Campus', 'type' => 'select', 'source' => 'campuses', 'rules' => ['required', 'exists:tbl_campuses,campus_id']],
            ['name' => 'program_code', 'label' => 'Program Code', 'type' => 'text', 'rules' => ['required', 'string', 'max:20']],
            ['name' => 'program_name', 'label' => 'Program Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
        ],
    ],
    'document-types' => [
        'name' => 'Document Types',
        'model' => DocumentType::class,
        'primary_key' => 'document_type_id',
        'log_table' => 'tbl_document_types',
        'icon' => 'document-type',
        'sort_order' => 10,
        'order' => ['sort_order' => 'asc', 'type_name' => 'asc'],
        'search' => ['type_code', 'type_name'],
        'columns' => [
            ['key' => 'type_code', 'label' => 'Code'],
            ['key' => 'type_name', 'label' => 'Type'],
            ['key' => 'is_required', 'label' => 'Required', 'type' => 'boolean'],
            ['key' => 'sort_order', 'label' => 'Sort'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'boolean'],
        ],
        'fields' => [
            ['name' => 'type_code', 'label' => 'Type Code', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:20'], 'unique' => true],
            ['name' => 'type_name', 'label' => 'Type Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'unique' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rules' => ['nullable', 'string']],
            ['name' => 'is_required', 'label' => 'Required', 'type' => 'checkbox', 'default' => false, 'rules' => ['sometimes', 'boolean']],
            ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0']],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean']],
        ],
    ],
];
