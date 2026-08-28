import 'package:eshopplus_seller/features/orders/parcel/parcelDetailScreen.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/addProductCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/attributeListCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/brandListCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/categoryListCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/countryListCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/getProductByTypeCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/mediaListCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/taxlistCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/repositories/attributeRepository.dart';
import 'package:eshopplus_seller/features/profile/addProduct/screens/addCategoryScreen.dart';
import 'package:eshopplus_seller/features/profile/addProduct/screens/addProductScreen.dart';
import 'package:eshopplus_seller/features/profile/addProduct/screens/addBrandScreen.dart';
import 'package:eshopplus_seller/features/profile/addProduct/widgets/helper/descSetupPage.dart';
import 'package:eshopplus_seller/features/profile/addProduct/widgets/helper/mediaListPage.dart';
import 'package:eshopplus_seller/features/profile/addSellerStore/addSellerStoreScreen.dart';
import 'package:eshopplus_seller/features/profile/deliverability/deliverabiltyScreen.dart';
import 'package:eshopplus_seller/features/profile/pickupLocation/screens/addPickupLocationScreen.dart';
import 'package:eshopplus_seller/features/profile/pickupLocation/screens/managePickupLocationScreen.dart';
import 'package:eshopplus_seller/features/profile/salesReport/blocs/salesListCubit.dart';
import 'package:eshopplus_seller/features/profile/salesReport/repositories/salesRepository.dart';
import 'package:eshopplus_seller/features/profile/salesReport/screens/salesReportScreen.dart';
import 'package:flutter/material.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

import 'package:get/get_navigation/src/routes/get_route.dart';
import '../../features/profile/chat/screens/contactListScreen.dart';
import '../../commons/blocs/productCubit.dart';
import '../../features/auth/screens/forgotPasswordScreen.dart';
import '../../features/auth/screens/loginScreen.dart';
import '../../features/auth/screens/resetPasswordScreen.dart';
import '../../features/home/screens/homeScreen.dart';
import '../../features/notification/screens/notificationScreen.dart';
import '../../features/mainScreen.dart';
import '../../features/orders/screens/orderDetailScreen.dart';
import '../../features/product/screens/productDetailsScreen.dart';
import '../../features/product/screens/productScreen.dart';
import '../../features/product/widgets/fullScreenImageScreen.dart';
import '../../features/profile/chat/screens/chatScreen.dart';
import '../../features/profile/faq/screens/faqScreen.dart';
import '../../features/profile/policyScreen.dart';
import '../../features/profile/settings/screens/changePasswordScreen.dart';
import '../../features/profile/settings/screens/deleteAccountScreen.dart';
import '../../features/profile/settings/screens/settingScreen.dart';
import '../../features/profile/termsAndPolicyScreen.dart';
import '../../features/profile/wallet/screens/walletScreen.dart';
import '../../features/splashScreen.dart';
import '../../commons/blocs/zoneListCubit.dart';
import '../../features/auth/blocs/zipcodeListCubit.dart';
import '../../features/auth/blocs/signUpCubit.dart';
import '../../features/orders/blocs/orderUpdateCubit.dart';
import '../../features/auth/screens/signupScreen.dart';

class Routes {
  static String splashScreen = "/splash";

  static String loginScreen = "/login";
  static String resetPasswordScreen = "/resetPassword";
  static String forgotPasswordScreen = "/forgotPassword";
  static String otpVerificationScreen = "/otpVerification";
  static String signupScreen = "/signup";
  static String homeScreen = "/home";
  static String mainScreen = "/";
  static String notificationScreen = "/notifications";
  static String settingScreen = "/settings";
  static String changePasswordScreen = "/changePassword";
  static String deleteAccountScreen = "/deleteAccount";
  static String termsAndPolicyScreen = "/termsAndPolicy";
  static String addProductScreen = "/addProduct";
  static String managePickupLocScreen = "/managePickupLoc";
  static String addPickupLocScreen = "/addPickupLoc";
  static String faqScreen = "/faq";
  static String walletScreen = "/wallet";
  static String salesReportScreen = "/salesReport";
  static String stockManagementScreen = "/stockManagement";
  static String productScreen = "/products";
  static String productDetailsScreen = "/productDetails";
  static String orderDetailsScreen = "/orderDetails";
  static String parcelDetailsScreen = "/parcelDetails";
  static String policyScreen = "/policy";
  static String fullScreenImageScreen = "/fullScreenImage";
  static String chatScreen = "/chat";
  static String contactListScreen = "/contactList";
  static String mediaListScreen = "/mediaListScreen";
  static String addSellerStoreScreen = "/addSellerStore";
  static String htmlEditorPage = "/htmlEditorPage";
  static String deliverabiltyScreen = "/deliverabiltyScreen";
  static String addCategoryScreen = "/addCategory";
  static String addBrandScreen = "/addBrand";

  static final List<GetPage> getPages = [
    GetPage(name: splashScreen, page: () => SplashScreen.getRouteInstance()),
    GetPage(name: loginScreen, page: () => LoginScreen.getRouteInstance()),
    GetPage(
        name: resetPasswordScreen,
        page: () => ResetPasswordScreen.getRouteInstance()),
    GetPage(
        name: forgotPasswordScreen,
        page: () => ForgotPasswordScreen.getRouteInstance()),
    GetPage(
        name: signupScreen,
        page: () => MultiBlocProvider(
              providers: [
                BlocProvider<SignUpCubit>(
                  create: (context) => SignUpCubit(),
                ),
                BlocProvider<ZipcodeListCubit>(
                  create: (context) => ZipcodeListCubit(),
                ),
                BlocProvider<ZoneListCubit>(
                  create: (context) => ZoneListCubit(),
                ),
              ],
              child: SignupScreen.getRouteInstance(),
            )),
    GetPage(name: homeScreen, page: () => HomeScreen.getRouteInstance()),
    GetPage(
        name: mainScreen,
        page: () {
          mainScreenKey = GlobalKey<MainScreenState>();
          return MainScreen.getRouteInstance();
        }),
    GetPage(
        name: notificationScreen,
        page: () => NotificationScreen.getRouteInstance()),
    GetPage(name: settingScreen, page: () => SettingScreen.getRouteInstance()),
    GetPage(name: productScreen, page: () => ProductScreen.getRouteInstance()),
    GetPage(
        name: changePasswordScreen,
        page: () => ChangePasswordScreen.getRouteInstance()),
    GetPage(
        name: deleteAccountScreen,
        page: () => DeleteAccountScreen.getRouteInstance()),
    GetPage(
        name: termsAndPolicyScreen,
        page: () => TermsAndPolicyScreen.getRouteInstance()),
    GetPage(
        name: addProductScreen,
        page: () => MultiBlocProvider(
              providers: [
                BlocProvider<AddProductCubit>(
                  create: (context) => AddProductCubit(),
                ),
                BlocProvider<ProductsCubit>(
                  create: (context) => ProductsCubit(),
                ),
                BlocProvider<GetProductByTypeCubit>(
                  create: (context) => GetProductByTypeCubit(),
                ),
                BlocProvider<CategoryListCubit>(
                  create: (context) => CategoryListCubit(),
                ),
                BlocProvider<BrandListCubit>(
                  create: (context) => BrandListCubit(),
                ),
                BlocProvider<CountryListCubit>(
                  create: (context) => CountryListCubit(),
                ),
                BlocProvider<TaxListCubit>(
                  create: (context) => TaxListCubit(),
                ),
                BlocProvider<ZoneListCubit>(
                  create: (context) => ZoneListCubit(),
                ),
                BlocProvider<AttributeListCubit>(
                  create: (context) =>
                      AttributeListCubit(AttributeRepository()),
                ),
                BlocProvider<MediaListCubit>(
                  create: (context) => MediaListCubit(),
                ),
              ],
              child: AddProductScreen.getRouteInstance(),
            )),
    GetPage(
        name: productDetailsScreen,
        page: () => ProductDetailsScreen.getRouteInstance()),
    GetPage(
        name: fullScreenImageScreen,
        page: () => FullScreenImage.getRouteInstance()),
    GetPage(
        name: managePickupLocScreen,
        page: () => ManagePickupLocationScreen.getRouteInstance()),
    GetPage(
        name: addPickupLocScreen,
        page: () => AddPickupLocationScreen.getRouteInstance()),
    GetPage(name: faqScreen, page: () => FaqScreen.getRouteInstance()),
    GetPage(name: walletScreen, page: () => WalletScreen.getRouteInstance()),
    GetPage(
        name: salesReportScreen,
        page: () => BlocProvider<SalesListCubit>(
              create: (context) => SalesListCubit(SalesRepository()),
              child: SalesReportScreen.getRouteInstance(),
            )),
    GetPage(
        name: orderDetailsScreen,
        page: () => BlocProvider<OrderUpdateCubit>(
              create: (context) => OrderUpdateCubit(),
              child: OrderDetailScreen.getRouteInstance(),
            )),
    GetPage(
      name: parcelDetailsScreen,
      page: () => ParcelDetailScreen.getRouteInstance(),
    ),
    GetPage(name: policyScreen, page: () => PolicyScreen.getRouteInstance()),
    GetPage(name: chatScreen, page: () => ChatScreen.getRouteInstance()),
    GetPage(
        name: contactListScreen,
        page: () => ContactListScreen.getRouteInstance()),
    GetPage(
        name: mediaListScreen, page: () => MediaListPage.getRouteInstance()),
    GetPage(
        name: addSellerStoreScreen,
        page: () => AddSellerStoreScreen.getRouteInstance()),
    GetPage(name: htmlEditorPage, page: () => DescSetupPage.getRouteInstance()),
    GetPage(
        name: deliverabiltyScreen,
        page: () => DeliverabiltyScreen.getRouteInstance()),
    GetPage(
        name: addCategoryScreen,
        page: () => AddCategoryScreen.getRouteInstance()),
    GetPage(
        name: addBrandScreen, page: () => AddBrandScreen.getRouteInstance()),
  ];
}
