

# Filament Resource Map

All Filament resources, pages, and their locations. Updated as each milestone is built.

---

## Admin Panel (`/admin`)

Provider: `app/Providers/Filament/AdminPanelProvider.php`

Discovery paths:
- Resources: `app/Filament/Resources`
- Pages: `app/Filament/Pages`
- Widgets: `app/Filament/Widgets`

### Milestone 1 Resources

| Resource | File | Model | Roles |
|---|---|---|---|
| `UserResource` | `app/Filament/Resources/UserResource.php` | `User` | `admin`, `super_admin` |
| `CountryResource` | `app/Filament/Resources/CountryResource.php` | `Country` | `admin`, `super_admin` |
| `VisaTypeResource` | `app/Filament/Resources/VisaTypeResource.php` | `VisaType` | `admin`, `super_admin` |
| `VisaFeeResource` | `app/Filament/Resources/VisaFeeResource.php` | `VisaFee` | `admin`, `super_admin` |

### Milestone 2 Resources

| Resource | File | Model | Roles |
|---|---|---|---|
| `FormTemplateResource` | `app/Filament/Resources/FormTemplateResource.php` | `FormTemplate` | `admin`, `super_admin` |
| `VisaApplicationResource` | `app/Filament/Resources/VisaApplicationResource.php` | `VisaApplication` | All back-office roles |

### Milestone 3 Resources

| Resource | File | Model | Roles |
|---|---|---|---|
| `DocumentTypeResource` | `app/Filament/Resources/DocumentTypeResource.php` | `DocumentType` | `admin`, `super_admin` |

### Milestone 4 Resources

| Resource | File | Model | Roles |
|---|---|---|---|
| `PaymentResource` | `app/Filament/Resources/PaymentResource.php` | `Payment` | `admin`, `finance_officer`, `super_admin` |

---

## Officer Panel

> Note: The officer panel is a separate Filament panel. Provider to be created in Milestone 5.

Planned path: `/officer`

### Milestone 5 Resources

| Resource | Visibility |
|---|---|
| `VisaApplicationResource` (officer view) | Only assigned applications (scoped by policy) |

---

## Authorization Pattern

Every Filament resource must:
1. Use a Policy for `viewAny`, `view`, `create`, `update`, `delete`
2. Call `->authorize(...)` on every custom action (Filament does not auto-authorize these)
3. Never expose data outside the authenticated user's policy scope

---

## Custom Pages

| Page | Path | Purpose |
|---|---|---|
| Horizon dashboard | `/horizon` | Queue monitoring (admin only) |
| Audit log review | TBD in Milestone 7 | Read-only audit log viewer |
