<?php

declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class Role extends Model
{
    protected $table = 'roles';
    protected $guarded = [];
    public $timestamps = false;
}
