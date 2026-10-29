<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('subbase-payment.tables.subscription_payments', 'subscription_payments'), function (Blueprint $table): void {
            $table->decimal('amount', 20, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Narrowing currency precision would lose existing financial history.
        // A reviewed rollback migration and backup are required.
    }
};
