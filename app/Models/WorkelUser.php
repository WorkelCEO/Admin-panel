<?php

namespace App\Models;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkelUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'system_role',
        'status',
        'subscription_type',
        'subscription_start_date',
        'subscription_end_date',
        'subscription_status',
        'subscription_payment_status',
        'subscription_payment_method',
        'subscription_payment_date',
        'subscription_payment_amount',
        'subscription_payment_currency',
        'subscription_payment_transaction_id',
        'subscription_payment_receipt',
        'created_at',
        'updated_at',
        'api_type',
        'external_id',
        'source',
        'email_verified_at',
    ];
    
    // Additional attributes that may come from API but aren't in fillable
    protected $casts = [
        'is_super_admin' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_seen' => 'datetime',
        'suspended_at' => 'datetime',
        'suspended_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];



    public function workspaces(): HasMany
    {
        return $this->hasMany(related: Workspace::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(related: Task::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(related: Project::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(related: Comment::class);
    }
}
