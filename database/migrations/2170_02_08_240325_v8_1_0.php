<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V810 extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        //TODO: Maybe add default to schedule date as well?
        DB::table('posts')
            ->whereNull('release_date')
            ->update([
                'release_date' => DB::raw('created_at')
            ]);

        Schema::table('posts', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('release_date');
            $table->index('expire_date');
        });

        Schema::table('public_pages', function (Blueprint $table) {
            $table->boolean('show_last_update_date')->default(0)->nullable();
        });

        DB::table('public_pages')
            ->whereIn('slug', ['help','privacy','terms-and-conditions'])
            ->update([
                'show_last_update_date' => true
            ]);

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'compliance.allow_text_only_ppv',
                    'display_name' => 'Allow creators to publish text-only PPV content',
                    'value' => 1,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "If enabled, creators will be allowed to sell text-only PPV messages & posts (no media requirements) "
                        }',
                    'type' => 'checkbox',
                    'order' => 1200,
                    'group' => 'Compliance',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'compliance.enforce_tos_check_on_id_verify',
                    'display_name' => 'Enforce TOS & Creator agreement checkbox on ID-verify',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "If enabled, creators will be allowed to sell text-only PPV messages & posts (no media requirements) "
                        }',
                    'type' => 'checkbox',
                    'order' => 1210,
                    'group' => 'Compliance',
                )
            )
        );
        \DB::table('public_pages')->insert(array (
        array (
            'slug' => 'creator-agreement',
            'title' => 'Creator Agreement',
            'short_title' => 'Creator Agreement',
            'content' => '<p class="tdfocus-1744223181420" data-pm-slice="1 3 []">This Creator Agreement ("Agreement") sets forth the terms between the platform operator ("Platform," "We," "Us") and the content provider or creator ("Creator," "You," "Your"). By creating an account, verifying your identity, and uploading content, you explicitly accept these terms.</p>
<p class="tdfocus-1744223181420" data-pm-slice="1 3 []">&nbsp;</p>
<h3 class="tdfocus-1744223220131">Eligibility</h3>
<ul data-spread="false">
<li>
<p>You confirm you are at least 18 years of age.</p>
</li>
<li>
<p>You must provide accurate, valid, government-issued identification to verify your age and identity.</p>
</li>
</ul>
<h3>Pre-screening of Content</h3>
<ul data-spread="false">
<li>
<p>All content uploaded by you will be reviewed and pre-screened by the Platform prior to publication.</p>
</li>
<li>
<p>We reserve the right to reject or remove any content determined to be illegal, inappropriate, or otherwise violating Platform guidelines or applicable laws.</p>
</li>
</ul>
<h3>Content Ownership and Licensing</h3>
<ul data-spread="false">
<li>
<p>You retain full ownership of your uploaded content.</p>
</li>
<li>
<p>You grant the Platform a worldwide, non-exclusive, royalty-free license to host, promote, display, and monetize your content.</p>
</li>
<li>
<p>You may remove your content at any time, excluding previously purchased or downloaded content by users.</p>
</li>
</ul>
<h3>Compliance and Prohibited Activities</h3>
<ul data-spread="false">
<li>
<p>You expressly agree that all content you upload will adhere to applicable laws and Platform standards.</p>
</li>
<li>
<p>You shall not upload content involving minors, non-consensual material, or illegal acts.</p>
</li>
</ul>
<h3>Consent and Documentation</h3>
<ul data-spread="false">
<li>
<p>You are required to obtain and retain written consent from every person depicted in your content, clearly specifying:</p>
<ul data-spread="false">
<li>
<p>Consent to be depicted in the content.</p>
</li>
<li>
<p>Consent for public distribution on the Platform.</p>
</li>
<li>
<p>Consent for downloading of content by users, if applicable.</p>
</li>
</ul>
</li>
<li>
<p>You must verify and document the identity and age of all persons depicted, ensuring each is an adult (18+). You agree to provide these documents promptly upon request by the Platform or relevant authorities.</p>
</li>
</ul>
<h3>Image and Likeness Consent</h3>
<ul data-spread="false">
<li>
<p>By checking the designated consent checkbox upon signup, you explicitly acknowledge and grant the Platform permission to publicly use, display, and distribute your images and likeness.</p>
</li>
</ul>
<h3>Revenue and Payments</h3>
<ul data-spread="false">
<li>
<p>Revenue generated from your content is subject to the Platform&rsquo;s revenue-sharing structure, clearly communicated upon account creation.</p>
</li>
<li>
<p>Payments will be made according to the Platform&rsquo;s established payout schedule.</p>
</li>
<li>
<p>You are responsible for applicable taxes.</p>
</li>
</ul>
<h3>Creator Responsibilities</h3>
<ul data-spread="false">
<li>
<p>You agree to regularly monitor and manage your content, promptly reporting unauthorized use or copyright infringements.</p>
</li>
<li>
<p>You agree to adhere strictly to Platform policies and applicable laws.</p>
</li>
</ul>
<h3>Termination</h3>
<ul data-spread="false">
<li>
<p>Either party may terminate this agreement at any time with appropriate notice or by account closure.</p>
</li>
<li>
<p>Upon termination, licenses end except for continued access by users who previously purchased or downloaded your content.</p>
</li>
</ul>
<h3>Indemnification</h3>
<ul data-spread="false">
<li>
<p>You agree to indemnify and hold harmless the Platform against any liabilities, claims, or expenses arising from your uploaded content or actions on the Platform.</p>
</li>
</ul>
<h3>Amendments</h3>
<ul data-spread="false">
<li>
<p>The Platform reserves the right to amend this Agreement periodically. Continued use of the Platform constitutes your acceptance of any updated terms.</p>
</li>
</ul>
<h3>Governing Law</h3>
<ul data-spread="false">
<li>
<p>This Agreement shall be governed by the laws of [Your Jurisdiction].</p>
</li>
</ul>',
            'created_at' => '2025-04-08 13:47:41',
            'updated_at' => '2025-04-09 18:31:36',
            'page_order' => 4,
            'shown_in_footer' => 0,
            'is_tos' => 0,
            'is_privacy' => 0,
            'show_last_update_date' => 1,
        )
        )
        );

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('posts')
            ->whereColumn('release_date', 'created_at')
            ->update(['release_date' => null]);

        Schema::table('public_pages', function (Blueprint $table) {
            $table->dropColumn('show_last_update_date');
        });
        DB::table('settings')
            ->whereIn('key', [
                'compliance.allow_text_only_ppv',
                'compliance.enforce_tos_check_on_id_verify',
            ])
            ->delete();

        DB::table('public_pages')
            ->whereIn('slug', [
                'creator-agreement',
            ])
            ->delete();


        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['release_date']);
            $table->dropIndex(['expire_date']);
        });

    }
};
