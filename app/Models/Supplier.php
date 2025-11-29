<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Supplier Model
 *
 * Represents a supplier/vendor from whom products are purchased.
 * Stores supplier contact information and address.
 *
 * @package App\Models
 */
class Supplier extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'contact',
        'email',
        'address',
    ];
}
