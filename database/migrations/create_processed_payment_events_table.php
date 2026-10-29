<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('subbase-payment.tables.processed_payment_events', 'subbase_processed_payment_events');

        Schema::create($table, function (Blueprint $schema): void {
            $schema->id();
            $schema->string('gateway_driver', 64);
            $schema->string('gateway_event_id', 191);
            $schema->string('gateway_account', 100)->default('default');
            $schema->unsignedBigInteger('payment_id');
            $schema->string('status', 32);
            $schema->timestamps();

            $schema->unique(['gateway_driver', 'gateway_account', 'gateway_event_id'], 'sbpayment_event_unique');
            $schema->index(['payment_id', 'created_at'], 'sbpayment_payment_events');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subbase-payment.tables.processed_payment_events', 'subbase_processed_payment_events'));
    }
};
