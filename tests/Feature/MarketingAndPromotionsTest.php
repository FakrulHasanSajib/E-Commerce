<?php

use App\Models\Affiliate;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Marketing\AffiliateService;
use App\Services\Marketing\AnalyticsTrackingService;
use App\Services\Marketing\GiftCardService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->customerA = User::factory()->create(['email' => 'affiliate@larashop.test']);
    $this->customerB = User::factory()->create(['email' => 'buyer@larashop.test']);

    $this->zone = ShippingZone::create(['name' => 'Inside Dhaka', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Flat Rate',
        'type' => 'flat_rate',
        'cost' => 50.00,
        'is_active' => true,
    ]);
});

test('gift card atomic balance redemptions and cancellation refunds', function () {
    $giftCardService = app(GiftCardService::class);

    // 1. Create gift card of 1000 BDT
    $card = $giftCardService->createGiftCard(1000.00, $this->admin->id);
    expect($card->current_balance)->toBe('1000.00');

    $order = Order::create([
        'user_id' => $this->customerB->id,
        'order_number' => 'ORD-GIFT-001',
        'status' => 'pending',
        'subtotal' => 400.00,
        'grand_total' => 400.00,
        'total_amount' => 400.00,
        'currency' => 'BDT',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'Buyer B',
        'customer_email' => 'buyer@larashop.test',
        'customer_phone' => '01711111111',
        'shipping_address' => 'Dhaka',
    ]);

    // 2. Redeem 400 BDT for order
    $redeemed = $giftCardService->redeemGiftCard($card->code, 400.00, $order);
    expect($redeemed)->toBe(400.00);
    expect($card->fresh()->current_balance)->toBe('600.00');

    // 3. Cancel order and refund to gift card
    $giftCardService->refundToGiftCard($order);
    expect($card->fresh()->current_balance)->toBe('1000.00');
});

test('affiliate self referral blocks, subtotal commissions, delivery credits, and return reversals', function () {
    $affiliateService = app(AffiliateService::class);

    // 1. Register Customer A as Affiliate (5% rate)
    $affiliate = $affiliateService->registerAffiliate($this->customerA, 5.00);
    expect($affiliate->affiliate_code)->toBeString();

    // 2. Self-referral attempt (Customer A buys using Customer A's affiliate code)
    $selfOrder = Order::create([
        'user_id' => $this->customerA->id,
        'order_number' => 'ORD-SELF-001',
        'status' => 'pending',
        'subtotal' => 1000.00,
        'grand_total' => 1000.00,
        'total_amount' => 1000.00,
        'currency' => 'BDT',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'Affiliate A',
        'customer_email' => 'affiliate@larashop.test',
        'customer_phone' => '01722222222',
        'shipping_address' => 'Dhaka',
    ]);

    $selfReferral = $affiliateService->recordOrderReferral($selfOrder, $affiliate->affiliate_code);
    expect($selfReferral)->toBeNull(); // Self-referral blocked!

    // 3. Legitimate Customer B order (Subtotal 2000, Discount 200 => Base 1800)
    $validOrder = Order::create([
        'user_id' => $this->customerB->id,
        'order_number' => 'ORD-BUYER-001',
        'status' => 'pending',
        'subtotal' => 2000.00,
        'grand_total' => 1800.00,
        'discount' => 200.00,
        'total_amount' => 1800.00,
        'currency' => 'BDT',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'Buyer B',
        'customer_email' => 'buyer@larashop.test',
        'customer_phone' => '01711111111',
        'shipping_address' => 'Dhaka',
    ]);

    $referral = $affiliateService->recordOrderReferral($validOrder, $affiliate->affiliate_code);
    expect($referral)->not()->toBeNull();
    // 5% of 1800 = 90 BDT
    expect((float) $referral->commission_amount)->toBe(90.00);
    expect($referral->status)->toBe('pending');

    // 4. Order delivered -> Credit commission idempotently
    $validOrder->update(['status' => 'delivered']);
    $affiliateService->creditCommissionOnDelivery($validOrder);

    expect($referral->fresh()->status)->toBe('paid');
    expect((float) $affiliate->fresh()->earnings_balance)->toBe(90.00);

    // Idempotency check: call delivery credit again
    $affiliateService->creditCommissionOnDelivery($validOrder);
    expect((float) $affiliate->fresh()->earnings_balance)->toBe(90.00); // Unchanged!

    // 5. Order returned/refunded -> Reverse commission
    $affiliateService->reverseCommission($validOrder);
    expect($referral->fresh()->status)->toBe('rejected');
    expect((float) $affiliate->fresh()->earnings_balance)->toBe(0.00);
});

test('dynamic product feeds rendering in xml and csv formats', function () {
    Cache::flush();

    // 1. Google Shopping XML
    $xmlResponse = $this->get('/feeds/google-shopping.xml')->assertStatus(200);
    expect($xmlResponse->headers->get('Content-Type'))->toContain('xml');
    expect($xmlResponse->getContent())->toContain('<rss');

    // 2. Facebook Catalog CSV
    $csvResponse = $this->get('/feeds/facebook-catalog.csv')->assertStatus(200);
    expect($csvResponse->headers->get('Content-Type'))->toContain('csv');
    expect($csvResponse->getContent())->toContain('id,title,description,availability');
});

test('server side analytics tracking payloads generation', function () {
    $trackingService = app(AnalyticsTrackingService::class);

    $capi = $trackingService->generateFacebookCapiPayload('Purchase', [
        'value' => 1500.00,
        'currency' => 'BDT',
        'content_ids' => [1, 2],
    ], $this->customerB);

    expect($capi['data'][0]['event_name'])->toBe('Purchase');
    expect($capi['data'][0]['custom_data']['value'])->toBe(1500.0);

    $gtm = $trackingService->generateGtmDataLayer('purchase', [
        'value' => 1500.00,
        'currency' => 'BDT',
    ]);

    expect($gtm['event'])->toBe('purchase');
    expect($gtm['ecommerce']['value'])->toBe(1500.0);
});
