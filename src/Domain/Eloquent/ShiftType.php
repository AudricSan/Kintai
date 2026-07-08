<?php

declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class ShiftType extends Model
{
    protected $table = 'shift_types';
    protected $guarded = [];
    public $timestamps = false;
}
