<?php

declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class HiringReport extends Model
{
    protected $table = 'hiring_reports';
    protected $guarded = [];
    public $timestamps = false;
}
