<?php

declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class StorePhotoSubmission extends Model
{
    protected $table = 'store_photo_submissions';
    protected $guarded = [];
    public $timestamps = false;
}
