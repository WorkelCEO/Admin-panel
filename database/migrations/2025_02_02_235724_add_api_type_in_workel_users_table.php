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
        Schema::table('workel_users', function (Blueprint $table) {
            $table->string('api_type')->default('client')->after('subscription_payment_receipt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workel_users', function (Blueprint $table) {
            //
        });
    }
};
