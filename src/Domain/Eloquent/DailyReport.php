<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class DailyReport extends Model
{
    protected $table = 'daily_reports';
    protected $guarded = [];
    public $timestamps = false;
}
