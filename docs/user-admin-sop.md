# SOP User & Admin

## User
- Buat transaksi sesuai semester aktif.
- Jika transaksi terkunci, hubungi admin (tidak bypass).
- Gunakan pembayaran piutang lewat menu bayar piutang agar kwitansi tercatat.
- Untuk pencarian besar, gunakan minimal 3 huruf.
- Untuk print piutang global/semester, gunakan menu `Piutang Global` dan `Piutang Semester`.
- Untuk hutang supplier, gunakan filter `supplier + tahun + bulan` agar laporan lebih tepat.

## Admin
- Kelola permission user detail di menu Users (RBAC per aksi).
- Lakukan koreksi transaksi hanya saat diperlukan, semua perubahan akan tercatat di audit log.
- Gunakan filter `No dokumen` di audit log untuk telusur cepat per invoice/kwitansi/retur.
- Tutup buku semester setelah verifikasi data akhir.
- Tutup tahun supplier dari menu `Hutang Supplier`, bukan dari semester global.
- Pantau `Ops Health` setelah deploy, restore drill, dan stress test.

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
  - Kategori Barang
  - Lokasi Kirim Customer
- Isi sesuai kolom template.
- Import file.
- Jika ada error, cek daftar error per baris lalu perbaiki dan import ulang.
- Gunakan `docs/IMPORT_MASSAL_PLAYBOOK.md` saat import besar atau import transaksi.
