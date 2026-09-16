<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class Feedback extends Model
{
    protected $table = 'employee_feedbacks';
    protected $guarded = [];
    public $timestamps = false;
}
