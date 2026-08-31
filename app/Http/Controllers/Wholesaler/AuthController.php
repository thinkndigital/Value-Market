<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\Role;
use App\Models\User;
use App\Models\Wholesaler;
use App\Traits\HandlesValidation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Self-contained auth controller for the new Wholesaler panel, mirroring the blueprint every other panel
 * follows (Admin\UserController::login/authenticate/logout, seller_login/seller_register/sellerStore) -
 * a dedicated `web` guard session, `role:wholesaler` gating everything past login (routes/wholesaler_routes.
 * php). Kept in its own controller rather than added to Admin\UserController (already large and heavily
 * depended on by every other panel's login) so this new module can't regress existing login flows.
 */
class AuthController extends Controller
{
    use HandlesValidation;

    public function login()
    {
        return view('wholesaler.pages.forms.login');
    }

    public function register()
    {
        return view('wholesaler.pages.forms.register');
    }

    public function authenticate(Request $request)
    {
        $formFields = $request->validate([
            'mobile' => 'required',
            'password' => 'required',
        ]);

        if (!auth()->attempt($formFields)) {
            return response()->json(['errors' => ['mobile' => ['Invalid credentials']]], 422);
        }

        $user = User::with('role')->where('active', 1)->find(Auth::id());

        if (!$user || !$user->role || $user->role->name !== 'wholesaler') {
            Auth::logout();
            return response()->json(['errors' => ['role' => ['You do not have access to this panel']]], 422);
        }

        $wholesaler = Wholesaler::where('user_id', $user->id)->first();
        if (!$wholesaler || (int) $wholesaler->status !== 1) {
            Auth::logout();
            return response()->json(['errors' => ['status' => ['Your wholesaler account is not active. Please contact the platform admin.']]], 422);
        }

        return response()->json(['message' => 'Login successful', 'location' => route('wholesaler.home')]);
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'mobile' => 'required|numeric|unique:users,mobile',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|same:password',
            'business_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $user = User::create([
            'username' => $request->name,
            'mobile' => $request->mobile,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'type' => 'phone',
            'role_id' => Role::WHOLESALER,
            'active' => 1,
            'disk' => 'public',
        ]);

        Wholesaler::create([
            'user_id' => $user->id,
            'business_name' => $request->business_name,
            'description' => $request->description,
            'address' => $request->address,
            'status' => 1,
            'disk' => 'public',
        ]);

        return response()->json([
            'message' => labels('wholesaler_labels.registered_successfully', 'Registered successfully! You can now log in.'),
            'location' => route('wholesaler.login'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/wholesaler/login')->withHeaders([
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
