<?php

declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class Translation extends Model
{
    protected $table = 'translations';
    protected $guarded = [];
    public $timestamps = true;
}
