<?php

namespace Tests\Feature;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Filament\Pages\ManageReviewRequestSettings;
use App\Mail\ReviewRequestMail;
use App\Models\User;
use App\Settings\ReviewRequestSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewRequestTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrder(int $daysAgo, ?string $email = 'rahim@example.test'): Order
    {
        $order = Order::create([
            'access_token' => Str::random(40), 'number' => 'LJ'.random_int(10000, 99999), 'status' => OrderStatus::Delivered,
            'customer_name' => 'রহিম', 'customer_phone' => '01712345678', 'customer_email' => $email,
            'address' => 'মিরপুর', 'subtotal' => 50000, 'total' => 50000,
        ]);
        $order->items()->create(['name' => 'উদ আতর', 'unit_price' => 50000, 'quantity' => 1, 'line_total' => 50000]);
        $order->statusHistories()->create(['to' => OrderStatus::Delivered])->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $order;
    }

    public function test_emails_go_once_after_the_delay_and_only_with_an_email(): void
    {
        Mail::fake();
        app(ReviewRequestSettings::class)->fill(['enabled' => true, 'delay_days' => 3])->save();
        $due = $this->deliveredOrder(4);
        $this->deliveredOrder(1); // too soon
        $this->deliveredOrder(5, null); // no email
        $this->deliveredOrder(60); // too long ago

        $this->artisan('reviews:request')->assertSuccessful();
        $this->artisan('reviews:request')->assertSuccessful(); // not twice

        Mail::assertQueuedCount(1);
        Mail::assertQueued(ReviewRequestMail::class, fn (ReviewRequestMail $mail) => $mail->order->is($due) && $mail->hasTo('rahim@example.test'));
        $this->assertNotNull($due->fresh()->review_requested_at);

        $html = (new ReviewRequestMail($due))->render();
        $this->assertStringContainsString('রহিম', $html);
        $this->assertStringContainsString('/account?tab=reviews', $html);
        $this->assertStringContainsString('উদ আতর', $html);
    }

    public function test_nothing_goes_while_off_and_admin_turns_it_on(): void
    {
        Mail::fake();
        $this->deliveredOrder(4);
        $this->artisan('reviews:request');
        Mail::assertNothingQueued();

        $this->actingAs(tap(User::factory()->create())->assignRole(Role::findOrCreate('super_admin', 'web')));
        Livewire::test(ManageReviewRequestSettings::class)->fillForm(['enabled' => true, 'delay_days' => 5])->call('save')->assertHasNoErrors();
        $this->assertSame([true, 5], [app(ReviewRequestSettings::class)->refresh()->enabled, app(ReviewRequestSettings::class)->delay_days]);
    }
}
