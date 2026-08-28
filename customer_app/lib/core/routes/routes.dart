import 'package:value_market_customer/ui/auth/screens/createAccountScreen.dart';
import 'package:value_market_customer/ui/cart/screens/cartScreen.dart';
import 'package:value_market_customer/ui/cart/screens/orderConfirmScreen.dart';
import 'package:value_market_customer/ui/cart/screens/placeOrderScreen.dart';
import 'package:value_market_customer/ui/categoty/screens/categoryScreen.dart';
import 'package:value_market_customer/ui/categoty/screens/subCategoryScreen.dart';
import 'package:value_market_customer/ui/explore/screens/exploreScreen.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/allFaqListScreen.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/allCustomerImagesScreen.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/compareWithSimilarItemsContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/fullScreenImageScreen.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/ratingContainer.dart';
import 'package:value_market_customer/commons/seller/screens/sellerDetailScreen.dart';
import 'package:value_market_customer/ui/home/seller/allFeaturedSellerList.dart';
import 'package:value_market_customer/ui/notification/screens/notificationScreen.dart';
import 'package:value_market_customer/ui/onBoardingScreen.dart';
import 'package:value_market_customer/ui/explore/productDetails/productDetailsScreen.dart';
import 'package:value_market_customer/ui/explore/productFilters/screens/productFiltersScreen.dart';
import 'package:value_market_customer/ui/profile/address/screens/addNewAddressScreen.dart';
import 'package:value_market_customer/ui/profile/address/screens/myAddressScreen.dart';
import 'package:value_market_customer/ui/profile/customerSupport/screens/askQueryScreen.dart';
import 'package:value_market_customer/ui/profile/chat/screens/chatScreen.dart';
import 'package:value_market_customer/ui/profile/customerSupport/screens/customerSupportScreen.dart';
import 'package:value_market_customer/ui/profile/chat/screens/userListScreen.dart';
import 'package:value_market_customer/ui/profile/editProfileScreen.dart';
import 'package:value_market_customer/ui/favorites/screens/favoriteScreen.dart';
import 'package:value_market_customer/ui/profile/orders/screens/myOrderScreen.dart';
import 'package:value_market_customer/ui/profile/transaction/screens/addMoneyScreen.dart';
import 'package:value_market_customer/ui/profile/transaction/widgets/paypalWebviewScreen.dart';
import 'package:value_market_customer/ui/profile/transaction/widgets/paystackWebviewScreen.dart';
import 'package:value_market_customer/ui/search/screens/searchScreen.dart';
import 'package:value_market_customer/ui/splashScreen.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../ui/auth/screens/forgotPasswordScreen.dart';
import '../../ui/auth/screens/loginScreen.dart';
import '../../ui/auth/screens/otpVerificationScreen.dart';

import '../../ui/auth/screens/signupScreen.dart';
import '../../ui/mainScreen.dart';
import '../../ui/profile/address/widgets/mapScreen.dart.dart';
import '../../ui/profile/faq/screens/faqScreen.dart';
import '../../ui/profile/orders/screens/orderDetailScreen.dart';
import '../../ui/profile/policyScreen.dart';
import '../../ui/profile/promoCode/screens/promoCodeScreen.dart';
import '../../ui/profile/referAndEarnScreen.dart';
import '../../ui/profile/settings/changePasswordScreen.dart';
import '../../ui/profile/settings/deleteAccountScreen.dart';
import '../../ui/profile/settings/settingScreen.dart';
import '../../ui/profile/termsAndPolicyScreen.dart';
import '../../ui/profile/transaction/screens/transactionScreen.dart';
import '../../ui/profile/transaction/screens/walletScreen.dart';

class Routes {
  static String mainScreen = "/main";
  static String splashScreen = "/";
  static String authenticationScreen = "/authentication";
  static String onBoardingScreen = "/onBoarding";

  static String signupScreen = "/signup";
  static String loginScreen = "/login";
  static String forgotPasswordScreen = "/forgotPassword";
  static String otpVerificationScreen = "/otpVerification";
  static String createAccountScreen = "/createAccount";
  static String exploreScreen = "/explore";
  static String productFiltersScreen = "/productFilters";
  static String termsAndPolicyScreen = "/termsAndPolicy";
  static String settingScreen = "/settings";
  static String changePasswordScreen = "/changePassword";
  static String deleteAccountScreen = "/deleteAccount";
  static String walletScreen = "/wallet";
  static String policyScreen = "/policy";
  static String editProfileScreen = "/editProfile";
  static String faqScreen = "/faq";
  static String referAndEarnScreen = "/referAndEarn";
  static String transactionScreen = "/transaction";
  static String myOrderScreen = "/myOrder";
  static String myAddressScreen = "/myAddress";
  static String addNewAddressScreen = "/addNewAddress";
  static String mapScreen = "/map";
  static String promoCodeScreen = "/promoCode";
  static String orderDetailsScreen = "/orderDetails";
  static String productDetailsScreen = "/productDetails";
  static String favoriteScreen = "/favorite";
  static String addMoneyScreen = "/addMoney";
  static String customerSupportScreen = "/customerSupport";
  static String askQueryScreen = "/askQuery";
  static String userListScreen = "/userList";
  static String paypalWebviewScreen = "/paypalWebview";
  static String paystackWebviewScreen = "/paystackWebview";
  static String categoryScreen = "/category";
  static String subCategoryScreen = "/subCategory";
  static String notificationScreen = "/notification";
  static String allFeaturedSellerList = "/allFeaturedSellerList";
  static String sellerDetailScreen = "/sellerDetail";
  static String customerImagesScreen = "/customerImages";
  static String fullScreenImageScreen = "/fullScreenImage";
  static String allFaqListScreen = "/allFaqList";
  static String allReviewScreen = "/allReview";
  static String comparisonScreen = "/comparisonScreen";
  static String cartScreen = "/cart";
  static String searchScreen = "/search";
  static String placeOrderScreen = "/placeOrder";
  static String orderConfirmedScreen = "/orderConfirmed";
  static String chatScreen = "/chat";

  static String currentRoute = splashScreen;
  static String previousRoute = splashScreen;
  static final List<GetPage> getPages = [
    GetPage(
        name: mainScreen,
        page: () {
          mainScreenKey = GlobalKey<MainScreenState>();
          return MainScreen.getRouteInstance();
        }),
    GetPage(name: splashScreen, page: () => SplashScreen.getRouteInstance()),
    GetPage(
        name: onBoardingScreen,
        page: () => OnBoardingScreen.getRouteInstance()),
    GetPage(name: signupScreen, page: () => SignupScreen.getRouteInstance()),
    GetPage(name: loginScreen, page: () => LoginScreen.getRouteInstance()),
    GetPage(
        name: forgotPasswordScreen,
        page: () => ForgotPasswordScreen.getRouteInstance()),
    GetPage(
        name: otpVerificationScreen,
        page: () => OtpVerificationScreen.getRouteInstance()),
    GetPage(
        name: createAccountScreen,
        page: () => CreateAccountScreen.getRouteInstance()),
    GetPage(name: exploreScreen, page: () => ExploreScreen.getRouteInstance()),
    GetPage(
        name: productFiltersScreen,
        page: () => ProductFiltersScreen.getRouteInstance()),
    GetPage(
        name: termsAndPolicyScreen,
        page: () => TermsAndPolicyScreen.getRouteInstance()),
    GetPage(name: settingScreen, page: () => SettingScreen.getRouteInstance()),
    GetPage(
        name: changePasswordScreen,
        page: () => ChangePasswordScreen.getRouteInstance()),
    GetPage(
        name: deleteAccountScreen,
        page: () => DeleteAccountScreen.getRouteInstance()),
    GetPage(name: walletScreen, page: () => WalletScreen.getRouteInstance()),
    GetPage(name: policyScreen, page: () => PolicyScreen.getRouteInstance()),
    GetPage(
        name: editProfileScreen,
        page: () => EditProfileScreen.getRouteInstance()),
    GetPage(name: faqScreen, page: () => FaqScreen.getRouteInstance()),
    GetPage(
        name: referAndEarnScreen,
        page: () => ReferAndEarnScreen.getRouteInstance()),
    GetPage(
        name: transactionScreen,
        page: () => TransactionScreen.getRouteInstance()),
    GetPage(name: myOrderScreen, page: () => MyOrderScreen.getRouteInstance()),
    GetPage(
        name: addNewAddressScreen,
        page: () => AddNewAddressScreen.getRouteInstance()),
    GetPage(
        name: myAddressScreen,
        page: () => MyAddressScreen.getRouteInstance()), //
    GetPage(name: mapScreen, page: () => MapScreen.getRouteInstance()),
    GetPage(
        name: promoCodeScreen, page: () => PromoCodeScreen.getRouteInstance()),
    GetPage(
        name: orderDetailsScreen,
        page: () => OrderDetailScreen.getRouteInstance()),
    GetPage(
        name: productDetailsScreen,
        page: () => ProductDetailsScreen.getRouteInstance()),
    GetPage(
        name: favoriteScreen, page: () => FavoriteScreen.getRouteInstance()),
    GetPage(
        name: addMoneyScreen, page: () => AddMoneyScreen.getRouteInstance()),
    GetPage(
        name: customerSupportScreen,
        page: () => CustomerSupportScreen.getRouteInstance()),
    GetPage(
        name: askQueryScreen, page: () => AskQueryScreen.getRouteInstance()),
    GetPage(
        name: userListScreen, page: () => ContactListScreen.getRouteInstance()),
    GetPage(
        name: paypalWebviewScreen,
        page: () => PaypalWebviewScreen.getRouteInstance()),
    GetPage(
        name: paystackWebviewScreen,
        page: () => PaystackWebViewScreen.getRouteInstance()),
    GetPage(
        name: categoryScreen, page: () => CategoryScreen.getRouteInstance()),
    GetPage(
        name: subCategoryScreen,
        page: () => SubCategoryScreen.getRouteInstance()),
    GetPage(
        name: notificationScreen,
        page: () => NotificationScreen.getRouteInstance()),
    GetPage(
        name: allFeaturedSellerList,
        page: () => AllFeaturedSellerList.getRouteInstance()),
    GetPage(
        name: sellerDetailScreen,
        page: () => SellerDetailScreen.getRouteInstance()),
    GetPage(
        name: customerImagesScreen,
        page: () => AllCustomerImagesScreen.getRouteInstance()),
    GetPage(
        name: fullScreenImageScreen,
        page: () => FullScreenImage.getRouteInstance()),
    GetPage(
        name: allFaqListScreen,
        page: () => AllFaqListScreen.getRouteInstance()),
    GetPage(
        name: allReviewScreen, page: () => RatingContainer.getRouteInstance()),
    GetPage(
        name: comparisonScreen,
        page: () => ComparisonScreen.getRouteInstance()),
    GetPage(name: searchScreen, page: () => SearchScreen.getRouteInstance()),
    GetPage(name: cartScreen, page: () => CartScreen.getRouteInstance()),
    GetPage(
        name: placeOrderScreen,
        page: () => PlaceOrderScreen.getRouteInstance()),
    GetPage(
        name: orderConfirmedScreen,
        page: () => OrderConfirmScreen.getRouteInstance()),
    GetPage(name: chatScreen, page: () => ChatScreen.getRouteInstance()),
  ];
}
