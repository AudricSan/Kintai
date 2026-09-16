<?php

declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class SalaryReport extends Model
{
    protected $table = 'salary_reports';
    protected $guarded = [];
    public $timestamps = false;
}
