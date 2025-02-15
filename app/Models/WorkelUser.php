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
        'external_id',
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

        // Fetch and sync users from the App API
        $responseApp = Http::withToken(env('API_APP_TOKEN'))
            ->withHeaders([
                'Accept'        => 'application/json',
                'Custom-Header' => 'CustomValue'
            ])
            ->get(env("APP_WORKEL_API") . '/admin/users');

        if ($responseApp->successful()) {
            $appUsers = $responseApp->json();
            if (empty($appUsers)) {
                Log::info('No users found in App API');
            }
            foreach ($appUsers as $user) {
                WorkelUser::updateOrCreate(
                    [
                        'external_id' => $user['id'],
                        'api_type'    => 'App'
                    ],
                    [
                        'name'       => $user['name'],
                        'email'      => $user['email'],
                        'phone'      => $user['phone']   ?? '',
                        'address'    => $user['address'] ?? '',
                        'role'       => $user['role']    ?? 'user',
                        'password'   => $user['password'] ?? '',
                        'status'     => $user['status']  ?? 'active',
                        'created_at' => $user['created_at'],
                        'updated_at' => $user['updated_at'],
                    ]
                );
            }
        } else {
            Log::error('Failed to fetch users from App API', ['response' => $responseApp->body()]);
        }

        // Fetch and sync users from the Client API
        $responseClient = Http::withToken(env('API_CLIENT_TOKEN'))
            ->withHeaders([
                'Accept'        => 'application/json',
                'Custom-Header' => 'CustomValue'
            ])
            ->get(env("CLIENT_WORKEL_API") . '/admin/users');

        if ($responseClient->successful()) {
            $clientUsers = $responseClient->json();
            if (empty($clientUsers)) {
                Log::info('No users found in Client API');
            }
            foreach ($clientUsers as $user) {
                WorkelUser::updateOrCreate(
                    [
                        'external_id' => $user['id'],
                        'api_type'    => 'Client'
                    ],
                    [
                        'name'       => $user['name'],
                        'email'      => $user['email'],
                        'phone'      => $user['phone']   ?? '',
                        'address'    => $user['address'] ?? '',
                        'role'       => $user['role']    ?? 'user',
                        'password'   => $user['password'] ?? '',
                        'status'     => $user['status']  ?? 'active',
                        'created_at' => $user['created_at'],
                        'updated_at' => $user['updated_at'],
                    ]
                );
            }
        } else {
            Log::error('Failed to fetch users from Client API', ['response' => $responseClient->body()]);
        }
    }
}
