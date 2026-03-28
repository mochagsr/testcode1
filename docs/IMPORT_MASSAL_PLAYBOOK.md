# Playbook Import Massal

Dokumen ini dipakai saat tim ingin isi data awal atau update master/transaksi dalam jumlah besar.

## 1. Prinsip aman
- Selalu pakai environment `tes` dulu untuk uji file import baru.
- Download template dari modul yang sesuai, jangan bikin kolom sendiri.
- Jangan import langsung ke `prod` tanpa cek hasil di `tes`.
- Setelah import besar, jalankan integrity check.

## 2. Modul yang sudah mendukung import
- Barang
- Customer
- Supplier
- Kategori Barang
- Lokasi Kirim Customer
- Faktur Penjualan

## 3. Urutan kerja yang disarankan
1. Download `Template Import` dari modul.
2. Isi file sesuai header template.
3. Import di environment `tes`.
4. Cek hasil import:
   - jumlah data baru/update
   - daftar error per baris
   - efek ke stok/piutang bila modul transaksi
5. Jalankan:
   - `php artisan app:integrity-check`
6. Kalau aman, baru ulang di `prod`.

## 4. Checklist per jenis import

### Barang
- pastikan kategori sudah ada
- cek kode barang hasil generate
- cek stok awal benar
- cek harga agen / sales / umum benar

### Customer
- cek level customer sudah tersedia
- cek kota dan alamat tidak tertukar
- cek nomor telepon tidak berubah format aneh

### Supplier
- cek nama supplier tidak duplikat
- cek phone dan notes ikut masuk

### Kategori Barang
- fokus isi `nama kategori`
- `kode` boleh kosong, sistem akan generate
- kalau isi manual, pastikan unik

### Lokasi Kirim Customer
- pastikan customer target sudah ada
- cek kota, alamat, dan status aktif

### Faktur Penjualan
- cek customer ada
- cek produk ada
- cek semester valid
- cek metode bayar benar (`tunai` / `kredit`)
- setelah import, cek:
  - stok berkurang
  - piutang bertambah bila kredit
  - nomor dokumen format benar

## 5. Kalau ada error saat import
- baca pesan `Baris X: ...`
- perbaiki data di file sumber
- import ulang file yang sudah dibetulkan
- jangan edit database manual kalau masih bisa diperbaiki lewat import ulang

## 6. Setelah import besar
Jalankan minimal ini:

```bash
php artisan app:integrity-check
php artisan app:db-backup --gzip
```

Lalu cek:
- `Ops Health`
- `Audit Log`
- modul yang terkena import

## 7. Batas aman operasional
- import master: aman dilakukan di jam kerja bila file kecil
- import transaksi: lebih aman di luar jam sibuk
- import besar: lakukan di `tes` dulu, lalu `prod` setelah backup dibuat
