<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default rows for the `settings` table (system_settings/web_settings/pwa_settings).
 *
 * Not part of the original eShop Plus schema dump as a migration - the original app's `eshop_plus.sql`
 * shipped these rows pre-populated, imported wholesale by the legacy installer (InstallerController).
 * This deployment provisions the database via Laravel migrations instead (no SQL dump ever present - see
 * CheckInstallation.php), so nothing ever seeded these rows. Dozens of Blade views and several app/
 * Services/Controllers read specific keys out of these three settings arrays directly (`$system_settings
 * ['app_name']`, `$web_settings['site_title']`, etc.) without a null-coalescing fallback - confirmed
 * against a real deploy, which 500'd on every page with "Undefined array key \"app_name\"" once the
 * settings table existed but was empty. Seeds every key referenced that way (grepped across
 * resources/views and app/), with placeholder values an admin can edit via the admin settings screens.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $defaults = [
            'system_settings' => [
                'app_name' => 'Value Market',
                'favicon' => '',
                'logo' => '',
                'currency' => '$',
                'minimum_cart_amount' => 0,
                'order_delivery_otp_system' => '0',
                'single_seller_order_system' => '0',
            ],
            'web_settings' => [
                'site_title' => 'Value Market',
                'address' => '',
                'meta_description' => '',
                'meta_keywords' => '',
                'copyright_details' => '',
                'logo' => '',
                'favicon' => '',
                'map_iframe' => '',
                'facebook_link' => '',
                'instagram_link' => '',
                'twitter_link' => '',
                'youtube_link' => '',
                'app_short_description' => '',
                'app_download_section' => '0',
                'app_download_section_title' => '',
                'app_download_section_tagline' => '',
                'app_download_section_short_description' => '',
                'app_download_section_appstore_url' => '',
                'app_download_section_playstore_url' => '',
                'support_mode' => '0',
                'support_title' => '',
                'support_description' => '',
                'support_email' => '',
                'support_number' => '',
                'shipping_mode' => '0',
                'shipping_title' => '',
                'shipping_description' => '',
                'return_mode' => '0',
                'return_title' => '',
                'return_description' => '',
                'safety_security_mode' => '0',
                'safety_security_title' => '',
                'safety_security_description' => '',
            ],
            'pwa_settings' => [
                'name' => 'Value Market',
                'short_name' => 'Value Market',
                'description' => '',
                'background_color' => '#ffffff',
                'theme_color' => '#ffffff',
                'logo' => '',
            ],
        ];

        foreach ($defaults as $variable => $value) {
            if (!DB::table('settings')->where('variable', $variable)->exists()) {
                DB::table('settings')->insert([
                    'variable' => $variable,
                    'value' => json_encode($value),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('variable', ['system_settings', 'web_settings', 'pwa_settings'])->delete();
    }
};
