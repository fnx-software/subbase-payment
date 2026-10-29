<?php

declare(strict_types=1);

namespace Nafiswatsiq\SubbasePayment\Tests\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nafiswatsiq\SubbasePayment\Tests\TestCase;

final class PaymentSchemaTest extends TestCase
{
    public function test_existing_payment_schema_upgrades_without_dropping_records(): void
    {
        config([
            'subbase-payment.tables.subscription_payments' => 'subscription_payments',
            'subbase-payment.tables.payment_webhook_logs' => 'payment_webhook_logs',
            'subbase-payment.tables.processed_payment_events' => 'subbase_processed_payment_events',
        ]);

        foreach ([
            'create_subscription_payments_table',
            'create_payment_webhook_logs_table',
            'add_billable_to_subscription_payments_table',
            'create_processed_payment_events_table',
            'upgrade_subscription_payment_decimal_columns',
        ] as $file) {
            $migration = require dirname(__DIR__, 2).'/database/migrations/'.$file.'.php';
            $migration->up();
        }

        $this->assertTrue(Schema::hasColumn('subscription_payments', 'billable_type'));
        $this->assertTrue(Schema::hasColumn('subscription_payments', 'billable_id'));
        $this->assertTrue(Schema::hasTable('subbase_processed_payment_events'));

        $id = DB::table('subscription_payments')->insertGetId([
            'gateway_driver' => 'stripe',
            'gateway_transaction_id' => 'cs_schema_test',
            'payment_status' => 'pending',
            'amount' => '1.234',
            'currency' => 'KWD',
            'billable_type' => 'organization',
            'billable_id' => '7',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('subscription_payments')->find($id);
        $this->assertSame('organization', $record->billable_type);
        $this->assertSame('7', $record->billable_id);
        $this->assertSame(0, bccomp((string) $record->amount, '1.234', 4));

        $this->expectException(QueryException::class);
        DB::table('subbase_processed_payment_events')->insert([
            [
                'gateway_driver' => 'stripe',
                'gateway_account' => 'default',
                'gateway_event_id' => 'evt_one',
                'payment_id' => $id,
                'status' => 'paid',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'gateway_driver' => 'stripe',
                'gateway_account' => 'default',
                'gateway_event_id' => 'evt_one',
                'payment_id' => $id,
                'status' => 'paid',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
