<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('value', 10, 2);
            $table->string('status')->default('active')->index();
            $table->timestamp('issued_at');
            $table->date('expires_at')->nullable()->index();
            $table->string('purchaser_name')->nullable();
            $table->foreignId('purchaser_customer_profile_id')->nullable()->constrained('customer_profiles')->nullOnDelete();
            $table->string('sale_payment_method')->nullable();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('redeemed_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->decimal('applied_amount', 10, 2)->nullable();
            $table->decimal('forfeited_amount', 10, 2)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('gift_voucher_id')->nullable()->after('customer_membership_id')->constrained()->nullOnDelete();
            $table->decimal('gift_voucher_applied', 10, 2)->default(0)->after('gift_voucher_id');
            $table->decimal('amount_due', 10, 2)->nullable()->after('gift_voucher_applied');
        });

        Schema::table('application_settings', function (Blueprint $table): void {
            $table->json('gift_voucher_denominations')->nullable()->after('promotion_voucher_validity_days');
            $table->unsignedSmallInteger('gift_voucher_default_expiry_months')->nullable()->default(6)->after('gift_voucher_denominations');
            // Printed terms: vouchers cannot be combined with other discounts or promos unless stated.
            $table->boolean('gift_voucher_combinable')->default(false)->after('gift_voucher_default_expiry_months');
        });
    }

    public function down(): void
    {
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->dropColumn(['gift_voucher_denominations', 'gift_voucher_default_expiry_months', 'gift_voucher_combinable']);
        });
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gift_voucher_id');
            $table->dropColumn(['gift_voucher_applied', 'amount_due']);
        });
        Schema::dropIfExists('gift_vouchers');
    }
};
