<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedSmallInteger('rank')->default(0)->index();
            $table->unsignedTinyInteger('stars')->default(0);
            $table->text('description')->nullable();
            $table->decimal('service_discount_percent', 5, 2)->default(0);
            $table->decimal('addon_discount_percent', 5, 2)->default(0);
            $table->json('addon_discount_codes')->nullable();
            $table->decimal('vip_room_price', 10, 2)->nullable();
            $table->string('birthday_treat_label')->nullable();
            $table->string('birthday_treat_addon_code')->nullable();
            $table->foreignId('birthday_treat_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->decimal('referral_reward_amount', 10, 2)->default(0);
            $table->unsignedSmallInteger('validity_months')->default(12);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('customer_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_tier_id')->constrained()->restrictOnDelete();
            $table->string('card_number')->unique();
            $table->date('issued_at');
            $table->date('valid_until')->index();
            $table->string('status')->default('active')->index();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('referred_by_customer_profile_id')->nullable()->constrained('customer_profiles')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('membership_referral_credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_membership_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->foreignId('used_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('membership_perk_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('perk');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamp('used_at');
            $table->timestamps();

            $table->index(['customer_membership_id', 'perk']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->decimal('gross_amount', 10, 2)->nullable()->after('amount');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('gross_amount');
            $table->foreignId('customer_membership_id')->nullable()->after('discount_amount')->constrained()->nullOnDelete();
        });

        Schema::create('transaction_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('label');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        Schema::table('rfm_segments', function (Blueprint $table): void {
            $table->foreignId('recommended_membership_tier_id')->nullable()->after('addon_code')->constrained('membership_tiers')->nullOnDelete();
        });

        $now = now();
        // The spa's printed Gold and Platinum cards; admins can edit every benefit afterwards.
        $tiers = [
            [
                'code' => 'gold', 'name' => 'Gold', 'rank' => 10, 'stars' => 3,
                'description' => '10% off all services, a free back massage birthday treat, and a referral reward.',
                'service_discount_percent' => 10, 'addon_discount_percent' => 0, 'addon_discount_codes' => json_encode([]),
                'vip_room_price' => null, 'birthday_treat_label' => 'Free Back Massage', 'birthday_treat_addon_code' => 'back-massage-30',
                'birthday_treat_service_id' => null, 'referral_reward_amount' => 100, 'validity_months' => 12,
            ],
            [
                'code' => 'platinum', 'name' => 'Platinum', 'rank' => 20, 'stars' => 5,
                'description' => '15% off all services, 20% off Ventosa, Hot Compress and Hot Stone, a full body massage birthday treat, a referral reward, and the VIP Room for PHP 100.',
                'service_discount_percent' => 15, 'addon_discount_percent' => 20, 'addon_discount_codes' => json_encode(['ventosa', 'hot-compress', 'hot-stone']),
                'vip_room_price' => 100, 'birthday_treat_label' => 'Full Body Massage', 'birthday_treat_addon_code' => null,
                'birthday_treat_service_id' => DB::table('services')->where('slug', 'gaia-touch')->value('id'), 'referral_reward_amount' => 100, 'validity_months' => 12,
            ],
        ];

        foreach ($tiers as $tier) {
            if (! DB::table('membership_tiers')->where('code', $tier['code'])->exists()) {
                DB::table('membership_tiers')->insert([...$tier, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // RFM recommends Platinum for high-value customers and Gold for loyal ones.
        foreach (['high_value' => 'platinum', 'loyal' => 'gold'] as $presetKey => $tierCode) {
            DB::table('rfm_segments')->where('preset_key', $presetKey)->whereNull('recommended_membership_tier_id')
                ->update(['recommended_membership_tier_id' => DB::table('membership_tiers')->where('code', $tierCode)->value('id')]);
        }
    }

    public function down(): void
    {
        Schema::table('rfm_segments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recommended_membership_tier_id');
        });
        Schema::dropIfExists('transaction_adjustments');
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_membership_id');
            $table->dropColumn(['gross_amount', 'discount_amount']);
        });
        Schema::dropIfExists('membership_perk_usages');
        Schema::dropIfExists('membership_referral_credits');
        Schema::dropIfExists('customer_memberships');
        Schema::dropIfExists('membership_tiers');
    }
};
