<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workel_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('role')->default('user');
            $table->string('status')->default('active');
            $table->string('subscription_type')->nullable();
            $table->date('subscription_start_date')->nullable();
            $table->date('subscription_end_date')->nullable();
            $table->string('subscription_status')->nullable();
            $table->string('subscription_payment_status')->nullable();
            $table->string('subscription_payment_method')->nullable();
            $table->date('subscription_payment_date')->nullable();
            $table->double('subscription_payment_amount')->nullable();
            $table->string('subscription_payment_currency')->nullable();
            $table->string('subscription_payment_transaction_id')->nullable();
            $table->text('subscription_payment_receipt')->nullable();
            $table->rememberToken();
            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workel_users');
    }
};
