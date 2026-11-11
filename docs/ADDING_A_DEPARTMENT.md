# Adding a New Staff Department

Wildcrest Lodges & Camps models departments declaratively across code enums, permission maps, navigation configuration, database metadata, and isolated non-web storage folders. Adding a new department (for example, `Human Resources`) requires zero edits to authentication logic or core RBAC checks.

Look for `// [FUTURE:HR-MODULE]` tags across the repository to locate every integration seam.

---

## Step-by-Step Checklist

### 1. Update `App\Support\Enums\Department` (`src/Support/Enums/Department.php`)

Add the new backed string enum case and update its match arms (`label()` and `role()`):

```php
case HumanResources = 'hr';
```

- `label()`: return `'Human Resources'`.
- `role()`: return the owning `Role` level (for example, `Role::Admin` for a collaborative Level-1 peer, or `Role::Manager` if managed at Level 3).

### 2. Declare Access Rules in `config/permissions.php`

Every permission is deny-by-default. Explicitly add the new department slug `'hr'` to the appropriate arrays:

- Each role's `'visible_departments'` list that may view its dashboard, tasks, and files.
- Each role's `'writable_departments'` list that may create tasks or upload files in it.
- If the new department belongs to Level-1 collaborative read-only sharing, add `'hr'` under `'level1_cross_department_read.departments'` as well.

### 3. Add Sidebar Navigation in `config/navigation.php`

Register an item in the appropriate navigation group:

```php
[
    'key' => 'dept-hr',
    'label' => 'Human Resources',
    'path' => '/staff/hr/dashboard',
    'department' => Department::HumanResources->value,
    'badge_text' => 'L1',
],
```

`App\Support\Navigation\NavigationRegistry` automatically filters visibility using `AccessControl::canViewDepartment()`.

### 4. Provision Non-Web Department Storage

Create the isolated storage folder (matching the slug strictly) alongside `.htaccess` deny-all defense-in-depth and `.gitkeep`:

```text
storage/departments/hr/
  .gitkeep
  .htaccess   # Copy from storage/departments/general-admin/.htaccess
```

### 5. Seed the Department Row in `database/seed.sql`

Add an idempotent `INSERT INTO departments (...) ... ON DUPLICATE KEY UPDATE` entry with matching `slug = 'hr'`, `name`, and `role_level`, and assign test/operational accounts accordingly.

### 6. Add Dashboard Presentation Data & View

1. Add a `Department::HumanResources` match arm in `App\Modules\Staff\Services\StaffDashboardService::departmentPresentation()`.
2. Create `resources/views/staff/hr/dashboard.blade.php` extending `layouts.staff` and including:
   - `staff._partials.readonly-banner`
   - `staff._partials.task-widget`
   - `staff._partials.file-widget`

Note: Routes `/staff/{department}/dashboard`, `/staff/{department}/tasks`, and `/staff/{department}/files` already resolve dynamic `Department` slugs through `RequireDepartmentMiddleware` — no manual route duplication is required.

### 7. Verify Tests & Linters

```bash
composer check
npm run lint:js
```
