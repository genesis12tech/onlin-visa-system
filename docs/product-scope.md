# Product Scope

## What This System Does

An end-to-end visa application management platform. It handles the full lifecycle:

**Applicant side:**
1. Register and verify email
2. Create and maintain a profile (personal details, passport info)
3. Start a visa application for a specific visa type
4. Fill a dynamic form defined by admins per visa type
5. Upload required supporting documents
6. Submit the application
7. Pay the application fee via Stripe Checkout
8. Track application status with a non-sequential tracking number
9. Receive email and in-app notifications at every key event
10. Respond to officer requests for additional information

**Officer side:**
1. View the queue of applications assigned to them
2. Review applicant profiles, form answers, and documents
3. Accept or reject individual documents
4. Request additional information from the applicant
5. Schedule appointments (if required)
6. Make a final decision: approve or reject
7. Generate a decision letter from the immutable submitted snapshot

**Admin side:**
1. Manage visa types, fee structures, and form templates
2. Manage user accounts and role assignments
3. Manage document type requirements per visa type
4. Manage country reference data
5. Access reports and exports
6. Review audit logs

---

## In Scope (MVP)

- Tourist visa application — one complete vertical slice
- One dynamic form template per visa type (JSON schema, admin-editable)
- Document upload with MIME/size/scan validation
- Stripe Checkout payment integration
- Officer approve/reject workflow
- Email and in-app notifications
- PDF generation: receipt, decision letter
- Role-based access control (9 roles)
- Full audit trail

---

## Out of Scope (Post-MVP)

- Visual drag-and-drop form builder
- Multi-country complex rule engine
- Meilisearch / Algolia
- Read replicas or Laravel Octane
- Separate document-processing microservice
- Dedicated reporting database
- Complex refund workflows
- SMS / WhatsApp notification channels
- Partner API or mobile app
- Multi-region storage

---

## User Roles

| Role | Description |
|---|---|
| `applicant` | End-user submitting visa applications |
| `agent` | Travel agent submitting on behalf of applicants |
| `case_officer` | Reviews and decides on assigned applications |
| `senior_officer` | Can see all applications; oversees case officers |
| `document_verifier` | Reviews documents only |
| `finance_officer` | Views payments; no application edits |
| `support_staff` | Handles applicant queries |
| `admin` | Manages configuration, users, visa types |
| `super_admin` | Full system access |

---

## First Vertical Slice (Tourist Visa)

Before building all visa types, get this one complete flow working and tested:

1. Tourist visa type, one form template
2. Two required document types: passport scan, passport photo
3. Stripe payment
4. Officer approve/reject
5. Email notification on decision

Do not proceed to Milestone 7 or multi-visa support until this slice is fully tested and stable.
