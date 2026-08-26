<?php

namespace App\Http\Requests\Pos;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Phase 1 architecture convention (docs/PHASE_1_ARCHITECTURE.md): the first real FormRequest in this
 * codebase, replacing the manual `if (!$request->has(...))` checks at the top of
 * Seller\PosController::place_order(). Same validation rules as before - this only centralizes them.
 *
 * IMPORTANT: every existing endpoint in this app returns `{"error": true, "message": "..."}` on failure,
 * not Laravel's default 422 `{"message": "...", "errors": {...}}` shape. failedValidation() below
 * overrides FormRequest's default behavior specifically to preserve that existing response contract - a
 * FormRequest that used Laravel's default failure response here would be a breaking API change for every
 * client (web admin, seller app) calling this endpoint, not a refactor.
 */
class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level middleware already gates who can reach this endpoint; this FormRequest is about
        // input shape, not authorization.
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'json'],
            'payment_method' => ['required', 'string'],
            'payment_method_name' => ['required_if:payment_method,other', 'string'],
        ];
    }

    /**
     * Preserve the app-wide `{error, message}` response shape instead of Laravel's default 422 body.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        $firstMessage = $validator->errors()->first();

        throw new HttpResponseException(response()->json([
            'error' => true,
            'message' => $firstMessage ?: 'Invalid request.',
        ]));
    }
}
