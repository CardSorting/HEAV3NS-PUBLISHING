<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V800 extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.tax_info_dac7_enabled',
                    'display_name' => 'Enabled',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "Allow users to prefill their tax information required for DAC7 compliance in the EU"
                        }',
                    'type' => 'checkbox',
                    'order' => 135,
                    'group' => 'Payments',
                ),
                array (
                    'key' => 'payments.tax_info_dac7_withdrawals_enforced',
                    'display_name' => 'Enforce on withdrawals',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "Prevent users from making any more withdrawals before they complete the DAC7 form"
                        }',
                    'type' => 'checkbox',
                    'order' => 140,
                    'group' => 'Payments',
                )
            )
        );

        /**
         * Create user taxes table
         */
        Schema::create('user_taxes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('issuing_country_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('issuing_country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->string('legal_name')->index();
            $table->string('tax_identification_number')->index();
            $table->string('vat_number')->nullable();
            $table->string('tax_type')->index();
            $table->text('primary_address');
            $table->timestamp('date_of_birth');
            // Timestamps last
            $table->timestamps();
        });

//        Schema::table('streams', function (Blueprint $table) {
//            $table->string('driver')->after('id');
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'payments.tax_info_dac7_enabled',
                'payments.tax_info_dac7_withdrawals_enforced',
            ])
            ->delete();

        Schema::dropIfExists('user_taxes');
    }
};
