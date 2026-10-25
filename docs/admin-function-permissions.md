# Admin function permissions

Super Admin manages roles at `/admin/roles`. Search the permission matrix and
select View, Add, Edit, Delete, and Execute for each supported function. A dash
means the application has no action of that type (for example, suppliers are
deactivated rather than deleted). Roles and permission assignments are reserved
for Super Admin. Users can still change their own password with the current
password check.

`app/Support/AdminPermissions.php` contains the function inventory and controller
operation mappings. Every protected admin endpoint, including the legacy
`/system-health` aliases, is authorized by `EnsureAdmin`. Unmapped endpoints are
denied. Existing broad `permission:*` route declarations defer to the operation
mapping. Adding a new admin route requires a mapping; the route coverage test
will fail when it is missing.

Bulk operations check the selected action. A permission to edit categories or
products never permits bulk deletion. Execute controls imports, sync queues,
approvals, dispatch, CSV exports, backup restores, migrations, and deployment as
described in the matrix. Receiving order stock also requires Inventory / Edit;
creating an order from an abandoned cart also requires Orders / Add.

The migration `2026_10_25_000003_expand_admin_function_permissions.php` converts
saved role and user permissions to explicit action keys. Existing role names,
accounts, and nullable role inheritance are retained. Broad legacy grants are
expanded once so individual actions can then be revoked independently. New role
and user forms submit explicit keys only. Role permission updates synchronize
assigned user permissions in a transaction. No database schema is dropped by
this migration or its rollback.

Staff with delegated user actions cannot edit a Super Admin, promote themselves,
change permission assignments, or assign access higher than their own. The last
Super Admin, current account deletion, protected built-in roles, and assigned
role dependency checks remain in place.

Verification includes all protected route mappings, denied access without grants,
independent Add/Edit/Delete/Execute requests, bulk deletion, role revocation,
custom-role login, and privilege escalation attempts. Run:

```sh
php artisan test
npm run test:components
npm run build
```
