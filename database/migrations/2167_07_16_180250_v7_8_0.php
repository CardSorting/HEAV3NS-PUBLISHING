<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V780 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        DB::table('settings')
            ->where('key', 'ai.open_ai_model')
            ->update([
                'display_name' => 'Use markdown editor for profile descriptions',
                'details' => '{
            "default" : "gpt-3.5-turbo-instruct",
"options" : {
"o1": "o1",
"o1-mini": "o1-mini",
"gpt-4o": "GPT 4.0-o",
"gpt-4": "GPT 4.0",
"gpt-3.5-turbo-instruct": "GPT 3.5 Turbo Instruct"
},
"description" : "The OpenAI model to be used. You can check more details, including pricing at their docs/models page."
}',
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('settings')
            ->where('key', 'ai.open_ai_model')
            ->update([
                'display_name' => 'Use markdown editor for profile descriptions',
                'details' => '{
            "default" : "gpt-3.5-turbo-instruct",
"options" : {
"gpt-4o": "GPT 4.0-o",
"gpt-4": "GPT 4.0",
"gpt-3.5-turbo-instruct": "GPT 3.5 Turbo Instruct"
},
"description" : "The OpenAI model to be used. You can check more details, including pricing at their docs/models page."
}',
            ]);
    }
};
