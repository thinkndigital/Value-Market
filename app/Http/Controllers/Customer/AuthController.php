<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Customer web session auth, mirroring Admin\UserController::authenticate()'s exact pattern (web guard,
 * auth()->attempt() on mobile+password) - the storefront's own equivalent of that shared login endpoint,
 * scoped to the customer role instead of admin/seller/delivery_boy.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        if (auth('web')->check()) {
            return redirect()->route('home');
        }

        return view('customer.auth.login');
    }

    public function login(Request $request)
    {
        $formFields = $request->validate([
            'mobile' => 'required',
            'password' => 'required',
        ]);

        if (!auth('web')->attempt($formFields)) {
            return back()->withErrors(['mobile' => 'Invalid credentials.'])->withInput();
        }

        $user = auth('web')->user();
        if ((int) $user->role_id !== Role::CUSTOMER) {
            auth('web')->logout();
            return back()->withErrors(['mobile' => 'This account cannot log in here.'])->withInput();
        }

        $request->session()->regenerate();

        return redirect()->intended(session()->pull('redirect_to', route('home')));
    }

    public function showRegister()
    {
        if (auth('web')->check()) {
            return redirect()->route('home');
        }

        return view('customer.auth.register');
    }

    /**
     * Same validation shape as App\v1\ApiController::register_user() for consistency, then auto-logs-in
     * (the mobile API deliberately doesn't auto-login since it hands back a bearer token instead; a web
     * session flow has no equivalent reason not to).
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'mobile' => 'required|numeric|unique:users,mobile',
            'country_code' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $settings = json_decode(app(SettingService::class)->getSettings('system_settings', true) ?? '[]', true);
        $walletBalance = $settings['wallet_balance_amount'] ?? '';

        $user = User::create([
            'username' => $validated['name'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'country_code' => $validated['country_code'],
            'type' => 'phone',
            'role_id' => Role::CUSTOMER,
            'active' => 1,
        ]);

        if (!empty($settings['wallet_balance_status']) && $settings['wallet_balance_status'] == 1 && $walletBalance !== '') {
            app(WalletService::class)->updateWalletBalance('credit', $user->id, $walletBalance, 'Welcome Wallet Amount Credited for User ID : ' . $user->id);
        }

        auth('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        auth('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
