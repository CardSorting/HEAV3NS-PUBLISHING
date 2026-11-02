<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V730 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->float('total', 12)->nullable()->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->float('amount', 12)->nullable()->change();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->float('price', 12)->nullable()->change();
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->float('amount', 12)->nullable()->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->float('amount', 12)->nullable()->change();
        });

        Schema::table('streams', function (Blueprint $table) {
            $table->float('price', 12)->nullable()->change();
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->float('fee', 12)->nullable()->change();
            $table->float('amount', 12)->nullable()->change();
        });

        Schema::table('user_messages', function (Blueprint $table) {
            $table->float('price', 12)->nullable()->change();
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->float('amount', 12)->nullable()->change();
        });

        DB::table('settings')->insert(
            array(
                'key' => 'ai.open_ai_model',
                'display_name' => 'OpenAI Model',
                'value' => 'gpt-3.5-turbo-instruct',
                'details' => '{
"default" : "gpt-3.5-turbo-instruct",
"options" : {
"gpt-4o": "GPT 4.0-o",
"gpt-4": "GPT 4.0",
"gpt-3.5-turbo-instruct": "GPT 3.5 Turbo Instruct"
},
"description" : "The OpenAI model to be used. You can check more details, including pricing at their docs/models page."
}',
                'type' => 'select_dropdown',
                'order' => 22,
                'group' => 'AI',
            )
        );


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->float('total')->nullable()->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->float('amount')->nullable()->change();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->float('price')->nullable()->change();
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->float('amount')->nullable()->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->float('amount')->nullable()->change();
        });

        Schema::table('streams', function (Blueprint $table) {
            $table->float('price')->nullable()->change();
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->float('fee')->nullable()->change();
            $table->float('amount')->nullable()->change();
        });

        Schema::table('user_messages', function (Blueprint $table) {
            $table->float('price')->nullable()->change();
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->float('amount')->nullable()->change();
        });

        DB::table('settings')
            ->whereIn('key', [
                'ai.open_ai_model',
            ])
            ->delete();

    }
};
