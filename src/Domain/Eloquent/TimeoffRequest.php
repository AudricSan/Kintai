<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class TimeoffRequest extends Model
{
    protected $table = 'timeoff_requests';
    protected $guarded = [];
    public $timestamps = false;
}
