<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Inquiry;
use App\Mail\InquiryReplyMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_public_home_page_is_accessible(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_reservation_page_is_accessible(): void
    {
        $response = $this->get('/reservation');

        $response->assertStatus(200);
        $response->assertSee('placeholder="Juan dela Cruz"', false);
        $response->assertSee('pattern="(?:\\+63[0-9]{10}|09[0-9]{9})"', false);
    }

    public function test_inquiry_page_is_accessible(): void
    {
        $response = $this->get('/inquiry');

        $response->assertStatus(200);
        $response->assertSee('pattern="(?:\\+63[0-9]{10}|09[0-9]{9})"', false);
    }

    public function test_reservation_requires_two_day_lead_time(): void
    {
        Package::create([
            'name' => 'Classic Package',
            'slug' => 'classic-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
            'event_type' => 'Wedding',
        ]);

        $response = $this->from('/reservation')->post('/reservation', [
            'full_name' => 'Test User',
            'contact_number' => '09171234567',
            'email' => 'test@example.com',
            'address' => '123 Main Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDay()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Sample Venue',
            'guest_count' => 50,
            'estimated_budget' => 10000,
            'package_id' => 1,
            'website' => '',
            'form_started' => now()->timestamp,
            'g-recaptcha-response' => 'test',
        ]);

        $response->assertRedirect('/reservation');
        $response->assertSessionHasErrors('event_date');
    }

    public function test_reservation_rejects_invalid_contact_email_address_and_venue(): void
    {
        Package::create([
            'name' => 'Classic Package',
            'slug' => 'classic-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
            'event_type' => 'Wedding',
        ]);

        $response = $this->from('/reservation')->post('/reservation', [
            'full_name' => 'Test User',
            'contact_number' => '123',
            'email' => 'not-an-email',
            'address' => 'Apt',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'X',
            'guest_count' => 50,
            'estimated_budget' => 10000,
            'package_id' => 1,
            'website' => '',
            'form_started' => now()->timestamp,
            'g-recaptcha-response' => 'test',
        ]);

        $response->assertRedirect('/reservation');
        $response->assertSessionHasErrors(['contact_number', 'email', 'address', 'venue']);
    }

    public function test_reservation_accepts_valid_philippine_mobile_number_formats(): void
    {
        $request = new \Illuminate\Http\Request([
            'full_name' => 'Test User',
            'contact_number' => '+639814542318',
            'email' => 'valid@example.com',
            'address' => '123 Main Street, Cebu City',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Sample Venue Hall',
            'guest_count' => 50,
            'estimated_budget' => 10000,
            'package_id' => 1,
            'website' => '',
            'form_started' => now()->timestamp,
            'g-recaptcha-response' => 'test',
        ]);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), (new \App\Http\Requests\StoreReservationRequest)->rules());

        $this->assertFalse($validator->fails(), 'Expected valid Philippine mobile numbers to pass validation. Errors: ' . json_encode($validator->errors()->all()));

        $request->merge(['contact_number' => '09814542318']);
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), (new \App\Http\Requests\StoreReservationRequest)->rules());

        $this->assertFalse($validator->fails(), 'Expected 09-prefixed numbers to pass validation. Errors: ' . json_encode($validator->errors()->all()));
    }

    public function test_admin_report_csv_export_uses_the_selected_period_summary(): void
    {
        $controller = app(\App\Http\Controllers\ReportController::class);
        $response = $controller->export(app(\App\Services\ReportService::class), 'daily');

        $csv = $response->getContent();

        $this->assertStringContainsString('Period', $csv);
        $this->assertStringContainsString('daily', strtolower($csv));
        $this->assertStringContainsString('Reservations', $csv);
    }

    public function test_admin_inquiry_reply_is_sent_to_the_customer_email(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $inquiry = Inquiry::create([
            'full_name' => 'Inquiry Client',
            'contact_number' => '09171234567',
            'email' => 'customer@example.com',
            'subject' => 'Event inquiry',
            'category' => 'Catering',
            'message' => 'Please send details.',
            'status' => 'in_progress',
        ]);

        app(\App\Http\Controllers\AdminController::class)->replyToInquiry(
            new \Illuminate\Http\Request(['reply' => 'Thank you for reaching out.']),
            $inquiry,
        );

        Mail::assertSent(InquiryReplyMail::class, function (InquiryReplyMail $mail) {
            return $mail->hasTo('customer@example.com')
                && $mail->subjectLine === 'Re: Event inquiry'
                && $mail->reply === 'Thank you for reaching out.';
        });
    }

    public function test_admin_can_update_total_amount_down_payment_and_mark_reservation_fully_paid(): void
    {
        $reservation = \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Alice Client',
            'contact_number' => '09170000001',
            'email' => 'alice@example.com',
            'address' => '123 Alice St',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Alice Venue',
            'guest_count' => 80,
            'estimated_budget' => 25000,
            'status' => 'confirmed',
            'payment_status' => 'Unpaid',
            'payment_type' => 'Unpaid',
            'amount_paid' => 0,
            'balance' => 25000,
            'reservation_code' => 'RES-PAYMENT-001',
        ]);

        $request = new \Illuminate\Http\Request([
            'estimated_budget' => 35000,
            'amount_paid' => 8000,
        ]);

        app(\App\Http\Controllers\AdminController::class)->updateReservationStatus($request, $reservation);
        $reservation->refresh();

        $this->assertSame(35000.0, (float) $reservation->estimated_budget);
        $this->assertSame(8000.0, (float) $reservation->amount_paid);
        $this->assertSame('Downpayment', $reservation->payment_status);
        $this->assertSame(27000.0, (float) $reservation->balance);

        $fullyPaidRequest = new \Illuminate\Http\Request([
            'estimated_budget' => 35000,
            'amount_paid' => 35000,
            'mark_fully_paid' => true,
        ]);

        app(\App\Http\Controllers\AdminController::class)->updateReservationStatus($fullyPaidRequest, $reservation);
        $reservation->refresh();

        $this->assertSame(35000.0, (float) $reservation->estimated_budget);
        $this->assertSame('Fully Paid', $reservation->payment_status);
        $this->assertSame('Full Payment', $reservation->payment_type);
        $this->assertSame(35000.0, (float) $reservation->amount_paid);
        $this->assertSame(0.0, (float) $reservation->balance);
    }

    public function test_admin_dashboard_sidebar_calendar_lists_booked_customers_for_the_month(): void
    {
        $bookingDate = now()->startOfMonth()->addDays(3);

        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Alice Client',
            'contact_number' => '09170000001',
            'email' => 'alice@example.com',
            'address' => '123 Alice St',
            'event_type' => 'Wedding',
            'event_date' => $bookingDate->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Alice Venue',
            'guest_count' => 80,
            'estimated_budget' => 20000,
            'status' => 'confirmed',
            'payment_status' => 'Downpayment',
            'payment_type' => 'Downpayment',
            'amount_paid' => 3000,
            'balance' => 17000,
            'reservation_code' => 'RES-SIDEBAR-001',
        ]);

        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Bob Client',
            'contact_number' => '09170000002',
            'email' => 'bob@example.com',
            'address' => '456 Bob St',
            'event_type' => 'Birthday',
            'event_date' => $bookingDate->toDateString(),
            'event_time' => '17:00',
            'venue' => 'Bob Venue',
            'guest_count' => 50,
            'estimated_budget' => 12000,
            'status' => 'pending',
            'payment_status' => 'Unpaid',
            'payment_type' => 'Unpaid',
            'amount_paid' => 0,
            'balance' => 12000,
            'reservation_code' => 'RES-SIDEBAR-002',
        ]);

        $response = app(\App\Http\Controllers\AdminController::class)->index();
        $data = $response->getData(true);

        $this->assertArrayHasKey('sidebarCalendar', $data);
        $this->assertSame(now()->translatedFormat('F Y'), $data['sidebarCalendar']['monthLabel']);
        $this->assertArrayHasKey($bookingDate->toDateString(), $data['sidebarCalendar']['bookings']);
        $this->assertContains('Alice Client', $data['sidebarCalendar']['bookings'][$bookingDate->toDateString()]);
        $this->assertContains('Bob Client', $data['sidebarCalendar']['bookings'][$bookingDate->toDateString()]);
    }

    public function test_admin_can_track_reservation_payment_status_and_balance(): void
    {
        $reservation = \App\Models\Reservation::create([
            'client_id' => null,
            'package_id' => 1,
            'full_name' => 'Test Client',
            'contact_number' => '09814542318',
            'email' => 'client@example.com',
            'address' => '123 Test Street, Cebu City',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Nustad Hall',
            'guest_count' => 100,
            'estimated_budget' => 25000,
            'status' => 'pending',
            'reservation_code' => 'RES-TEST-001',
        ]);

        $request = new \Illuminate\Http\Request([
            'status' => 'completed',
            'payment_status' => 'Downpayment',
            'payment_type' => 'Downpayment',
            'total_cost' => 30000,
            'amount_paid' => 8000,
        ]);

        $response = app(\App\Http\Controllers\AdminController::class)->updateReservationStatus($request, $reservation);

        $this->assertSame('completed', $reservation->fresh()->status);
        $this->assertSame('Downpayment', $reservation->fresh()->payment_status);
        $this->assertSame('Downpayment', $reservation->fresh()->payment_type);
        $this->assertSame(30000.0, (float) $reservation->fresh()->total_cost);
        $this->assertSame(8000.0, (float) $reservation->fresh()->amount_paid);
        $this->assertSame(22000.0, (float) $reservation->fresh()->balance);
        $this->assertNotNull($response);
    }

    public function test_admin_can_edit_accepted_reservation_schedule_and_package(): void
    {
        $originalPackage = Package::create([
            'name' => 'Original Package',
            'slug' => 'original-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
        ]);
        $updatedPackage = Package::create([
            'name' => 'Updated Package',
            'slug' => 'updated-package',
            'price' => 750,
            'min_guests' => 30,
            'max_guests' => 250,
        ]);
        $reservation = \App\Models\Reservation::create([
            'package_id' => $originalPackage->id,
            'full_name' => 'Accepted Client',
            'contact_number' => '09171234567',
            'email' => 'accepted@example.com',
            'address' => '123 Accepted Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Accepted Venue',
            'guest_count' => 80,
            'estimated_budget' => 20000,
            'status' => 'confirmed',
            'reservation_code' => 'RES-EDIT-001',
        ]);

        $request = new \Illuminate\Http\Request([
            'status' => 'confirmed',
            'package_id' => $updatedPackage->id,
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '19:30',
        ]);

        app(\App\Http\Controllers\AdminController::class)->updateReservationStatus($request, $reservation);

        $updated = $reservation->fresh();
        $this->assertSame($updatedPackage->id, $updated->package_id);
        $this->assertSame(now()->addDays(10)->toDateString(), $updated->event_date);
        $this->assertSame('19:30', $updated->event_time);
    }

    public function test_admin_reservations_can_be_filtered_by_status_and_payment_status(): void
    {
        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Alice Client',
            'contact_number' => '09171234567',
            'email' => 'alice@example.com',
            'address' => '123 Alice St',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Alice Venue',
            'guest_count' => 80,
            'estimated_budget' => 20000,
            'status' => 'confirmed',
            'payment_status' => 'Downpayment',
            'payment_type' => 'Downpayment',
            'amount_paid' => 6000,
            'balance' => 14000,
            'reservation_code' => 'RES-FILT-001',
        ]);

        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Bob Client',
            'contact_number' => '09181234567',
            'email' => 'bob@example.com',
            'address' => '456 Bob St',
            'event_type' => 'Birthday',
            'event_date' => now()->addDays(7)->toDateString(),
            'event_time' => '17:00',
            'venue' => 'Bob Venue',
            'guest_count' => 50,
            'estimated_budget' => 12000,
            'status' => 'pending',
            'payment_status' => 'Unpaid',
            'payment_type' => 'Unpaid',
            'amount_paid' => 0,
            'balance' => 12000,
            'reservation_code' => 'RES-FILT-002',
        ]);

        $request = new \Illuminate\Http\Request([
            'status' => 'confirmed',
            'payment_status' => 'Downpayment',
        ]);

        $response = app(\App\Http\Controllers\AdminController::class)->reservations($request);
        $data = $response->getData(true);

        $this->assertCount(1, $data['reservations']);
        $this->assertSame('Alice Client', $data['reservations'][0]->full_name);
        $this->assertSame('confirmed', $data['filterStatus']);
        $this->assertSame('Downpayment', $data['filterPaymentStatus']);
    }

    public function test_admin_reservations_can_be_filtered_by_date_range_and_customer_search(): void
    {
        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Alice Client',
            'contact_number' => '09170000001',
            'email' => 'alice@example.com',
            'address' => '123 Alice St',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Alice Venue',
            'guest_count' => 80,
            'estimated_budget' => 20000,
            'status' => 'confirmed',
            'payment_status' => 'Downpayment',
            'payment_type' => 'Downpayment',
            'amount_paid' => 3000,
            'balance' => 17000,
            'reservation_code' => 'RES-FILT-SEARCH-001',
        ]);

        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Bob Client',
            'contact_number' => '09180000002',
            'email' => 'bob@example.com',
            'address' => '456 Bob St',
            'event_type' => 'Birthday',
            'event_date' => now()->addDays(15)->toDateString(),
            'event_time' => '17:00',
            'venue' => 'Bob Venue',
            'guest_count' => 50,
            'estimated_budget' => 12000,
            'status' => 'pending',
            'payment_status' => 'Unpaid',
            'payment_type' => 'Unpaid',
            'amount_paid' => 0,
            'balance' => 12000,
            'reservation_code' => 'RES-FILT-SEARCH-002',
        ]);

        $request = new \Illuminate\Http\Request([
            'search' => 'alice',
            'date_from' => now()->addDays(3)->toDateString(),
            'date_to' => now()->addDays(10)->toDateString(),
        ]);

        $response = app(\App\Http\Controllers\AdminController::class)->reservations($request);
        $data = $response->getData(true);

        $this->assertCount(1, $data['reservations']);
        $this->assertSame('Alice Client', $data['reservations'][0]->full_name);
        $this->assertSame('alice', $data['search']);
        $this->assertSame(now()->addDays(3)->toDateString(), $data['dateFrom']);
    }

    public function test_admin_reservations_export_uses_filtered_results(): void
    {
        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Alice Client',
            'contact_number' => '09170000003',
            'email' => 'alice@example.com',
            'address' => '123 Alice St',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Alice Venue',
            'guest_count' => 80,
            'estimated_budget' => 20000,
            'status' => 'confirmed',
            'payment_status' => 'Fully Paid',
            'payment_type' => 'Full Payment',
            'amount_paid' => 20000,
            'balance' => 0,
            'reservation_code' => 'RES-FILT-EXPORT-001',
        ]);

        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Bob Client',
            'contact_number' => '09180000004',
            'email' => 'bob@example.com',
            'address' => '456 Bob St',
            'event_type' => 'Birthday',
            'event_date' => now()->addDays(12)->toDateString(),
            'event_time' => '17:00',
            'venue' => 'Bob Venue',
            'guest_count' => 50,
            'estimated_budget' => 12000,
            'status' => 'pending',
            'payment_status' => 'Unpaid',
            'payment_type' => 'Unpaid',
            'amount_paid' => 0,
            'balance' => 12000,
            'reservation_code' => 'RES-FILT-EXPORT-002',
        ]);

        $response = app(\App\Http\Controllers\AdminController::class)->exportReservationsCsv(new \Illuminate\Http\Request([
            'status' => 'confirmed',
            'search' => 'alice',
        ]));

        $csv = $response->getContent();

        $this->assertStringContainsString('RES-FILT-EXPORT-001', $csv);
        $this->assertStringNotContainsString('RES-FILT-EXPORT-002', $csv);
    }
}
