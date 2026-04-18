# README Cetak Admin

Dokumen ini dibuat untuk admin, supervisor, atau owner.

Tujuannya:
- mudah diprint
- ringkas untuk briefing
- fokus ke kontrol dan pengawasan

## 1. Login

### Admin
- Username default: `admin`
- Email default: `admin@pgpos.local`
- Password default: `@Passwordadmin123#`

### User uji akses
- Username default: `user`
- Email default: `user@pgpos.local`
- Password default: `@Passworduser123#`

Login bisa memakai:
- username + password
- email + password

## 2. Tugas admin

Admin dipakai untuk:
- review transaksi
- koreksi transaksi
- cancel transaksi tertentu
- mengelola hak akses user
- lock / unlock periode
- memantau audit log
- memantau backup, restore drill, integrity check, dan arsip data

## 3. Menu yang paling sering dipakai admin

- `Pengguna`
- `Audit Log`
- `Ops Health`
- `Arsip Data`
- `Piutang Global`
- `Piutang Semester`
- `Hutang Supplier`
- `Semester Transaksi`
- semua halaman detail transaksi untuk koreksi

## 4. Yang harus dicek admin setiap hari

- ada transaksi gagal atau tidak
- ada koreksi transaksi atau tidak
- piutang customer terlihat normal atau tidak
- hutang supplier terlihat normal atau tidak
- print penting terlihat benar atau tidak

## 5. Tanggung jawab admin per area

### Faktur Penjualan
- cek customer, item, qty, harga, metode pembayaran
- kalau kredit, cek mutasi piutang
- kalau ada salah input, pakai jalur koreksi resmi

### Retur Penjualan
- cek retur memang sesuai penjualan
- cek qty retur tidak berlebih

### Tanda Terima Barang
- cek supplier dipilih dari master
- cek qty, harga, foto nota, dan dampak ke hutang supplier

### Bayar Piutang
- cek nominal bayar
- cek customer
- cek dampak ke saldo invoice dan mutasi piutang

### Bayar Hutang Supplier
- cek nominal bayar
- cek supplier
- cek bukti bayar
- cek dampak ke mutasi hutang

## 6. Lock periode

### Piutang customer
- tutup semester customer hanya jika memang siap ditutup
- jangan tutup kalau masih ada proses koreksi penting

### Hutang supplier
- tutup bulan supplier kalau periode itu sudah final
- jangan tutup kalau user masih perlu input transaksi supplier pada bulan itu

## 7. Hak akses

Admin harus memastikan:
- user hanya melihat menu yang memang dipakai
- user tidak mendapat akses koreksi sensitif tanpa alasan
- hak akses detail mengikuti tugas kerja

## 8. Backup dan arsip

Untuk production:
- backup DB harus rutin
- restore drill harus dicek
- integrity check harus dipantau
- review arsip harus dijalankan
- untuk `managed DB AWS`, backup dibuat dari app server lalu simpan juga di komputer lokal operator

## 9. Yang tidak boleh dilakukan admin

- edit database manual kalau masih bisa lewat aplikasi
- hapus transaksi langsung dari database
- purge arsip tanpa backup dan restore test
- memberi akses terlalu luas tanpa alasan operasional

## 10. Checklist admin harian

1. login admin
2. cek dashboard admin
3. cek `Ops Health`
4. cek `Audit Log` bila ada kasus
5. cek piutang / hutang jika ada transaksi kredit
6. cek koreksi transaksi yang masuk
7. pastikan backup / review penting tidak tertinggal

## 11. Checklist admin mingguan

1. cek hasil backup
2. cek restore drill terakhir
3. cek integrity check
4. cek kandidat arsip data
5. review hak akses user

## 12. Panduan lengkap

Kalau butuh panduan detail:
- `docs/ADMIN_TRANSACTION_GUIDE.md`
- `docs/user-admin-sop.md`
- `docs/BACKUP_OPS_HEALTH_README.md`
- `docs/RETENTION_POLICY_AAPANEL_PROD_AWS_MANAGED_DB.md`
