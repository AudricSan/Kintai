<?php

declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class StorePhotoImage extends Model
{
    protected $table = 'store_photo_images';
    protected $guarded = [];
    public $timestamps = false;
}
