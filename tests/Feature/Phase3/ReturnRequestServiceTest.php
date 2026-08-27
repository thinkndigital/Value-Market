<?php

namespace Tests\Feature\Phase3;

use App\Models\ReturnRequest;
use App\Services\ReturnRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): ReturnRequestService consolidates the transition-guard chain
 * that used to be duplicated near-verbatim between Admin\ReturnRequestController::update() and
 * Seller\ReturnRequestController::update(). This locks in behavior-preserving coverage of every guard,
 * including the two ("can't revert to pending") that only Seller's copy used to enforce - both callers now
 * get all six.
 */
class ReturnRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeReturnRequest(int $status): ReturnRequest
    {
        return ReturnRequest::forceCreate([
            'user_id' => 1,
            'product_id' => 1,
            'product_variant_id' => 1,
            'order_id' => 1,
            'order_item_id' => 1,
            'status' => $status,
        ]);
    }

    public function test_returned_to_returned_is_blocked(): void
    {
        $rr = $this->makeReturnRequest(ReturnRequest::STATUS_RETURNED);
        $error = app(ReturnRequestService::class)->guardTransition($rr, ReturnRequest::STATUS_RETURNED);
        $this->assertSame('This Item Is Already Returned!', $error);
    }

    public function test_approved_to_approved_is_blocked(): void
    {
        $rr = $this->makeReturnRequest(ReturnRequest::STATUS_APPROVED);
        $error = app(ReturnRequestService::class)->guardTransition($rr, ReturnRequest::STATUS_APPROVED);
        $this->assertSame('This Item Is Already Approved!', $error);
    }

    public function test_rejected_to_rejected_is_blocked(): void
    {
        $rr = $this->makeReturnRequest(ReturnRequest::STATUS_REJECTED);
        $error = app(ReturnRequestService::class)->guardTransition($rr, ReturnRequest::STATUS_REJECTED);
        $this->assertSame('This Item Is Already Rejected!', $error);
    }

    public function test_rejected_to_approved_is_blocked(): void
    {
        $rr = $this->makeReturnRequest(ReturnRequest::STATUS_REJECTED);
        $error = app(ReturnRequestService::class)->guardTransition($rr, ReturnRequest::STATUS_APPROVED);
        $this->assertSame('You can not approve rejected return request!', $error);
    }

    public function test_rejected_to_pending_is_blocked(): void
    {
        $rr = $this->makeReturnRequest(ReturnRequest::STATUS_REJECTED);
        $error = app(ReturnRequestService::class)->guardTransition($rr, ReturnRequest::STATUS_PENDING);
        $this->assertNotNull($error);
    }

    public function test_approved_to_pending_is_blocked(): void
    {
        $rr = $this->makeReturnRequest(ReturnRequest::STATUS_APPROVED);
        $error = app(ReturnRequestService::class)->guardTransition($rr, ReturnRequest::STATUS_PENDING);
        $this->assertNotNull($error);
    }

    public function test_pending_to_approved_is_allowed(): void
    {
        $rr = $this->makeReturnRequest(ReturnRequest::STATUS_PENDING);
        $error = app(ReturnRequestService::class)->guardTransition($rr, ReturnRequest::STATUS_APPROVED);
        $this->assertNull($error);
    }

    public function test_pending_to_rejected_is_allowed(): void
    {
        $rr = $this->makeReturnRequest(ReturnRequest::STATUS_PENDING);
        $error = app(ReturnRequestService::class)->guardTransition($rr, ReturnRequest::STATUS_REJECTED);
        $this->assertNull($error);
    }
}
