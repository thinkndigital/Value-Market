# Android 15 Navigation Bar Fix

## Problem
In Android 15, the system navigation bar is transparent by default, which can cause app content to be hidden behind it.

## Solutions

### Option 1: Individual Screen Wrapping (Current Approach)
Wrap each screen's Scaffold body with `SafeAreaWithBottomPadding`.

### Option 2: Route-Level Wrapping (Recommended for New Screens)
Use `ScreenWrapper` in your route definitions to automatically apply padding.

### Option 3: Global App-Level Solution (Advanced)
Apply padding globally through GetMaterialApp builder (use with caution).

## Implementation Options

### Option 1: Individual Screen Wrapping
**Best for:** Existing screens that need quick fixes

```dart
// Import
import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';

// Wrap Scaffold body
Scaffold(
  appBar: CustomAppbar(titleKey: 'Your Title'),
  body: SafeAreaWithBottomPadding(
    child: YourContent(),
  ),
)
```

### Option 2: Route-Level Wrapping (Recommended)
**Best for:** New screens and systematic approach

```dart
// In your routes file
GetPage(
  name: Routes.yourScreen,
  page: () => ScreenWrapper.withBottomPadding(
    child: YourScreen(),
  ),
),

// For screens with bottom navigation
GetPage(
  name: Routes.mainScreen,
  page: () => ScreenWrapper.withoutBottomPadding(
    child: MainScreen(),
  ),
),
```

### Option 3: Global Solution (Advanced)
**Best for:** Apps with consistent padding needs

```dart
// In main.dart - GetMaterialApp
builder: (context, child) {
  if (child != null) {
    return ScreenWrapper.withBottomPadding(child: child);
  }
  return const SizedBox.shrink();
},
```

## Available Widgets

### SafeAreaWithBottomPadding
- **Purpose:** Individual screen wrapping
- **Usage:** Wrap Scaffold body
- **Features:** Automatic Android detection, configurable padding

### ScreenWrapper
- **Purpose:** Route-level and global wrapping
- **Usage:** Wrap entire screens or use in routes
- **Features:** Factory constructors, conditional padding

## Screens Already Fixed
- ✅ `lib/ui/mainScreen.dart` - Main screen with bottom navigation
- ✅ `lib/ui/profile/transaction/screens/walletScreen.dart`
- ✅ `lib/ui/favorites/screens/favoriteScreen.dart`
- ✅ `lib/ui/profile/orders/screens/myOrderScreen.dart`
- ✅ `lib/ui/profile/transaction/screens/transactionScreen.dart`
- ✅ `lib/ui/explore/screens/exploreScreen.dart`

## Recommended Approach for New Screens

### For Screens Without Bottom Navigation:
```dart
// Option A: Wrap in route
GetPage(
  name: Routes.newScreen,
  page: () => ScreenWrapper.withBottomPadding(
    child: NewScreen(),
  ),
),

// Option B: Wrap in screen
Scaffold(
  appBar: CustomAppbar(titleKey: 'Title'),
  body: SafeAreaWithBottomPadding(
    child: YourContent(),
  ),
)
```

### For Screens With Bottom Navigation:
```dart
// No wrapping needed - bottom navigation handles padding
Scaffold(
  appBar: CustomAppbar(titleKey: 'Title'),
  body: YourContent(),
  bottomNavigationBar: YourBottomNav(),
)
```

## Testing Checklist
- [ ] Test on Android 15 device/emulator
- [ ] Scroll to bottom of screen
- [ ] Verify last content is visible
- [ ] Test on other Android versions
- [ ] Test on iOS (should be unaffected)

## Notes
- **Android-only:** Padding only applies on Android devices
- **Automatic detection:** Uses `MediaQuery.paddingOf(context).bottom`
- **Performance:** Minimal impact, only applies when needed
- **Maintainability:** Use consistent approach across your app

## Migration Guide

### For Existing Screens:
1. Choose your preferred approach (Individual vs Route-level)
2. Apply the fix to screens with content hidden behind navigation bar
3. Test thoroughly on Android 15

### For New Screens:
1. Use `ScreenWrapper.withBottomPadding()` in route definitions
2. No need to modify individual screen code
3. Consistent padding across all new screens

## Screens That Need the Fix
The following screens likely need this fix (screens without bottom navigation bars):

### Profile Screens:
- `lib/ui/profile/faq/screens/faqScreen.dart`
- `lib/ui/profile/termsAndPolicyScreen.dart`
- `lib/ui/profile/editProfileScreen.dart`
- `lib/ui/profile/orders/screens/orderDetailScreen.dart`
- `lib/ui/profile/address/screens/addNewAddressScreen.dart`
- `lib/ui/profile/address/screens/myAddressScreen.dart`
- `lib/ui/profile/policyScreen.dart`
- `lib/ui/profile/chat/screens/chatScreen.dart`
- `lib/ui/profile/chat/screens/userListScreen.dart`
- `lib/ui/profile/customerSupport/screens/customerSupportScreen.dart`
- `lib/ui/profile/profileScreen.dart`
- `lib/ui/profile/customerSupport/screens/askQueryScreen.dart`
- `lib/ui/profile/settings/deleteAccountScreen.dart`
- `lib/ui/profile/settings/settingScreen.dart`
- `lib/ui/profile/transaction/screens/addMoneyScreen.dart`
- `lib/ui/profile/settings/changePasswordScreen.dart`
- `lib/ui/profile/promoCode/screens/promoCodeScreen.dart`

### Other Screens:
- `lib/ui/cart/screens/cartScreen.dart`
- `lib/ui/search/screens/searchScreen.dart`
- `lib/ui/notification/screens/notificationScreen.dart`
- `lib/ui/auth/screens/loginScreen.dart`
- `lib/ui/auth/screens/signupScreen.dart`
- `lib/ui/auth/screens/createAccountScreen.dart`
- `lib/ui/auth/screens/otpVerificationScreen.dart`
- `lib/ui/auth/screens/forgotPasswordScreen.dart` 