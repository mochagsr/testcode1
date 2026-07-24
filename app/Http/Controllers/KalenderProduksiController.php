<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Spk;
use App\Models\SpkStage;
use App\Services\ProductionStockService;
use App\Support\PrintTextFormatter;
use App\Support\SemesterBookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SanderMuller\FluentValidation\FluentRule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KalenderProduksiController extends Controller
{
    public function __construct(
        private readonly ProductionStockService $stockService,
        private readonly SemesterBookService $semesterBook,
    ) {
    }

    public function index(Request $request): View
    {
        $today = Carbon::today();
        $year = (int) $request->integer('year', (int) $today->year);
        $month = (int) $request->integer('month', (int) $today->month);
        if ($month < 1 || $month > 12) {
            $month = (int) $today->month;
        }

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $spks = $this->loadSpksForRange($monthStart, $monthEnd);

        $kpi = [
            'total' => $spks->count(),
            Spk::STATUS_ANTRE => 0,
            Spk::STATUS_PROSES => 0,
            Spk::STATUS_SELESAI => 0,
            Spk::STATUS_TELAT => 0,
        ];
        foreach ($spks as $spk) {
            $kpi[$spk->computedStatus()] = ($kpi[$spk->computedStatus()] ?? 0) + 1;
        }

        $konsumenOptions = Spk::query()
            ->select('konsumen')
            ->distinct()
            ->orderBy('konsumen')
            ->pluck('konsumen')
            ->all();

        $regionData = [
            'year' => $year,
            'month' => $month,
            'monthLabel' => $this->monthLabel($month).' '.$year,
            'prevMonth' => (clone $monthStart)->subMonth(),
            'nextMonth' => (clone $monthStart)->addMonth(),
            'spks' => $spks,
            'kpi' => $kpi,
            'weeks' => $this->buildMonthGrid($year, $month, $spks),
            'timeline' => $this->buildTimeline($spks),
            'listRows' => $this->buildList($spks),
            'spkData' => $this->buildSpkData($spks),
        ];

        // AJAX month navigation: swap only the calendar region, keep the chrome.
        if ($request->ajax()) {
            return view('produksi.kalender.partials.region', $regionData);
        }

        return view('produksi.kalender.index', $regionData + [
            'konsumenOptions' => $konsumenOptions,
            'openId' => $request->integer('open') ?: null,
            'canManage' => $request->user()?->canAccess('produksi.spk.kelola') ?? false,
            'canRealisasi' => $request->user()?->canAccess('produksi.realisasi') ?? false,
            'canExport' => $request->user()?->canAccess('produksi.export') ?? false,
        ]);
    }

    /**
     * Drawer detail (rendered partial injected client-side).
     */
    public function show(Request $request, Spk $spk): View
    {
        $spk->load(['items.product:id,code,name,unit', 'versis', 'stages', 'penanggungJawabs', 'items.versiQtys']);

        return view('produksi.kalender.partials.drawer', [
            'spk' => $spk,
            'canManage' => $request->user()?->canAccess('produksi.spk.kelola') ?? false,
            'canRealisasi' => $request->user()?->canAccess('produksi.realisasi') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSpk($request);

        $spk = DB::transaction(function () use ($data, $request): Spk {
            [$noSpk, $urut, $semester] = $this->generateNoSpk((string) $data['tanggal_order']);
            $spk = Spk::query()->create($this->spkAttributes($data) + [
                'no_spk' => $noSpk,
                'nomor_urut' => $urut,
                'semester_periode' => $semester,
                'created_by_user_id' => $request->user()?->id,
            ]);
            $this->syncChildren($spk, $data);
            $this->generateStages($spk);

            return $spk;
        });

        return redirect()
            ->route('produksi.kalender.index')
            ->with('success', 'SPK '.$spk->no_spk.' berhasil dibuat.');
    }

    public function update(Request $request, Spk $spk): RedirectResponse
    {
        $data = $this->validateSpk($request);

        DB::transaction(function () use ($spk, $data): void {
            $spk->update($this->spkAttributes($data));
            // Replace items/versi/PJ but keep operator realisasi on stages.
            $spk->items()->delete();
            $spk->versis()->delete();
            $spk->penanggungJawabs()->delete();
            $this->syncChildren($spk, $data);
            $this->refreshStagePlan($spk);
        });

        // Item quantities may have changed the finished target: reconcile stock.
        $this->stockService->syncFinishedGoods($spk->fresh(['items', 'stages']), $request->user()?->id);

        return redirect()
            ->route('produksi.kalender.index')
            ->with('success', 'SPK '.$spk->no_spk.' berhasil diperbarui.');
    }

    /**
     * Operator input realisasi: per-stage qty/date/selesai + per-item finished qty.
     */
    public function storeRealisasi(Request $request, Spk $spk): RedirectResponse
    {
        $data = $request->validate([
            'stages' => FluentRule::array()->required(),
            'stages.*.id' => FluentRule::integer()->required(),
            'stages.*.qty_realisasi' => FluentRule::integer()->nullable()->min(0),
            'stages.*.tanggal_realisasi' => FluentRule::date()->nullable(),
            'stages.*.selesai' => FluentRule::boolean()->nullable(),
            'items' => FluentRule::array()->nullable(),
            'items.*.id' => FluentRule::integer()->nullable(),
            'items.*.jumlah_jadi_realisasi' => FluentRule::integer()->nullable()->min(0),
        ]);

        DB::transaction(function () use ($spk, $data): void {
            $stageMap = $spk->stages()->get()->keyBy('id');
            foreach (($data['stages'] ?? []) as $row) {
                $stage = $stageMap->get((int) ($row['id'] ?? 0));
                if ($stage === null) {
                    continue;
                }
                $stage->update([
                    'qty_realisasi' => $row['qty_realisasi'] ?? null,
                    'tanggal_realisasi' => $row['tanggal_realisasi'] ?? null,
                    'selesai' => (bool) ($row['selesai'] ?? false),
                ]);
            }

            $itemMap = $spk->items()->get()->keyBy('id');
            foreach (($data['items'] ?? []) as $row) {
                $item = $itemMap->get((int) ($row['id'] ?? 0));
                if ($item === null) {
                    continue;
                }
                $item->update(['jumlah_jadi_realisasi' => $row['jumlah_jadi_realisasi'] ?? null]);
            }
        });

        // Post finished goods into general-product stock (idempotent).
        $this->stockService->syncFinishedGoods($spk->fresh(['items', 'stages']), $request->user()?->id);

        return redirect()
            ->route('produksi.kalender.index', ['open' => $spk->id])
            ->with('success', 'Realisasi SPK '.$spk->no_spk.' tersimpan.');
    }

    public function cetak(Spk $spk): View
    {
        $spk->load(['items.product:id,code,name', 'versis', 'stages', 'penanggungJawabs']);

        return view('produksi.kalender.cetak', ['spk' => $spk]);
    }

    public function export(Request $request): StreamedResponse
    {
        $konsumen = trim((string) $request->string('konsumen', ''));
        $status = trim((string) $request->string('status', ''));

        $spks = $this->loadSpksForRange(null, null)
            ->when($konsumen !== '', fn (Collection $c): Collection => $c->filter(fn (Spk $s): bool => $s->konsumen === $konsumen))
            ->when(in_array($status, [Spk::STATUS_ANTRE, Spk::STATUS_PROSES, Spk::STATUS_SELESAI, Spk::STATUS_TELAT], true),
                fn (Collection $c): Collection => $c->filter(fn (Spk $s): bool => $s->computedStatus() === $status))
            ->values();

        $filename = 'kalender-produksi-'.Carbon::now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spks): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Kalender Produksi');
            $rows = [[
                'No. SPK', 'Konsumen', 'Alamat', 'Jenis', 'Tgl Order', 'Deadline',
                'Status', 'Progress', 'Cetak Web', 'Cetak Sheet', 'Catatan',
            ]];
            foreach ($spks as $spk) {
                $rows[] = [
                    $spk->no_spk,
                    $spk->konsumen,
                    $spk->alamat,
                    $spk->jenis_order,
                    $spk->tanggal_order?->format('d-m-Y'),
                    $spk->deadline_kirim?->format('d-m-Y'),
                    ucfirst($spk->computedStatus()),
                    round($spk->progress() * 100).'%',
                    $spk->totalCetakWeb(),
                    $spk->totalCetakSheet(),
                    PrintTextFormatter::wrapWords((string) ($spk->catatan ?? ''), 6),
                ];
            }
            $sheet->fromArray($rows, null, 'A1');
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Product lookup for the SPK item picker (general stock only).
     */
    public function lookup(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search', ''));
        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $products = Product::query()
            ->generalStock()
            ->where('is_active', true)
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'code', 'name', 'unit', 'stock']);

        return response()->json([
            'data' => $products->map(fn (Product $p): array => [
                'id' => (int) $p->id,
                'code' => (string) $p->code,
                'name' => (string) $p->name,
                'unit' => (string) $p->unit,
                'stock' => (int) $p->stock,
            ])->all(),
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @return Collection<int, Spk>
     */
    private function loadSpksForRange(?Carbon $start, ?Carbon $end): Collection
    {
        return Spk::query()
            ->with(['items.product:id,code,name', 'items.versiQtys', 'stages', 'versis', 'penanggungJawabs'])
            ->when($start !== null && $end !== null, function ($query) use ($start, $end): void {
                // SPK visible if its [order, deadline] window overlaps the month.
                $query->where('tanggal_order', '<=', $end->toDateString())
                    ->where('deadline_kirim', '>=', $start->toDateString());
            })
            ->orderBy('tanggal_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function spkAttributes(array $data): array
    {
        return [
            'konsumen' => trim((string) $data['konsumen']),
            'alamat' => $data['alamat'] ?? null,
            'jenis_order' => $data['jenis_order'] ?? null,
            'tanggal_order' => $data['tanggal_order'],
            'deadline_kirim' => $data['deadline_kirim'],
            'pakai_web' => (bool) ($data['pakai_web'] ?? false),
            'pakai_sheet' => (bool) ($data['pakai_sheet'] ?? false),
            'jenis_cetak' => ($data['jenis_cetak'] ?? Spk::JENIS_PENUH) === Spk::JENIS_SEBAGIAN ? Spk::JENIS_SEBAGIAN : Spk::JENIS_PENUH,
            'revisi_bagian' => $data['revisi_bagian'] ?? null,
            'finishing' => $data['finishing'] ?? null,
            'packing' => $data['packing'] ?? null,
            'ukuran_jadi' => $data['ukuran_jadi'] ?? null,
            'mesin_cover' => $data['mesin_cover'] ?? null,
            'catatan' => $data['catatan'] ?? null,
            'web_kertas' => $data['web_kertas'] ?? null,
            'web_warna' => $data['web_warna'] ?? null,
            'web_mesin' => $data['web_mesin'] ?? null,
            'web_waste' => $data['web_waste'] ?? null,
            'sheet_kertas' => $data['sheet_kertas'] ?? null,
            'sheet_warna' => $data['sheet_warna'] ?? null,
            'sheet_mesin' => $data['sheet_mesin'] ?? null,
            'sheet_waste' => $data['sheet_waste'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncChildren(Spk $spk, array $data): void
    {
        $versiIdByIndex = [];
        if (($data['jenis_cetak'] ?? '') === Spk::JENIS_SEBAGIAN) {
            foreach (($data['versi'] ?? []) as $i => $versi) {
                $nama = trim((string) ($versi['nama'] ?? ''));
                if ($nama === '') {
                    continue;
                }
                $created = $spk->versis()->create(['nama' => $nama, 'urutan' => (int) $i]);
                $versiIdByIndex[(int) $i] = $created->id;
            }
        }

        foreach (($data['items'] ?? []) as $i => $item) {
            $nama = trim((string) ($item['nama_barang'] ?? ''));
            if ($nama === '') {
                continue;
            }
            $created = $spk->items()->create([
                'product_id' => (int) ($item['product_id'] ?? 0) > 0 ? (int) $item['product_id'] : null,
                'nama_barang' => $nama,
                'halaman' => $item['halaman'] ?? null,
                'kelas' => $item['kelas'] ?? null,
                'cetak_isi' => $this->intOrNull($item['cetak_isi'] ?? null),
                'cetak_sheet' => $this->intOrNull($item['cetak_sheet'] ?? null),
                'urutan' => (int) $i,
            ]);

            foreach (($item['versi'] ?? []) as $vi => $qty) {
                $versiId = $versiIdByIndex[(int) $vi] ?? null;
                if ($versiId === null) {
                    continue;
                }
                $created->versiQtys()->create([
                    'spk_versi_id' => $versiId,
                    'qty' => $this->intOrNull($qty),
                ]);
            }
        }

        foreach (($data['pj'] ?? []) as $i => $pj) {
            $jabatan = trim((string) ($pj['jabatan'] ?? ''));
            if ($jabatan === '') {
                continue;
            }
            $spk->penanggungJawabs()->create([
                'jabatan' => $jabatan,
                'nama' => $pj['nama'] ?? null,
                'urutan' => (int) $i,
            ]);
        }
    }

    /**
     * Create the 4 fixed stages with computed plan dates & quantities.
     */
    private function generateStages(Spk $spk): void
    {
        $spk->loadMissing('items');
        $start = $spk->tanggal_order;
        $end = $spk->deadline_kirim;
        $mid = $this->clampDate((clone $start)->addDay(), $start, $end);
        $fin = $this->clampDate((clone $end)->subDay(), $start, $end);

        $sumWeb = $spk->totalCetakWeb();
        $sumSheet = $spk->totalCetakSheet();

        $stages = [
            ['tahap' => SpkStage::TAHAP_CTCP, 'pic' => null, 'mesin' => 'CTCP', 'tanggal_rencana' => $start, 'qty_rencana' => null],
            ['tahap' => SpkStage::TAHAP_WEB, 'pic' => null, 'mesin' => $spk->web_mesin ?: 'Web', 'tanggal_rencana' => $mid, 'qty_rencana' => $sumWeb ?: null],
            ['tahap' => SpkStage::TAHAP_SHEET, 'pic' => null, 'mesin' => $spk->mesin_cover ?: ($spk->sheet_mesin ?: 'Sheet'), 'tanggal_rencana' => $mid, 'qty_rencana' => $sumSheet ?: null],
            ['tahap' => SpkStage::TAHAP_FINISHING, 'pic' => null, 'mesin' => $spk->finishing ?: 'Finishing', 'tanggal_rencana' => $fin, 'qty_rencana' => ($sumSheet ?: $sumWeb) ?: null],
        ];

        foreach ($stages as $i => $stage) {
            $spk->stages()->create($stage + ['urutan' => $i]);
        }
    }

    /**
     * Recompute plan dates/qty on edit without clobbering operator realisasi.
     */
    private function refreshStagePlan(Spk $spk): void
    {
        $spk->loadMissing(['items', 'stages']);
        if ($spk->stages->isEmpty()) {
            $this->generateStages($spk);

            return;
        }

        $start = $spk->tanggal_order;
        $end = $spk->deadline_kirim;
        $mid = $this->clampDate((clone $start)->addDay(), $start, $end);
        $fin = $this->clampDate((clone $end)->subDay(), $start, $end);
        $sumWeb = $spk->totalCetakWeb();
        $sumSheet = $spk->totalCetakSheet();

        $planByTahap = [
            SpkStage::TAHAP_CTCP => ['tanggal_rencana' => $start, 'qty_rencana' => null],
            SpkStage::TAHAP_WEB => ['tanggal_rencana' => $mid, 'qty_rencana' => $sumWeb ?: null],
            SpkStage::TAHAP_SHEET => ['tanggal_rencana' => $mid, 'qty_rencana' => $sumSheet ?: null],
            SpkStage::TAHAP_FINISHING => ['tanggal_rencana' => $fin, 'qty_rencana' => ($sumSheet ?: $sumWeb) ?: null],
        ];

        foreach ($spk->stages as $stage) {
            $plan = $planByTahap[$stage->tahap] ?? null;
            if ($plan !== null) {
                $stage->update($plan);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSpk(Request $request): array
    {
        return $request->validate([
            'konsumen' => FluentRule::string()->required()->max(150),
            'alamat' => FluentRule::string()->nullable()->max(200),
            'jenis_order' => FluentRule::string()->nullable()->max(200),
            'tanggal_order' => FluentRule::date()->required(),
            'deadline_kirim' => FluentRule::date()->required()->rule('after_or_equal:tanggal_order'),
            'pakai_web' => FluentRule::boolean()->nullable(),
            'pakai_sheet' => FluentRule::boolean()->nullable(),
            'jenis_cetak' => FluentRule::string()->nullable()->in([Spk::JENIS_PENUH, Spk::JENIS_SEBAGIAN]),
            'revisi_bagian' => FluentRule::string()->nullable()->max(200),
            'finishing' => FluentRule::string()->nullable()->max(120),
            'packing' => FluentRule::string()->nullable()->max(120),
            'ukuran_jadi' => FluentRule::string()->nullable()->max(120),
            'mesin_cover' => FluentRule::string()->nullable()->max(120),
            'catatan' => FluentRule::string()->nullable(),
            'web_kertas' => FluentRule::string()->nullable()->max(120),
            'web_warna' => FluentRule::string()->nullable()->max(120),
            'web_mesin' => FluentRule::string()->nullable()->max(120),
            'web_waste' => FluentRule::string()->nullable()->max(120),
            'sheet_kertas' => FluentRule::string()->nullable()->max(120),
            'sheet_warna' => FluentRule::string()->nullable()->max(120),
            'sheet_mesin' => FluentRule::string()->nullable()->max(120),
            'sheet_waste' => FluentRule::string()->nullable()->max(120),
            'items' => FluentRule::array()->required()->min(1),
            'items.*.product_id' => FluentRule::integer()->nullable()->exists('products', 'id'),
            'items.*.nama_barang' => FluentRule::string()->required()->max(200),
            'items.*.halaman' => FluentRule::string()->nullable()->max(40),
            'items.*.kelas' => FluentRule::string()->nullable()->max(40),
            'items.*.cetak_isi' => FluentRule::integer()->nullable()->min(0),
            'items.*.cetak_sheet' => FluentRule::integer()->nullable()->min(0),
            'items.*.versi' => FluentRule::array()->nullable(),
            'versi' => FluentRule::array()->nullable(),
            'versi.*.nama' => FluentRule::string()->nullable()->max(120),
            'pj' => FluentRule::array()->nullable(),
            'pj.*.jabatan' => FluentRule::string()->nullable()->max(120),
            'pj.*.nama' => FluentRule::string()->nullable()->max(120),
        ]);
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '—') {
            return null;
        }
        $clean = preg_replace('/[^0-9]/', '', (string) $value) ?? '';

        return $clean === '' ? null : (int) $clean;
    }

    /**
     * Auto nomor SPK: "{urut}/SPK/{bulan romawi}/{tahun}".
     * Urutan mulai dari 1 dan direset tiap semester (S1 Mei-Okt / S2 Nov-Apr).
     *
     * @return array{0: string, 1: int, 2: string}  [no_spk, nomor_urut, semester_periode]
     */
    private function generateNoSpk(string $tanggalOrder): array
    {
        $date = Carbon::parse($tanggalOrder);
        $semester = $this->semesterBook->semesterFromDate($date->toDateString())
            ?? ('S1-'.$date->format('y').$date->copy()->addYear()->format('y'));

        $lastUrut = (int) (Spk::query()
            ->where('semester_periode', $semester)
            ->lockForUpdate()
            ->max('nomor_urut') ?? 0);
        $urut = $lastUrut + 1;

        $noSpk = $urut.'/SPK/'.$this->romanMonth((int) $date->format('n')).'/'.$date->format('Y');

        return [$noSpk, $urut, $semester];
    }

    private function romanMonth(int $month): string
    {
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];

        return $roman[$month] ?? (string) $month;
    }

    private function clampDate(Carbon $date, Carbon $min, Carbon $max): Carbon
    {
        if ($date->lt($min)) {
            return $min->copy();
        }
        if ($date->gt($max)) {
            return $max->copy();
        }

        return $date->copy();
    }

    private function monthLabel(int $month): string
    {
        $names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $names[$month] ?? '';
    }

    /**
     * Build the month grid: weeks of 7 day cells + overlapping SPK bars per week.
     *
     * @param  Collection<int, Spk>  $spks
     * @return list<array<string, mixed>>
     */
    private function buildMonthGrid(int $year, int $month, Collection $spks): array
    {
        $first = Carbon::create($year, $month, 1)->startOfDay();
        $startW = ((int) $first->dayOfWeek + 6) % 7; // Monday = 0
        $gridStart = (clone $first)->subDays($startW);
        $daysInMonth = (int) $first->daysInMonth;
        $totalCells = (int) (ceil(($startW + $daysInMonth) / 7) * 7);
        $today = Carbon::today();

        $weeks = [];
        for ($w = 0; $w < $totalCells / 7; $w++) {
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $dt = (clone $gridStart)->addDays($w * 7 + $i);
                $days[] = [
                    'day' => (int) $dt->day,
                    'inMonth' => (int) $dt->month === $month,
                    'isToday' => $dt->isSameDay($today),
                ];
            }
            $weekStart = (clone $gridStart)->addDays($w * 7);
            $weekEnd = (clone $weekStart)->addDays(6);

            $segs = [];
            foreach ($spks as $spk) {
                $s = $spk->tanggal_order;
                $e = $spk->deadline_kirim;
                if ($s === null || $e === null || $e->lt($weekStart) || $s->gt($weekEnd)) {
                    continue;
                }
                $os = $s->lt($weekStart) ? $weekStart : $s;
                $oe = $e->gt($weekEnd) ? $weekEnd : $e;
                $si = (int) $weekStart->diffInDays($os, false);
                $ei = (int) $weekStart->diffInDays($oe, false);
                $segs[] = ['spk' => $spk, 'si' => $si, 'span' => max(1, $ei - $si + 1)];
            }

            // Lane assignment (greedy).
            $laneEnds = [];
            foreach ($segs as &$seg) {
                $placed = false;
                foreach ($laneEnds as $lane => $endCol) {
                    if ($seg['si'] > $endCol) {
                        $laneEnds[$lane] = $seg['si'] + $seg['span'] - 1;
                        $seg['lane'] = $lane;
                        $placed = true;
                        break;
                    }
                }
                if (! $placed) {
                    $seg['lane'] = count($laneEnds);
                    $laneEnds[] = $seg['si'] + $seg['span'] - 1;
                }
            }
            unset($seg);

            $weeks[] = [
                'days' => $days,
                'bars' => $segs,
                'lanes' => max(3, count($laneEnds)),
            ];
        }

        return $weeks;
    }

    /**
     * Weekly machine timeline for the current week.
     *
     * @param  Collection<int, Spk>  $spks
     * @return array<string, mixed>
     */
    private function buildTimeline(Collection $spks): array
    {
        $today = Carbon::today();
        $monday = (clone $today)->subDays(((int) $today->dayOfWeek + 6) % 7);
        $dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

        $cols = [];
        for ($i = 0; $i < 7; $i++) {
            $dt = (clone $monday)->addDays($i);
            $cols[] = [
                'label' => $dayNames[$i].' '.$dt->day,
                'iso' => $dt->toDateString(),
                'isToday' => $dt->isSameDay($today),
            ];
        }

        $machines = [
            ['key' => SpkStage::TAHAP_CTCP, 'name' => 'CTCP'],
            ['key' => SpkStage::TAHAP_WEB, 'name' => 'Cetak Isi (Web)'],
            ['key' => SpkStage::TAHAP_SHEET, 'name' => 'Cetak Cover (Sheet)'],
            ['key' => SpkStage::TAHAP_FINISHING, 'name' => 'Finishing'],
        ];

        $rows = [];
        foreach ($machines as $m) {
            $cells = [];
            foreach ($cols as $c) {
                $chips = [];
                foreach ($spks as $spk) {
                    foreach ($spk->stages as $stage) {
                        if ($stage->tahap === $m['key']
                            && $stage->tanggal_rencana !== null
                            && $stage->tanggal_rencana->toDateString() === $c['iso']) {
                            $chips[] = ['spk' => $spk, 'stage' => $stage];
                        }
                    }
                }
                $cells[] = $chips;
            }
            $rows[] = ['name' => $m['name'], 'cells' => $cells];
        }

        return ['cols' => $cols, 'rows' => $rows];
    }

    /**
     * @param  Collection<int, Spk>  $spks
     * @return Collection<int, Spk>
     */
    private function buildList(Collection $spks): Collection
    {
        return $spks->sortBy(fn (Spk $s): string => (string) $s->deadline_kirim?->toDateString())->values();
    }

    /**
     * Lightweight per-SPK payload for the edit-modal prefill (JSON in the page).
     *
     * @param  Collection<int, Spk>  $spks
     * @return array<int, array<string, mixed>>
     */
    private function buildSpkData(Collection $spks): array
    {
        $out = [];
        foreach ($spks as $s) {
            $out[(int) $s->id] = [
                'konsumen' => $s->konsumen, 'alamat' => $s->alamat, 'jenis_order' => $s->jenis_order,
                'tanggal_order' => $s->tanggal_order?->format('Y-m-d'), 'deadline_kirim' => $s->deadline_kirim?->format('Y-m-d'),
                'pakai_web' => (bool) $s->pakai_web, 'pakai_sheet' => (bool) $s->pakai_sheet,
                'jenis_cetak' => $s->jenis_cetak, 'revisi_bagian' => $s->revisi_bagian,
                'mesin_cover' => $s->mesin_cover, 'finishing' => $s->finishing, 'packing' => $s->packing, 'ukuran_jadi' => $s->ukuran_jadi, 'catatan' => $s->catatan,
                'web_kertas' => $s->web_kertas, 'web_warna' => $s->web_warna, 'web_mesin' => $s->web_mesin, 'web_waste' => $s->web_waste,
                'sheet_kertas' => $s->sheet_kertas, 'sheet_warna' => $s->sheet_warna, 'sheet_mesin' => $s->sheet_mesin, 'sheet_waste' => $s->sheet_waste,
                'items' => $s->items->map(fn ($it): array => [
                    'product_id' => $it->product_id,
                    'product_label' => $it->product ? ($it->product->code.' — '.$it->product->name) : '',
                    'nama_barang' => $it->nama_barang, 'halaman' => $it->halaman, 'kelas' => $it->kelas,
                    'cetak_isi' => $it->cetak_isi, 'cetak_sheet' => $it->cetak_sheet,
                ])->values()->all(),
                'versi' => $s->versis->map(fn ($v): array => ['nama' => $v->nama])->values()->all(),
                'pj' => $s->penanggungJawabs->map(fn ($p): array => ['jabatan' => $p->jabatan, 'nama' => $p->nama])->values()->all(),
            ];
        }

        return $out;
    }
}
