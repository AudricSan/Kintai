<?php

declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $table = 'users';
    protected $guarded = []; // Allow mass assignment for now to simplify migration
    public $timestamps = false;
}
