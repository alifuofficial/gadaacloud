<?php

namespace Workdo\Hrm\Models;


use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class DocumentCategory extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'document_type',
        'status',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean'
        ];
    }




}