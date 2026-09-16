<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class ThreadParticipant extends Model
{
    protected $table = 'thread_participants';
    protected $guarded = [];
    public $timestamps = false;
}
