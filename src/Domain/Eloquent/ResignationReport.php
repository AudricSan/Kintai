<?php

declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class ResignationReport extends Model
{
    protected $table = 'resignation_reports';
    protected $guarded = [];
    public $timestamps = false;
}
