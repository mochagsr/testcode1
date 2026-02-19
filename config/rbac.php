<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Role Permission Matrix
    |--------------------------------------------------------------------------
    |
    | Admin is treated as superuser in middleware, but explicit map is kept
    | for clarity and future extension.
    |
    */
    'roles' => [
        'admin' => ['*'],
        'user' => [
            'dashboard.view',
            'transactions.view',
            'transactions.create',
            'transactions.export',
            'transactions.correction.request',
            'receivables.view',
            'receivables.pay',
            'supplier_payables.view',
            'supplier_payables.pay',
            'reports.view',
            'reports.export',
            'settings.profile',
            'masters.suppliers.view',
            'masters.suppliers.edit',
        ],
    ],
    'permissions' => [
        'dashboard.view',
        'transactions.view',
        'transactions.create',
        'transactions.export',
        'transactions.cancel',
        'transactions.correction.request',
        'transactions.correction.approve',
        'receivables.view',
        'receivables.pay',
        'receivables.adjust',
        'supplier_payables.view',
        'supplier_payables.pay',
        'supplier_payables.adjust',
        'reports.view',
        'reports.export',
        'settings.profile',
        'settings.admin',
        'masters.products.view',
        'masters.products.manage',
        'masters.customers.view',
        'masters.customers.manage',
        'masters.suppliers.view',
        'masters.suppliers.edit',
        'imports.transactions',
        'semester.bulk',
        'users.manage',
        'audit_logs.view',
    ],
];
