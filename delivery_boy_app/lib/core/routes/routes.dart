import 'package:eshoppro_deliveryboy/features/auth/blocs/zoneListCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/blocs/orderCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/blocs/orderUpdateCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/blocs/recentOrderCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/repositories/orderRepository.dart';
import 'package:eshoppro_deliveryboy/features/auth/screens/signupScreen.dart';
import 'package:eshoppro_deliveryboy/features/notification/screens/notificationScreen.dart';
import 'package:eshoppro_deliveryboy/features/mainScreen.dart';
import 'package:eshoppro_deliveryboy/features/orders/screens/orderDetailScreen.dart';
import 'package:eshoppro_deliveryboy/features/profile/screens/policyScreen.dart';
import 'package:eshoppro_deliveryboy/features/profile/screens/settings/changePasswordScreen.dart';
import 'package:eshoppro_deliveryboy/features/profile/screens/settings/deleteAccountScreen.dart';
import 'package:eshoppro_deliveryboy/features/profile/screens/settings/settingScreen.dart';
import 'package:eshoppro_deliveryboy/features/profile/screens/termsAndPolicyScreen.dart';
import 'package:eshoppro_deliveryboy/features/splashScreen.dart';
import 'package:eshoppro_deliveryboy/features/wallet/screens/walletScreen.dart';

import '../../features/auth/screens/forgotPasswordScreen.dart';
import '../../features/auth/screens/loginScreen.dart';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import '../../features/auth/blocs/signUpCubit.dart';

class Routes {
  static String splashScreen = "/splash";

  static String loginScreen = "/login";
  static String forgotPasswordScreen = "/forgotPassword";
  static String signupScreen = "/signup";
  static String homeScreen = "/home";
  static String mainScreen = "/";
  static String notificationScreen = "/notifications";
  static String settingScreen = "/settings";
  static String changePasswordScreen = "/changePassword";
  static String deleteAccountScreen = "/deleteAccount";
  static String termsAndPolicyScreen = "/termsAndPolicy";
  static String policyScreen = "/policy";
  static String orderDetailsScreen = "/orderDetails";
  static String walletScreen = "/wallet";

  static final List<GetPage> getPages = [
    GetPage(name: splashScreen, page: () => SplashScreen.getRouteInstance()),
    GetPage(name: loginScreen, page: () => LoginScreen.getRouteInstance()),
    GetPage(
        name: forgotPasswordScreen,
        page: () => ForgotPasswordScreen.getRouteInstance()),
    GetPage(
      name: mainScreen,
      page: () => MultiBlocProvider(
        providers: [
          BlocProvider<OrdersCubit>(
            create: (context) => OrdersCubit(OrderRepository()),
          ),
          BlocProvider<RecentOrdersCubit>(
            create: (context) => RecentOrdersCubit(OrderRepository()),
          ),
        ],
        child: MainScreen.getRouteInstance(),
      ),
    ),
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
        name: notificationScreen,
        page: () => NotificationScreen.getRouteInstance()),
    GetPage(name: settingScreen, page: () => SettingScreen.getRouteInstance()),
    GetPage(name: policyScreen, page: () => PolicyScreen.getRouteInstance()),
    GetPage(
        name: orderDetailsScreen,
        page: () => BlocProvider<OrderUpdateCubit>(
              create: (context) => OrderUpdateCubit(),
              child: OrderDetailScreen.getRouteInstance(),
            )),
    GetPage(name: walletScreen, page: () => WalletScreen.getRouteInstance()),
    GetPage(
        name: signupScreen,
        page: () => MultiBlocProvider(
              providers: [
                BlocProvider<SignUpCubit>(
                  create: (context) => SignUpCubit(),
                ),
                BlocProvider<ZoneListCubit>(
                  create: (context) => ZoneListCubit(),
                ),
              ],
              child: SignupScreen.getRouteInstance(),
            )),
  ];
}
