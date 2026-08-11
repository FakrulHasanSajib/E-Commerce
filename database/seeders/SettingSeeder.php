<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // --- GENERAL GROUP ---
            [
                'group' => 'general',
                'key' => 'general.site_name',
                'value' => 'LaraShop',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.tagline',
                'value' => 'Premium Single-Tenant E-commerce Store',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.favicon',
                'value' => '/images/favicon.png',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.logo_light',
                'value' => '/images/logo-light.png',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.logo_dark',
                'value' => '/images/logo-dark.png',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.timezone',
                'value' => 'Asia/Dhaka',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.date_format',
                'value' => 'Y-m-d',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.currency',
                'value' => 'BDT',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.language',
                'value' => 'en',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.maintenance_mode',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'group' => 'general',
                'key' => 'general.multi_language',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
            ],

            // --- THEME GROUP ---
            [
                'group' => 'theme',
                'key' => 'theme.primary_color',
                'value' => '#4f46e5',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'theme',
                'key' => 'theme.secondary_color',
                'value' => '#06b6d4',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'theme',
                'key' => 'theme.font_family',
                'value' => 'Outfit',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'theme',
                'key' => 'theme.layout',
                'value' => 'full-width',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'theme',
                'key' => 'theme.dark_mode',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'group' => 'theme',
                'key' => 'theme.custom_css',
                'value' => '',
                'type' => 'textarea',
                'is_public' => true,
            ],
            [
                'group' => 'theme',
                'key' => 'theme.custom_js',
                'value' => '',
                'type' => 'textarea',
                'is_public' => true,
            ],

            // --- SEO GROUP ---
            [
                'group' => 'seo',
                'key' => 'seo.meta_title',
                'value' => 'LaraShop — Modern E-commerce Platform',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'seo',
                'key' => 'seo.meta_description',
                'value' => 'A robust, high-performance single-tenant e-commerce platform built with Laravel.',
                'type' => 'textarea',
                'is_public' => true,
            ],
            [
                'group' => 'seo',
                'key' => 'seo.meta_keywords',
                'value' => 'e-commerce, online store, shop, laravel e-commerce',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'seo',
                'key' => 'seo.google_analytics_id',
                'value' => '',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'seo',
                'key' => 'seo.facebook_pixel_id',
                'value' => '',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'seo',
                'key' => 'seo.google_tag_manager_id',
                'value' => '',
                'type' => 'string',
                'is_public' => true,
            ],
            [
                'group' => 'seo',
                'key' => 'seo.robots_txt',
                'value' => "User-agent: *\nAllow: /",
                'type' => 'textarea',
                'is_public' => true,
            ],
            [
                'group' => 'seo',
                'key' => 'seo.sitemap_auto_generate',
                'value' => true,
                'type' => 'boolean',
                'is_public' => true,
            ],

            // --- PAYMENT GROUP (SECURE/PRIVATE) ---
            [
                'group' => 'payment',
                'key' => 'payment.sslcommerz',
                'value' => [
                    'enabled' => false,
                    'store_id' => '',
                    'store_password' => '',
                    'sandbox' => true,
                    'min_order' => 10,
                    'max_order' => 50000,
                ],
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'payment',
                'key' => 'payment.bkash',
                'value' => [
                    'enabled' => false,
                    'app_key' => '',
                    'app_secret' => '',
                    'username' => '',
                    'password' => '',
                    'sandbox' => true,
                    'min_order' => 1,
                    'max_order' => 20000,
                ],
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'payment',
                'key' => 'payment.nagad',
                'value' => [
                    'enabled' => false,
                    'merchant_id' => '',
                    'merchant_number' => '',
                    'public_key' => '',
                    'private_key' => '',
                    'sandbox' => true,
                    'min_order' => 1,
                    'max_order' => 20000,
                ],
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'payment',
                'key' => 'payment.rocket',
                'value' => [
                    'enabled' => false,
                    'merchant_id' => '',
                    'sandbox' => true,
                    'min_order' => 1,
                    'max_order' => 20000,
                ],
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'payment',
                'key' => 'payment.card',
                'value' => [
                    'enabled' => false,
                    'publishable_key' => '',
                    'secret_key' => '',
                    'sandbox' => true,
                    'min_order' => 10,
                    'max_order' => 100000,
                ],
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'payment',
                'key' => 'payment.cod',
                'value' => [
                    'enabled' => true,
                    'min_order' => 1,
                    'max_order' => 10000,
                    'available_areas' => ['all'],
                ],
                'type' => 'json',
                'is_public' => false,
            ],

            // --- SHIPPING GROUP ---
            [
                'group' => 'shipping',
                'key' => 'shipping.methods',
                'value' => [
                    [
                        'id' => 'inside_dhaka',
                        'name' => 'Inside Dhaka',
                        'charge' => 60,
                        'estimated_days' => '1-3 days',
                        'enabled' => true,
                    ],
                    [
                        'id' => 'outside_dhaka',
                        'name' => 'Outside Dhaka',
                        'charge' => 120,
                        'estimated_days' => '3-5 days',
                        'enabled' => true,
                    ],
                ],
                'type' => 'json',
                'is_public' => true,
            ],
            [
                'group' => 'shipping',
                'key' => 'shipping.free_shipping_threshold',
                'value' => 5000,
                'type' => 'integer',
                'is_public' => true,
            ],
            [
                'group' => 'shipping',
                'key' => 'shipping.zones',
                'value' => [
                    'dhaka' => ['charge' => 60, 'districts' => ['Dhaka', 'Gazipur', 'Narayanganj']],
                    'outside' => ['charge' => 120, 'districts' => ['*']],
                ],
                'type' => 'json',
                'is_public' => true,
            ],

            // --- NOTIFICATIONS GROUP (SECURE/PRIVATE) ---
            [
                'group' => 'notifications',
                'key' => 'notifications.sms',
                'value' => [
                    'enabled' => false,
                    'provider' => 'twilio',
                    'twilio_sid' => '',
                    'twilio_token' => '',
                    'twilio_from' => '',
                ],
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'notifications',
                'key' => 'notifications.smtp',
                'value' => [
                    'host' => 'mailhog',
                    'port' => 1025,
                    'username' => '',
                    'password' => '',
                    'encryption' => 'tls',
                    'from_address' => 'hello@example.com',
                    'from_name' => 'LaraShop Support',
                ],
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'notifications',
                'key' => 'notifications.events',
                'value' => [
                    'order_placed' => ['email' => true, 'sms' => false],
                    'order_shipped' => ['email' => true, 'sms' => true],
                    'order_cancelled' => ['email' => true, 'sms' => false],
                ],
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'notifications',
                'key' => 'notifications.whatsapp',
                'value' => [
                    'enabled' => false,
                    'api_url' => '',
                    'api_key' => '',
                ],
                'type' => 'json',
                'is_public' => false,
            ],

            // --- SOCIAL & CONTACT GROUP ---
            [
                'group' => 'social',
                'key' => 'social.links',
                'value' => [
                    'facebook' => 'https://facebook.com',
                    'instagram' => 'https://instagram.com',
                    'twitter' => 'https://twitter.com',
                    'youtube' => 'https://youtube.com',
                ],
                'type' => 'json',
                'is_public' => true,
            ],
            [
                'group' => 'social',
                'key' => 'social.contact_info',
                'value' => [
                    'phone' => '+8801700000000',
                    'email' => 'support@larashop.test',
                    'address' => '123 E-commerce Street, Dhaka, Bangladesh',
                    'support_hours' => 'Sat - Thu: 9:00 AM - 6:00 PM',
                ],
                'type' => 'json',
                'is_public' => true,
            ],
            [
                'group' => 'social',
                'key' => 'social.live_chat_enabled',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'group' => 'social',
                'key' => 'social.whatsapp_button_enabled',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'group' => 'social',
                'key' => 'social.whatsapp_number',
                'value' => '',
                'type' => 'string',
                'is_public' => true,
            ],

            // --- E-COMMERCE BEHAVIOR GROUP ---
            [
                'group' => 'ecommerce',
                'key' => 'ecommerce.guest_checkout',
                'value' => true,
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'group' => 'ecommerce',
                'key' => 'ecommerce.stock_management',
                'value' => true,
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'group' => 'ecommerce',
                'key' => 'ecommerce.review_auto_publish',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'group' => 'ecommerce',
                'key' => 'ecommerce.wishlist_enabled',
                'value' => true,
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'group' => 'ecommerce',
                'key' => 'ecommerce.comparison_enabled',
                'value' => true,
                'type' => 'boolean',
                'is_public' => true,
            ],
            [
                'group' => 'ecommerce',
                'key' => 'ecommerce.related_products_enabled',
                'value' => true,
                'type' => 'boolean',
                'is_public' => true,
            ],

            // --- SECURITY GROUP (SECURE/PRIVATE) ---
            [
                'group' => 'security',
                'key' => 'security.recaptcha',
                'value' => [
                    'enabled' => false,
                    'site_key' => '',
                    'secret_key' => '',
                ],
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'security',
                'key' => 'security.two_factor_enforced',
                'value' => false,
                'type' => 'boolean',
                'is_public' => false,
            ],
            [
                'group' => 'security',
                'key' => 'security.login_attempt_limit',
                'value' => 5,
                'type' => 'integer',
                'is_public' => false,
            ],
            [
                'group' => 'security',
                'key' => 'security.admin_ip_whitelist',
                'value' => [],
                'type' => 'json',
                'is_public' => false,
            ],

            // --- LEGAL GROUP ---
            [
                'group' => 'legal',
                'key' => 'legal.terms_of_service',
                'value' => '<h1>Terms of Service</h1><p>Welcome to our store...</p>',
                'type' => 'textarea',
                'is_public' => true,
            ],
            [
                'group' => 'legal',
                'key' => 'legal.privacy_policy',
                'value' => '<h1>Privacy Policy</h1><p>Your privacy is important to us...</p>',
                'type' => 'textarea',
                'is_public' => true,
            ],
            [
                'group' => 'legal',
                'key' => 'legal.return_policy',
                'value' => '<h1>Return Policy</h1><p>Read about our 30-day return policy...</p>',
                'type' => 'textarea',
                'is_public' => true,
            ],
            [
                'group' => 'legal',
                'key' => 'legal.cookie_consent',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'group' => $setting['group'],
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'is_public' => $setting['is_public'],
                ]
            );
        }
    }
}
