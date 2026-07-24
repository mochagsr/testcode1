<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpkItemVersi extends Model
{
    protected $table = 'spk_item_versi';

    /**
     * @var list<string>
     */
    protected $fillable = ['spk_item_id', 'spk_versi_id', 'qty'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    /**
     * @return BelongsTo<SpkItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SpkItem::class, 'spk_item_id');
    }

    /**
     * @return BelongsTo<SpkVersi, $this>
     */
    public function versi(): BelongsTo
    {
        return $this->belongsTo(SpkVersi::class, 'spk_versi_id');
    }
}
