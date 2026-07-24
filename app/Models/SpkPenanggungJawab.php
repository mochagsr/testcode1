<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpkPenanggungJawab extends Model
{
    protected $table = 'spk_penanggung_jawab';

    /**
     * @var list<string>
     */
    protected $fillable = ['spk_id', 'jabatan', 'nama', 'urutan'];

    /**
     * @return BelongsTo<Spk, $this>
     */
    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class);
    }
}
