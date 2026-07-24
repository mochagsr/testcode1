<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Spk;
use App\Models\SpkStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProductionCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['urut' => 194, 'no' => '194/SPK/VII/2026', 'konsumen' => 'Pustaka Grafika', 'alamat' => 'Malang', 'jenis' => 'Cover LKS Modul Pintar Sem 1', 'order' => '2026-07-03', 'deadline' => '2026-07-07', 'finishing' => 'Stitching + Binding', 'mesin_cover' => 'Lithrone', 'catatan' => 'Reorder (cover plat lama), isi sudah ada',
                'items' => [['Pendidikan Pancasila', '64', '7', 6000, 6000], ['Bahasa Indonesia', '64', '7', 3500, 3500], ['IPS', '128', '7', 3000, 3000]]],
            ['urut' => 222, 'no' => '222/SPK/VII/2026', 'konsumen' => 'Pustaka Grafika', 'alamat' => 'Malang', 'jenis' => 'Cover LKS Modul Pintar', 'order' => '2026-07-15', 'deadline' => '2026-07-17', 'finishing' => 'Stitching', 'mesin_cover' => 'Lithrone', 'catatan' => 'Reorder (cover cerah bersinar)',
                'items' => [['Seni Budaya', '64', '7', 3000, 3000], ['Informatika', '64', '7', 8000, 3500]]],
            ['urut' => 235, 'no' => '235/SPK/VII/2026', 'konsumen' => 'Toko Buku Amanah', 'alamat' => 'Surabaya', 'jenis' => 'Cetak Buku Tulis 38 lembar', 'order' => '2026-07-20', 'deadline' => '2026-07-24', 'finishing' => 'Stitching', 'mesin_cover' => 'Lithrone', 'catatan' => 'Cover full color, isi garis',
                'items' => [['Buku Tulis Sampul A', '38', '—', 15000, 5000], ['Buku Tulis Sampul B', '38', '—', 15000, 5000]]],
            ['urut' => 242, 'no' => '242/SPK/VII/2026', 'konsumen' => 'Wiyanto', 'alamat' => 'Jember', 'jenis' => 'Cetak LKS PAI', 'order' => '2026-07-23', 'deadline' => '2026-07-28', 'finishing' => 'Stitching', 'mesin_cover' => 'Komori 66', 'catatan' => 'Menunggu bahan kertas CD 43gr',
                'items' => [['PAI', '80', '4', 8000, 4000], ['PAI', '80', '5', 8000, 4000]]],
        ];

        foreach ($samples as $s) {
            if (Spk::query()->where('no_spk', $s['no'])->exists()) {
                continue;
            }

            $spk = Spk::query()->create([
                'no_spk' => $s['no'],
                'nomor_urut' => $s['urut'],
                'semester_periode' => 'S1-2627',
                'konsumen' => $s['konsumen'],
                'alamat' => $s['alamat'],
                'jenis_order' => $s['jenis'],
                'tanggal_order' => $s['order'],
                'deadline_kirim' => $s['deadline'],
                'pakai_web' => true,
                'pakai_sheet' => true,
                'jenis_cetak' => Spk::JENIS_PENUH,
                'finishing' => $s['finishing'],
                'packing' => 'Plastik',
                'ukuran_jadi' => '19 × 27,5 cm',
                'mesin_cover' => $s['mesin_cover'],
                'catatan' => $s['catatan'],
                'web_kertas' => 'CD 57,5gr',
                'web_mesin' => 'Web Cut-off 57,8',
                'sheet_kertas' => 'AP 150gr',
                'sheet_mesin' => $s['mesin_cover'],
            ]);

            $sumWeb = 0;
            $sumSheet = 0;
            foreach ($s['items'] as $i => $it) {
                $spk->items()->create([
                    'nama_barang' => $it[0], 'halaman' => $it[1], 'kelas' => $it[2],
                    'cetak_isi' => $it[3], 'cetak_sheet' => $it[4], 'urutan' => $i,
                ]);
                $sumWeb += (int) $it[3];
                $sumSheet += (int) $it[4];
            }

            $start = Carbon::parse($s['order']);
            $end = Carbon::parse($s['deadline']);
            $mid = (clone $start)->addDay();
            if ($mid->gt($end)) {
                $mid = $end->copy();
            }
            $fin = (clone $end)->subDay();
            if ($fin->lt($start)) {
                $fin = $start->copy();
            }

            $stages = [
                [SpkStage::TAHAP_CTCP, 'CTCP', $start, null],
                [SpkStage::TAHAP_WEB, 'Web Cut-off 57,8', $mid, $sumWeb ?: null],
                [SpkStage::TAHAP_SHEET, $s['mesin_cover'], $mid, $sumSheet ?: null],
                [SpkStage::TAHAP_FINISHING, $s['finishing'], $fin, ($sumSheet ?: $sumWeb) ?: null],
            ];
            foreach ($stages as $i => [$tahap, $mesin, $tgl, $qty]) {
                $spk->stages()->create([
                    'tahap' => $tahap, 'mesin' => $mesin, 'tanggal_rencana' => $tgl,
                    'qty_rencana' => $qty, 'urutan' => $i,
                ]);
            }

            foreach (['PPIC' => 'Andri', 'Kepala Produksi' => 'Bsaga', 'Kepala CTCP' => 'Yanis'] as $jabatan => $nama) {
                $spk->penanggungJawabs()->create(['jabatan' => $jabatan, 'nama' => $nama]);
            }
        }
    }
}
