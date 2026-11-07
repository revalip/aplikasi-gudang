<?php

/*
 | Permissions come from the user's ROLE (Roles page) plus optional extra permissions ticked on the user.
 | Superadmin always has full access.
 |
 | Master-data modules use CRUD permissions named "<module>.<action>" (view / create / update / delete),
 | e.g. inventory.view, inventory.create, inventory.update, inventory.delete.
 | Workflow modules (PO, SO, WO, DO, ...) keep one permission per capability.
 | "legacy" permissions are the old coarse switches. They stay valid (they "imply" the CRUD set), but are
 | hidden in the UI.
 |
 | To add a new CRUD module: add one line to $crud and guard its routes with can:<module>.<action>.
 */

$actions = ['view' => 'View', 'create' => 'Create', 'update' => 'Edit', 'delete' => 'Delete'];

$crud = [
    'inventory' => ['label' => 'Inventory', 'actions' => ['view', 'create', 'update', 'delete'], 'description' => 'Master catalog of part numbers'],
    'warehouse' => ['label' => 'Warehouse Items', 'actions' => ['view', 'create', 'update', 'delete'], 'description' => 'Physical stock units, import & history'],
    'racks' => ['label' => 'Racks', 'actions' => ['view', 'create', 'delete'], 'description' => 'Warehouse racks'],
    'item-types' => ['label' => 'Item Types', 'actions' => ['view', 'create', 'delete'], 'description' => 'Item type categories'],
    'suppliers' => ['label' => 'Suppliers', 'actions' => ['view', 'create', 'delete'], 'description' => 'Supplier master data'],
];

$permissions = [];
foreach ($crud as $module => $def) {
    foreach ($def['actions'] as $action) {
        $permissions["{$module}.{$action}"] = [
            'label' => "{$def['label']} - {$actions[$action]}",
            'description' => "{$actions[$action]} in {$def['label']}",
            'module' => $module,
            'action' => $action,
        ];
    }
}

$workflow = [
    'purchasing' => ['Purchase Order', 'Create PO, receive items, cancel PO'],
    'purchasing.approve' => ['Approval Purchase Order', 'Approve / reject Purchase Orders'],
];
foreach ($workflow as $key => [$label, $description]) {
    $permissions[$key] = ['label' => $label, 'description' => $description, 'group' => 'workflow'];
}

// Old coarse switches: still honoured, expanded into the CRUD set.
$permissions['items.manage'] = [
    'label' => 'Warehouse Items (legacy)', 'legacy' => true,
    'implies' => ['inventory.create', 'inventory.update', 'inventory.delete', 'warehouse.create', 'warehouse.update', 'warehouse.delete',
        'racks.view', 'racks.create', 'racks.delete'],
];
$permissions['item-types.manage'] = ['label' => 'Item Types (legacy)', 'legacy' => true, 'implies' => ['item-types.view', 'item-types.create', 'item-types.delete']];
$permissions['suppliers'] = ['label' => 'Suppliers (legacy)', 'legacy' => true, 'implies' => ['suppliers.view', 'suppliers.create', 'suppliers.delete']];

// Reserved for superadmin.
$permissions['users.manage'] = ['label' => 'User Management & Permissions', 'superadmin_only' => true];
$permissions['activity-logs'] = ['label' => 'Activity Log', 'superadmin_only' => true];

return [
    'default' => 'user',

    // Fallback labels for built-in roles (the real roles live in the database, see App\Models\Role).
    'roles' => [
        'superadmin' => [
            'label' => 'Superadmin',
            'description' => 'Full access to all modules.',
            'colors' => ['#FCE8EF', '#B0104A'],
        ],
        'warehouse' => [
            'label' => 'Warehouse Staff',
            'description' => 'Can manage items, physical stock, racks, categories, suppliers, and receive goods.',
            'colors' => ['#E6F0F7', '#004E7A'],
        ],
        'sales' => [
            'label' => 'Purchasing / Sales',
            'description' => 'Can create and view Purchase Orders.',
            'colors' => ['#E7F6EC', '#1E7A3C'],
        ],
        'user' => [
            'label' => 'User',
            'description' => 'Can only access assigned modules.',
            'colors' => ['#E6F0F7', '#004E7A'],
        ],
    ],

    'everyone' => ['Dashboard'],

    'actions' => $actions,
    'crud' => $crud,
    'permissions' => $permissions,
];
