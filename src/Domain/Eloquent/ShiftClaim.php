<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class ShiftClaim extends Model
{
    protected $table = 'shift_claims';
    protected $guarded = [];
    public $timestamps = false;
}
