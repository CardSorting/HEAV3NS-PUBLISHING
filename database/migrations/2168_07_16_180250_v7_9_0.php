<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V790 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        DB::table('settings')
            ->where('key', 'streams.pushr_encoder')
            ->update([
                'display_name' => 'Pushr Encoder Region',
            ]);

        /**
         * Create polls table
         */
        Schema::create('polls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('post_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->timestamp('ends_at')->nullable();
            // Timestamps last
            $table->timestamps();
        });

        /**
         * Create poll_answers table
         */
        Schema::create('poll_answers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('poll_id');
            $table->longText('answer');

            // Define foreign keys
            $table->foreign('poll_id')
                ->references('id')
                ->on('polls')
                ->onDelete('cascade');

            // Timestamps last
            $table->timestamps();
        });

        /**
         * Create poll_user_answers table
         */
        Schema::create('poll_user_answers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('poll_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('answer_id');

            $table->unique(['poll_id', 'user_id'], 'poll_user_unique');

            // Define foreign keys
            $table->foreign('poll_id')
                ->references('id')
                ->on('polls')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('answer_id')
                ->references('id')
                ->on('poll_answers')
                ->onDelete('cascade');

            // Timestamps last
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // Change the 'last_ip' column to a larger string size to accommodate IPv6
            $table->string('last_ip', 45)->nullable()->change();
        });

//        TODO:
        // Aws CloudFront Domain Name >> add non https description

        DB::table('settings')
            ->where('key', 'storage.cdn_domain_name')
            ->update([
                'details' => '{"description":"Do not include https://"}',
            ]);

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'feed.allow_post_polls',
                    'display_name' => 'Allow post polls',
                    'value' => 1,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false
                        }',
                    'type' => 'checkbox',
                    'order' => 45,
                    'group' => 'Feed',
                )
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

        Schema::table('users', function (Blueprint $table) {
            // Revert the column back to its original length if needed
            $table->string('last_ip', 15)->nullable()->change();
        });

        DB::table('settings')
            ->whereIn('key', [
                'feed.allow_post_polls',
            ])
            ->delete();

        Schema::dropIfExists('poll_user_answers');
        Schema::dropIfExists('poll_answers');
        Schema::dropIfExists('polls');
    }
};
