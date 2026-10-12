<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrinterProfile extends Model
{
    protected $fillable = [
        'name',
        'connection_type', // browser, network, windows_raw
        'host',
        'port',
        'columns',
        'paper_width',
        'has_cutter',
        'is_default',
    ];

    protected $casts = [
        'has_cutter' => 'boolean',
        'is_default' => 'boolean',
        'port' => 'integer',
        'columns' => 'integer',
        'paper_width' => 'integer',
    ];

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
