<?php

// app/Models/Talla.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Talla extends Model
{
    use HasFactory;

    protected $table = 'tallas';
    protected $primaryKey = 'idTalla';

    public $timestamps = false;

    protected $fillable = [
        'nombreTalla',
    ];

    // Relación de uno a muchos hacia Stock
    public function stocks()
    {
        return $this->hasMany(Stock::class, 'idTalla', 'idTalla');
    }
}
