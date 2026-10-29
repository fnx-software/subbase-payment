<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('subbase-payment.tables.subscription_payments', 'subscription_payments');

        Schema::table($table, function (Blueprint $schema): void {
            $schema->string('billable_type')->nullable()->after('subscription_id');
            $schema->string('billable_id')->nullable()->after('billable_type');
            $schema->index(['billable_type', 'billable_id'], 'subbase_payments_billable_lookup');
        });
    }

    public function down(): void
    {
        $table = config('subbase-payment.tables.subscription_payments', 'subscription_payments');
        Schema::table($table, function (Blueprint $schema): void {
            $schema->dropIndex('subbase_payments_billable_lookup');
            $schema->dropColumn(['billable_type', 'billable_id']);
        });
    }
};
