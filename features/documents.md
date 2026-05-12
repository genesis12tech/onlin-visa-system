# Feature: Document Management (Milestone 3)

## Scope
Secure document upload, versioning, scanning, and officer review.

## Models
- `document_types` — accepted MIME types, size limits, page limits
- `visa_type_document_requirements` — which documents each visa type requires
- `application_documents` — one slot per document type per application
- `document_versions` — one row per upload or replacement

## Actions
- `UploadDocumentVersion` — validates, checksums, stores to private S3, creates version record
- `AcceptDocument` — officer marks document as accepted
- `RejectDocument` — officer rejects with reason; applicant can replace

## Rules
- Private storage only — never public buckets
- Storage paths are ULID-based — original filename stored as metadata only
- SHA-256 checksum computed on every upload
- Server-side MIME type, extension, file size, and page validation (not just Filament component)
- Block preview/download until scan status is `clean`
- Audit log entry on every upload, preview, download, accept, reject

## Acceptance Criteria
- [ ] Required document rules enforced before submission and officer review
- [ ] Applicant can replace a rejected document
- [ ] Every replacement creates a new `document_versions` row
- [ ] `application_documents.current_version_id` points to latest version
- [ ] Documents are never accessible via public URL
- [ ] Unauthorized users cannot view or download documents
- [ ] Every document action writes an audit log entry

## Security Requirements
- `ApplicationDocumentPolicy` required
- Signed, time-limited URLs for previews (never permanent URLs)
- Scan status must be `clean` before download is permitted

## Tests Required
- Feature: upload creates document version with correct checksum
- Feature: rejected document can be replaced
- Policy: applicant cannot view another applicant's documents
- Security: document download returns 403 before scan is clean
