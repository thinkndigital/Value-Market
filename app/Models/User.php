<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;



class User extends Authenticatable implements HasMedia
{
    use InteractsWithMedia;

    // Investigated (see docs/SECURITY_AUDIT.md §6.3): both traits are required together, not independently
    // optional. Spatie's Permission mechanism (hasPermissionTo()/syncPermissions()) is genuinely live - it
    // gates ~180 routes across the admin panel (routes/admin_routes.php's `permissions:` middleware) - but
    // HasPermissions::hasPermissionViaRole() itself calls hasRole(), which only HasRoles defines; dropping
    // HasRoles here was tried and broke that live permission check (caught by the full test suite before
    // being shipped - see git history). Spatie's Role *assignment* side (assignRole(), Spatie's own Role
    // model) is confirmed genuinely unused by any code in this app, and not even usable as-is - Spatie's
    // Role model expects a `guard_name` column on its `roles` table, but that table name is already this
    // app's own legacy role_id table (App\Models\Role), which has no such column - but the HasRoles trait
    // itself must stay, since HasPermissions depends on methods it provides. role_id and Spatie permissions
    // coexist correctly as two different concerns, not a duplication to merge: role_id answers "who is this
    // user" (super_admin/admin/editor/seller/delivery_boy/customer), Spatie permissions answer "what is
    // this specific admin/editor account allowed to do."
    use HasApiTokens, HasFactory, Notifiable, HasPermissions, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */


    protected $fillable = [
        'username',
        'role_id',
        'active',
        'is_available',
        'password',
        'address',
        'mobile',
        'email',
        'latitude',
        'longitude',
        'image',
        'fcm_id',
        'front_licence_image',
        'back_licence_image',
        'status',
        'balance',
        'bonus_type',
        'bonus',
        'serviceable_zones',
        'disk',
        'city',
        'pincode',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 4): semantic role helpers, all null-safe by
     * construction - a user with role_id = NULL (a legitimate, nullable column) or a role_id pointing at a
     * deleted/nonexistent row simply isn't any of these, rather than the app crashing on `$user->role->name`
     * (the pre-existing bug fixed in AuthServiceProvider/RoleMiddleware/CheckPermissions - see Task 3 in
     * the same doc). These compare against the plain role_id column directly, not the role() relation, so
     * no query/eager-load is needed to use them.
     */
    public function isSuperAdmin(): bool
    {
        return (int) $this->role_id === Role::SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return (int) $this->role_id === Role::ADMIN;
    }

    public function isEditor(): bool
    {
        return (int) $this->role_id === Role::EDITOR;
    }

    public function isSeller(): bool
    {
        return (int) $this->role_id === Role::SELLER;
    }

    public function isDeliveryBoy(): bool
    {
        return (int) $this->role_id === Role::DELIVERY_BOY;
    }

    public function isCustomer(): bool
    {
        return (int) $this->role_id === Role::CUSTOMER;
    }

    /** True for any of the three admin-panel roles the 'role:super_admin,admin,editor' route group allows. */
    public function isPlatformStaff(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin() || $this->isEditor();
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function seller_data()
    {
        return $this->hasOne(Seller::class, 'user_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function registerMediaCollections(): void
    {
        $media_storage_settings = fetchDetails(StorageType::class, ['is_default' => 1], '*');
        $mediaStorageType = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->name : 'public';
        if ($mediaStorageType === 's3') {
            $this->addMediaCollection('user_image')->useDisk('s3');
        } else {
            $this->addMediaCollection('user_image')->useDisk('public');
        }
    }
    public function stores()
    {
        return $this->belongsToMany(Store::class, 'seller_store', 'user_id', 'store_id');
    }

    public function sellerStore()
    {
        return $this->hasOne(SellerStore::class, 'user_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'seller_id', 'id');
    }
    public function favoriteSellers()
    {
        return $this->hasMany(Favorite::class, 'user_id');
    }
    public function city()
    {
        return $this->belongsTo(City::class, 'city', 'id');
    }
    /**
     * Phase 8 (docs/PHASE_8_DELIVERY.md): order_items currently assigned to this user as a delivery boy -
     * used by DispatchService for load-balancing (fewest active deliveries wins), not a general-purpose
     * relation for every caller.
     */
    public function deliveryOrderItems()
    {
        return $this->hasMany(OrderItems::class, 'delivery_boy_id');
    }
    public function address()
    {
        return $this->hasMany(Address::class);
    }
}
