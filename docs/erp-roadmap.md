# PgPOS ERP Roadmap

## Scope Source
- Requirements extracted from `tesai.docx` (book-store POS + ERP flow).

## Business Modules
- Master Data:
- Item Categories
- Items/Products
- Customers
- Customer Levels (agen, sales, umum)
- Transactions:
- Sales Invoice
- Delivery Note (Surat Jalan)
- Order Note (Surat Pesanan)
- Sales Return
- Receivables:
- Per-customer receivable
- Global receivable
- Payment methods (cash/bank transfer)
- Administration:
- User role (`admin`, `user`)
- User preferences (language/theme)
- Finance lock switch

## Phase 1 (Implemented)
- Database + CRUD API:
- Item Categories
- Customer Levels
- Products (3-level pricing)
- Customers (with KTP image upload field)
- User profile extension:
- role
- locale
- theme
- finance lock

## Phase 2 (Implemented)
- Sales invoice transaction flow:
- Create sales invoice with multi-line items
- Optional first payment during invoice creation
- Invoice payment posting after invoice created
- Automatic payment status update (`unpaid`, `partial`, `paid`)
- Stock mutation:
- Product stock auto-decrease on invoice posting
- Stock mutation log (`out`) for each invoice item
- Receivable ledger:
- Debit entry when invoice posted
- Credit entry when payment recorded
- Running customer receivable balance update
- Simple web UI:
- Dashboard summary
- Sales invoice list/create/detail pages
- Receivable page with customer balances and ledger history

## Phase 3 (Implemented)
- Sales return flow:
- Create sales return with multi-line items
- Stock auto-increase on return posting
- Stock mutation log (`in`) per returned item
- Receivable ledger credit entry on return posting
- Customer outstanding receivable auto-adjustment
- Simple return UI:
- Sales return list/create/detail pages

## Phase 4 (Implemented)
- Delivery notes (Surat Jalan):
- Create/list/detail delivery note pages
- Printable delivery note page
- Item rules implemented:
- Product code may be empty
- Recipient may be manual text without creating customer
- Price may be empty
- Unit can be filled manually
- "Dibuat" field stores creator name

## Phase 5 (Implemented)
- Order notes (Surat Pesanan):
- Create/list/detail order note pages
- Printable order note page
- Supports manual customer input or linked existing customer
- Supports manual item input or linked product
- Captures customer name, phone, city, creator name, and item quantities

## Phase 6 (Implemented)
- Reports export module:
- Central reports page for all key datasets
- Excel-compatible CSV export for:
- Products
- Customers
- Sales Invoices
- Receivables
- Sales Returns
- Delivery Notes
- Order Notes
- Printable report pages for each dataset (can be saved as PDF via browser print)
- Native PDF download export using Dompdf

## API Endpoints (Phase 1)
- `GET|POST /api/item-categories`
- `GET|PUT|DELETE /api/item-categories/{id}`
- `GET|POST /api/customer-levels`
- `GET|PUT|DELETE /api/customer-levels/{id}`
- `GET|POST /api/products`
- `GET|PUT|DELETE /api/products/{id}`
- `GET|POST /api/customers`
- `GET|PUT|DELETE /api/customers/{id}`

## Next Build Order (Recommended)
1. Language toggle (ID/EN) and theme preference UI.
2. Role-based access control per menu/action.
3. Authentication (login/logout) and audit log.
4. Semester receivable closing process.
5. Native PDF generation (dompdf) for document outputs (invoice, return, delivery note, order note).
