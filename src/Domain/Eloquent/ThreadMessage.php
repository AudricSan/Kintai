<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class ThreadMessage extends Model
{
    protected $table = 'thread_messages';
    protected $guarded = [];
    public $timestamps = false;
}
