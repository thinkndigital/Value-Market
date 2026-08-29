<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * The affiliate portal (docs/PHASE_7_AFFILIATE_ENGINE.md) is open to any authenticated user - a customer or
 * a seller can both be an affiliate - unlike admin/seller/delivery_boy login (Admin\UserController::authenticate())
 * which branches on role and rejects anyone outside its own three roles. Deliberately not folded into that
 * shared authenticate() method: adding an unrestricted branch there would let an affiliate login attempt
 * that guesses admin/seller credentials land in the same success path as those panels' own login, which is
 * a meaningfully different security posture worth keeping as its own explicit code path.
 */
class AffiliateAuthController extends Controller
{
    public function login()
    {
        return view('affiliate.login');
    }

    public function authenticate(Request $request)
    {
        $formFields = $request->validate([
            'mobile' => 'required',
            'password' => 'required',
        ]);

        if (!Auth::attempt($formFields)) {
            return response()->json(['errors' => ['mobile' => ['Invalid credentials']]], 422);
        }

        if (!Auth::user()->active) {
            Auth::logout();
            return response()->json(['errors' => ['status' => ['Your account is not active.']]], 422);
        }

        return response()->json(['message' => 'Login successful', 'location' => route('affiliate.dashboard')]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('affiliate.login');
    }
}
