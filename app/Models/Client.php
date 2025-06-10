<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;
    protected $table = 'clients';

    protected $fillable = [
        'name',
        'email',
        'password',
        'description',
        'url',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'client_id', 'id')->where('role', 'admin');
    }
}
