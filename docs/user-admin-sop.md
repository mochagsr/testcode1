# SOP User & Admin

Dokumen ringkas ini menjadi penghubung ke panduan yang lebih detail.

## Panduan yang dipakai

- User operasional versi ringkas siap print:
  - `docs/USER_PRINT_README.md`
- Admin / supervisor versi ringkas siap print:
  - `docs/ADMIN_PRINT_README.md`
- User operasional:
  - `docs/USER_TRANSACTION_GUIDE.md`
- Admin / supervisor:
  - `docs/ADMIN_TRANSACTION_GUIDE.md`
- Import massal:
  - `docs/IMPORT_MASSAL_PLAYBOOK.md`
- Backup / Ops Health:
  - `docs/BACKUP_OPS_HEALTH_README.md`
- Recovery:
  - `docs/RECOVERY_SOP.md`

## Akun default setelah seed
- Admin
  - username: `admin`
  - email: `admin@pgpos.local`
  - password: `@Passwordadmin123#`
- User
  - username: `user`
  - email: `user@pgpos.local`
  - password: `@Passworduser123#`

Login bisa memakai username atau email.

## Ringkasan aturan user

- Buat transaksi sesuai semester yang aktif.
- Jika semester customer terkunci, jangan paksa edit. Hubungi admin.
- Bayar piutang lewat menu `Bayar Piutang`, jangan dicatat manual di luar sistem.
- Untuk print rekap piutang:
  - gunakan `Piutang Global`
  - gunakan `Piutang Semester`
- Untuk hutang supplier, gunakan filter:
  - `supplier`
  - `tahun`
  - `bulan`

## Ringkasan aturan admin

- Kelola hak akses user di menu `Pengguna`.
- Koreksi transaksi sensitif lewat jalur resmi.
- Tutup semester customer hanya setelah data diverifikasi.
- Tutup bulan supplier dari menu `Hutang Supplier`.
- Pantau `Ops Health`, `Audit Log`, dan backup secara rutin.

## Ringkasan cepat peran

### User
- input transaksi harian
- print/export sesuai hak akses
- ajukan koreksi bila ada salah input

### Admin
- review data
- koreksi / cancel transaksi
- lock semester customer
- lock bulan supplier
- pantau audit, backup, dan kesehatan sistem
