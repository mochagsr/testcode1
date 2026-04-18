# README Cetak User

Dokumen ini dibuat untuk user operasional harian.

Tujuannya:
- mudah diprint 1 kali lalu ditempel atau dibagikan
- ringkas
- fokus ke pekerjaan harian user

## 1. Login

- Username default: `user`
- Email default: `user@pgpos.local`
- Password default: `@Passworduser123#`

Login bisa memakai:
- username + password
- email + password

Jika password sudah diganti admin, pakai password terbaru dari admin.

## 2. Tugas user

User dipakai untuk:
- input transaksi harian
- melihat data transaksi
- print dokumen
- export jika memang diberi hak akses

User tidak dipakai untuk:
- ubah database manual
- hapus data manual
- paksa buka periode terkunci

## 3. Menu yang paling sering dipakai

- `Faktur Penjualan`
- `Retur Penjualan`
- `Surat Jalan`
- `Surat Pesanan`
- `Tanda Terima Barang`
- `Bayar Piutang`
- `Bayar Hutang Supplier`
- `Piutang Global`
- `Piutang Semester`

## 4. Aturan umum user

- pilih customer, supplier, dan barang dari daftar master
- jangan ketik bebas lalu langsung simpan
- kalau periode terkunci, jangan dipaksa
- kalau salah input yang sudah memengaruhi stok atau piutang, hubungi admin
- gunakan tombol print/export yang tersedia di halaman

## 5. Yang harus diperhatikan saat input

### Faktur Penjualan
- pilih customer yang benar
- pilih metode pembayaran yang benar
- isi item, qty, harga dengan teliti
- kalau transaksi cetak, pilih `Tipe Transaksi = Cetak`
- kalau transaksi biasa, biarkan `Tipe Transaksi = Produk`

### Retur Penjualan
- retur hanya untuk barang yang memang pernah dijual
- cek qty retur tidak berlebihan

### Tanda Terima Barang
- supplier harus dipilih dari master
- cek qty, harga, dan catatan
- upload foto nota kalau perlu

### Bayar Piutang
- gunakan menu `Bayar Piutang`
- jangan catat pembayaran di luar sistem

### Bayar Hutang Supplier
- gunakan menu resmi pembayaran hutang
- upload bukti bayar kalau ada

## 6. Pesan error yang perlu dipahami

- `Customer tidak terdaftar.`
  - pilih customer dari daftar
- `Barang tidak terdaftar.`
  - pilih barang dari daftar
- periode terkunci
  - hubungi admin

## 7. Aturan print

- pastikan dokumen yang diprint sudah benar
- untuk laporan lebar, cek orientasi kertas
- jangan pakai `Fit to page` kalau admin sudah memberi setting khusus

## 8. Kalau ada masalah

Laporkan ke admin kalau:
- salah input qty / harga / customer / supplier
- transaksi tidak bisa disimpan
- print terlihat aneh
- piutang atau hutang terlihat janggal
- menu yang biasanya dipakai tiba-tiba tidak bisa diakses

## 9. Checklist user harian

1. login dengan akun yang benar
2. input transaksi sesuai menu
3. cek kembali customer / supplier / item / qty / harga
4. simpan transaksi
5. print jika diperlukan
6. laporkan ke admin kalau ada koreksi

## 10. Panduan lengkap

Kalau butuh panduan detail:
- `docs/USER_TRANSACTION_GUIDE.md`
- `docs/user-admin-sop.md`
