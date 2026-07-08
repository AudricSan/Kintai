<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class ShiftSwapRequest extends Model
{
    protected $table = 'shift_swap_requests';
    protected $guarded = [];
    public $timestamps = false;
}
