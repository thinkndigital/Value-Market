<?php

namespace Tests\Feature\Phase6;

use App\Http\Controllers\Seller\PosShiftController;
use App\Models\PosShift;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PosShiftControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): Seller
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);

        return Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
    }

    public function test_a_seller_can_open_and_close_their_own_shift(): void
    {
        $seller = $this->makeSeller();
        Auth::login(User::find($seller->user_id));

        $openResponse = app(PosShiftController::class)->open(new Request(['opening_cash' => 200]));
        $opened = json_decode($openResponse->getContent(), true);
        $this->assertFalse($opened['error']);

        $closeResponse = app(PosShiftController::class)->close(new Request(['closing_cash' => 200]), $opened['data']['id']);
        $closed = json_decode($closeResponse->getContent(), true);
        $this->assertFalse($closed['error']);
        $this->assertSame(PosShift::STATUS_CLOSED, $closed['data']['status']);
    }

    public function test_a_seller_cannot_close_another_sellers_shift(): void
    {
        $owner = $this->makeSeller();
        $stranger = $this->makeSeller();

        Auth::login(User::find($owner->user_id));
        $openResponse = app(PosShiftController::class)->open(new Request(['opening_cash' => 200]));
        $shiftId = json_decode($openResponse->getContent(), true)['data']['id'];

        Auth::login(User::find($stranger->user_id));
        $closeResponse = app(PosShiftController::class)->close(new Request(['closing_cash' => 999]), $shiftId);
        $data = json_decode($closeResponse->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(PosShift::STATUS_OPEN, PosShift::find($shiftId)->status);
    }
}
