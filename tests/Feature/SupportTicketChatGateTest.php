<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\App\v1\ApiController;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.11, "Support Ticket chat enabled after Admin reply"): confirmed
 * genuinely missing - App\v1\ApiController::send_message() (the customer's own chat-send endpoint) had no
 * gate at all; a customer could send unlimited follow-up messages the instant a ticket was created, before
 * any admin had looked at it. The ticket's initial `description` field is not a TicketMessage row, so
 * "an admin has replied" is checked as "a TicketMessage with user_type='admin' exists for this ticket".
 *
 * Implementing this surfaced a second, independent bug in the same method: `user_type` was read straight
 * from client request input and written verbatim into ticket_messages.user_type - any authenticated
 * customer could pass user_type=admin and have their own message stored (and rendered) as an official admin
 * reply, which would also have let them trivially bypass the gate above for themselves. Fixed by forcing
 * any client-claimed "admin" user_type down to a real customer value before it ever reaches the gate check
 * or the insert.
 */
class SupportTicketChatGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    private function makeTicket(User $customer): Ticket
    {
        $ticketType = TicketType::forceCreate(['title' => 'General']);

        return Ticket::forceCreate([
            'ticket_type_id' => $ticketType->id, 'user_id' => $customer->id,
            'subject' => 'Help needed', 'email' => 'customer@example.com',
            'description' => 'Something is wrong.', 'status' => 1,
        ]);
    }

    public function test_a_customer_cannot_send_a_chat_message_before_any_admin_has_replied(): void
    {
        $customer = $this->makeCustomer();
        $ticket = $this->makeTicket($customer);
        Auth::login($customer);

        $response = app(ApiController::class)->send_message(
            new Request(['user_type' => 'user', 'ticket_id' => $ticket->id, 'message' => 'Are you there?']),
            app(TicketController::class)
        );
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['error']);
        $this->assertSame(0, TicketMessage::where('ticket_id', $ticket->id)->count());
    }

    public function test_a_customer_can_send_a_chat_message_once_an_admin_has_replied(): void
    {
        $customer = $this->makeCustomer();
        $ticket = $this->makeTicket($customer);
        TicketMessage::forceCreate([
            'user_type' => 'admin', 'user_id' => 1, 'ticket_id' => $ticket->id,
            'message' => 'How can we help?', 'disk' => 'public',
        ]);
        Auth::login($customer);

        $response = app(ApiController::class)->send_message(
            new Request(['user_type' => 'user', 'ticket_id' => $ticket->id, 'message' => 'Thanks for replying!']),
            app(TicketController::class)
        );
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error']);
        $this->assertSame(1, TicketMessage::where('ticket_id', $ticket->id)->where('user_type', 'user')->count());
    }

    public function test_a_customer_cannot_spoof_their_own_message_as_an_admin_reply(): void
    {
        $customer = $this->makeCustomer();
        $ticket = $this->makeTicket($customer);
        Auth::login($customer);

        // Even with the gate open (an admin already replied), the customer must never be able to claim
        // user_type=admin for their own message.
        TicketMessage::forceCreate([
            'user_type' => 'admin', 'user_id' => 1, 'ticket_id' => $ticket->id,
            'message' => 'Hello', 'disk' => 'public',
        ]);

        app(ApiController::class)->send_message(
            new Request(['user_type' => 'admin', 'ticket_id' => $ticket->id, 'message' => 'I am totally an admin']),
            app(TicketController::class)
        );

        $spoofed = TicketMessage::where('ticket_id', $ticket->id)
            ->where('message', 'I am totally an admin')
            ->first();
        $this->assertNotNull($spoofed);
        $this->assertNotSame('admin', $spoofed->user_type, "A customer's message must never be recorded with user_type='admin'.");
    }
}
