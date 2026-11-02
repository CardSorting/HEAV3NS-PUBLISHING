<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class V920 extends Migration
{
    /**
     * Post-admin panel migrations.
     */
    public function up(): void
    {

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('settings')
            ->where('payload', '"0"')
            ->update(['payload' => '""']);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        #
        # L11 -- casting all doubles to decimals
        # Use: SELECT * FROM information_schema.columns T WHERE T.TABLE_SCHEMA = 'db' AND DATA_TYPE='double'; if ever needing to do it again
        #

        // CREATOR_OFFERS
        Schema::table('creator_offers', function (Blueprint $table) {
            $table->decimal('old_profile_access_price',        12, 2)->default(5.00)->nullable()->change();
            $table->decimal('old_profile_access_price_3_months',12, 2)->default(5.00)->nullable()->change();
            $table->decimal('old_profile_access_price_6_months',12, 2)->default(5.00)->nullable()->change();
            $table->decimal('old_profile_access_price_12_months',12, 2)->default(5.00)->nullable()->change();
        });

        // PAYMENT_REQUESTS
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change(); // was NULL default
        });

        // POSTS
        Schema::table('posts', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0.00)->nullable()->change();
        });

        // REWARDS
        Schema::table('rewards', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change();
        });

        // STREAMS
        Schema::table('streams', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0.00)->nullable()->change();
        });

        // SUBSCRIPTIONS
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change();
        });

        // TAXES (percentage; currently NOT NULL, no default)
        Schema::table('taxes', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->change();
        });

        // TRANSACTIONS
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change();
        });

        // USERS
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('profile_access_price',        12, 2)->default(5.00)->change();       // NOT NULL stays
            $table->decimal('profile_access_price_6_months',12, 2)->default(5.00)->nullable()->change();
            $table->decimal('profile_access_price_3_months',12, 2)->default(5.00)->nullable()->change();
            $table->decimal('profile_access_price_12_months',12, 2)->default(5.00)->nullable()->change();
        });

        // USER_MESSAGES
        Schema::table('user_messages', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->change();
        });

        // WALLETS
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('total', 12, 2)->nullable()->change();
        });

        // WITHDRAWALS
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change();
            $table->decimal('fee',    12, 2)->default(0.00)->nullable()->change();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
}
