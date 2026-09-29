<?php
declare(strict_types=1);

namespace kintai\Domain\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class NotebookEntry extends Model
{
    protected $table = 'notebook_entries';
    protected $guarded = [];
    public $timestamps = false;
}
