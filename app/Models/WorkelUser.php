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

        $responseClient = Http::withToken(env('API_APP_TOKEN'))
            ->withHeaders(headers: [
                'Accept' => 'application/json',
                'Custom-Header' => 'CustomValue'
            ])
            ->get(env("CLIENT_WORKEL_API") . '/admin/users');
        if ($responseClient->successful()) {
            $all_users = $responseClient->json();
            if (!$all_users) {
                Log::info('No users found in API');
            }
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
                        'created_at' => $user['created_at'],
                        'updated_at' => $user['updated_at'],
                        'api_type' => 'client',
                    ]
                );
            }
        } else {
            Log::error('Failed to fetch users from API', ['response' => $responseClient->body()]);
        }
        $responseApp = Http::withToken(env('API_CLIENT_TOKEN'))
            ->withHeaders(headers: [
                'Accept' => 'application/json',
                'Custom-Header' => 'CustomValue'
            ])
            ->get(env("APP_WORKEL_API") . '/admin/users');
        if ($responseApp->successful()) {
            $all_users = $responseApp->json();
            if (!$all_users) {
                Log::info('No users found in API');
            }
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
                        'created_at' => $user['created_at'],
                        'updated_at' => $user['updated_at'],
                        'api_type' => 'app',
                    ]
                );
            }
        } else {
            Log::error('Failed to fetch users from API', ['response' => $responseApp->body()]);
        }
    }
}
