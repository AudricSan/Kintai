<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class Timeclock extends Model
{
    protected $table = 'timeclocks';
    protected $guarded = [];
    public $timestamps = false;
}
