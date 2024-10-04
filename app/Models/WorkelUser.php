<?php

namespace App\Models;

use Illuminate\Support\Facades\Http;
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

    protected static function boot()
    {
        parent::boot();

        $response = Http::withToken(env('API_TOKEN'))
            ->withHeaders(headers: [
                'Accept' => 'application/json',
                'Custom-Header' => 'CustomValue'
            ])
            ->get('https://api.workel.com/api/v1/admin/users');


        $all_users = $response->json();
        dd($all_users);      
        foreach ($all_users as $user) {
            WorkelUser::updateOrCreate(
                attributes: ['id' => $user['id']],
                values: [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'] ?? '',
                    'address' => $user['address'] ?? '',
                    'role' => $user['role'] ?? 'user',
                    'password' => $user['password'] ?? '',
                    'status' => $user['status'] ?? 'active',
                    // 'subscription_type' => $user['subscription_type'],
                    // 'subscription_start_date' => $user['subscription_start_date'],
                    // 'subscription_end_date' => $user['subscription_end_date'],
                    // 'subscription_status' => $user['subscription_status'],
                    // 'subscription_payment_status' => $user['subscription_payment_status'],
                    // 'subscription_payment_method' => $user['subscription_payment_method'],
                    // 'subscription_payment_date' => $user['subscription_payment_date'],
                    // 'subscription_payment_amount' => $user['subscription_payment_amount'],
                    // 'subscription_payment_currency' => $user['subscription_payment_currency'],
                    // 'subscription_payment_transaction_id' => $user['subscription_payment_transaction_id'],
                    // 'subscription_payment_receipt' => $user['subscription_payment_receipt'],
                    'created_at' => $user['created_at'],
                    'updated_at' => $user['updated_at'],
                ]
            );
        }
        // static::creating(function ($model) {
        //     // This will run before the model is created
        //     \Log::info('Model is being created: ', [$model]);
        // });

        // static::created(function ($model) {
        //     // This will run after the model has been created
        //     \Log::info('Model has been created: ', [$model]);
        // });

        // You can add similar logic for updating, deleting, etc.
    }
}
