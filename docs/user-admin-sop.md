# SOP User & Admin

## User
- Buat transaksi sesuai semester aktif.
- Jika transaksi terkunci, hubungi admin (tidak bypass).
- Gunakan pembayaran piutang lewat menu bayar piutang agar kwitansi tercatat.
- Untuk pencarian besar, gunakan minimal 3 huruf.

## Admin
- Kelola permission user detail di menu Users (RBAC per aksi).
- Lakukan koreksi transaksi hanya saat diperlukan, semua perubahan akan tercatat di audit log.
- Gunakan filter `No dokumen` di audit log untuk telusur cepat per invoice/kwitansi/retur.
- Tutup buku semester setelah verifikasi data akhir.

## Koreksi transaksi aman
1. Buka detail transaksi.
2. Edit/batalkan (admin only).
3. Cek mutasi piutang/hutang supplier.
4. Cek audit log sebelum/sesudah.
5. Jalankan integrity check bila koreksi besar.

## Import massal
- Download template dari modul masing-masing:
  - Barang
  - Customer
  - Supplier
- Isi sesuai kolom template.
- Import file.
- Jika ada error, cek daftar error per baris lalu perbaiki dan import ulang.

