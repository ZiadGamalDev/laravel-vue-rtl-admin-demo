<?php

namespace App\Models;

use App\Support\ArabicText;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'city', 'notes'];

    protected $hidden = ['search_text'];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->search_text = ArabicText::customerSearchText($customer->name, $customer->email, $customer->phone, $customer->city);
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
