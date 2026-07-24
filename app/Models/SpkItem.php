<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpkItem extends Model
{
    protected $table = 'spk_item';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'spk_id', 'product_id', 'nama_barang', 'halaman', 'kelas',
        'cetak_isi', 'cetak_sheet', 'jumlah_jadi_realisasi', 'stock_posted_qty', 'urutan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cetak_isi' => 'integer',
            'cetak_sheet' => 'integer',
            'jumlah_jadi_realisasi' => 'integer',
            'stock_posted_qty' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Spk, $this>
     */
    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<SpkItemVersi, $this>
     */
    public function versiQtys(): HasMany
    {
        return $this->hasMany(SpkItemVersi::class);
    }
}
