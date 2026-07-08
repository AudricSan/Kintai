<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class MessageThread extends Model
{
    protected $table = 'message_threads';
    protected $guarded = [];
    public $timestamps = false;
}
