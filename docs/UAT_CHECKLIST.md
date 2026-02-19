# UAT Checklist (ERP PgPOS)

## Scope
- Sales invoice, sales return, delivery note, order note.
- Receivable payment and supplier payable payment.
- Approval workflow for transaction correction.
- Semester lock (customer/supplier) and admin override.
- Export (print/PDF/Excel) and mass import.

## Pre-conditions
- Environment: staging with production-like data copy.
- Roles available: `admin`, `user`.
- Active semester and one closed semester exist.
- Queue worker running for heavy export.

## Test Matrix
1. User create sales invoice (`tunai`) and verify:
- Stock reduced.
- Receivable ledger debit+credit net to zero.
- Invoice status `paid`.

2. User create sales invoice (`kredit`) and verify:
- Stock reduced.
- Receivable ledger has open balance.
- Invoice status `unpaid`.

3. User submit correction request from wizard:
- Approval request created in `pending`.
- Audit log recorded (`approval.request.create`).

4. Admin approve correction request (`sales_invoice`):
- Approval status `approved`.
- Auto execution status appears in approval payload.
- Stock mutation and ledger adjust correctly.

5. Admin reject correction request:
- Approval status `rejected`.
- Audit log recorded (`approval.request.reject`).

6. Permission check:
- User cannot access approvals page.
- User can create correction request.
- Non-admin cannot call cancel/admin-update endpoints.

7. Semester lock:
- User blocked on create/update financial docs in locked semester.
- Admin override behavior works according to lock policy.

8. Import sales invoices:
- Template downloadable.
- Valid file imports and creates invoice + item + stock mutation.
- Invalid row reported with row number and reason.

9. Export consistency:
- Print/PDF/Excel totals match source transaction.
- Date format is `dd-mm-yyyy`.

10. Idempotency:
- Double-submit create/payment endpoint does not create duplicate transaction.

## Evidence Required
- Screenshot or screen recording per test.
- Sample invoice numbers and ledger snapshots.
- Audit log record IDs.
- Exported files attached.

## Exit Criteria
- No critical/major issue open.
- Data integrity checks pass (stock and ledger balanced).
- Admin sign-off + user sign-off recorded.
