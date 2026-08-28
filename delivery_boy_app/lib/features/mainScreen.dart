import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/core/routes/routes.dart';
import 'package:eshoppro_deliveryboy/features/auth/blocs/authCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/blocs/recentOrderCubit.dart';

import 'package:eshoppro_deliveryboy/commons/blocs/userDetailsCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/models/parcel.dart';
import 'package:eshoppro_deliveryboy/features/notification/repositories/notificationRepository.dart';
import 'package:eshoppro_deliveryboy/features/collections/screens/collectionsScreen.dart';
import 'package:eshoppro_deliveryboy/features/home/screens/homeScreen.dart';
import 'package:eshoppro_deliveryboy/features/orders/screens/orderScreen.dart';
import 'package:eshoppro_deliveryboy/features/profile/screens/profileScreen.dart';
import 'package:eshoppro_deliveryboy/features/wallet/screens/walletScreen.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/appUnderMaintenanceContainer.dart';
import 'package:eshoppro_deliveryboy/core/api/apiEndPoints.dart';

import 'package:eshoppro_deliveryboy/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/errorScreen.dart';
import 'package:eshoppro_deliveryboy/core/localization/labelKeys.dart';
import 'package:eshoppro_deliveryboy/utils/designConfig.dart';
import 'package:eshoppro_deliveryboy/utils/notificationUtility.dart';
import 'package:eshoppro_deliveryboy/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/svg.dart';

import '../commons/blocs/settingsAndLanguagesCubit.dart';

class MainScreen extends StatefulWidget {
  const MainScreen({Key? key}) : super(key: key);
  static GlobalKey<MainScreenState> mainScreenKey =
      GlobalKey<MainScreenState>();
  static Widget getRouteInstance() => MainScreen(key: MainScreen.mainScreenKey);
  @override
  MainScreenState createState() => MainScreenState();
}

class MainScreenState extends State<MainScreen> with WidgetsBindingObserver {
  GlobalKey<HomeScreenState>? _homeKey;
  GlobalKey<OrderScreenState>? _ordersKey;
  GlobalKey _walletKey = GlobalKey();
  GlobalKey _cashcollectionKey = GlobalKey();
  DateTime? currentBackPressTime;
  @override
  void initState() {
    super.initState();
    _homeKey = GlobalKey<HomeScreenState>();
    _ordersKey = GlobalKey<OrderScreenState>();
    WidgetsBinding.instance.addObserver(this);
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.dark);

    Future.delayed(Duration.zero).then((value) {
      callUserApi();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeDependencies();
    if (state == AppLifecycleState.resumed) {
      NotificationRepository().getBGNotifications().then((notifications) {
        for (var notification in notifications) {
          NotificationUtility.onReceiveNotification(notification, context);
        }

        if (notifications.isNotEmpty) {
          NotificationRepository().clearNotification();
        }
      });
    }
  }

  callUserApi() {
    context.read<UserDetailsCubit>().fetchUserDetails(params: {
      ApiURL.mobileApiKey: context.read<AuthCubit>().getUserMobile()
    });
    BlocProvider.of<RecentOrdersCubit>(context).loadRecentOrders();
  }

  changeCurrentIndex(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  updateScreen(int tabno, {Parcel? parcel}) {
    if (tabno == 0 && parcel != null) {
      _homeKey!.currentState!.updateOrderItem(parcel);
    } else if (tabno == 1 && parcel != null) {
      _ordersKey!.currentState!.updateOrderItem(parcel);
    }
  }

  int _selectedIndex = 0;
  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle(
        statusBarColor: Colors.white,
        systemNavigationBarColor: Colors.white,
        systemNavigationBarDividerColor: Colors.white,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: PopScope(
        canPop: _selectedIndex == 0 &&
            !(currentBackPressTime == null ||
                DateTime.now().difference(currentBackPressTime!) >
                    const Duration(seconds: 2)),
        onPopInvokedWithResult: (didPop, result) async {
          if (_selectedIndex != 0) {
            setState(() {
              _selectedIndex = 0;
            });
          } else {
            DateTime now = DateTime.now();

            if (currentBackPressTime == null ||
                now.difference(currentBackPressTime!) >
                    const Duration(seconds: 2)) {
              currentBackPressTime = now;
              Utils.showSnackBar(
                  message: 'Press back again to Exit', context: context);
              setState(() {});
            }
          }
        },
        child: context.read<SettingsAndLanguagesCubit>().appUnderMaintenance()
            ? const AppUnderMaintenanceContainer()
            : Scaffold(
                bottomNavigationBar: buildBottomBar,
                body: BlocBuilder<SettingsAndLanguagesCubit,
                    SettingsAndLanguagesState>(
                  builder: (context, state) {
                    return BlocConsumer<UserDetailsCubit, UserDetailsState>(
                      listener: (context, state) {
                        if (state is UserDetailsFetchSuccess) {
                          NotificationUtility.setUpNotificationService(context);
                          NotificationUtility.initFirebaseState(context);
                          if (state.userDetails.active == '0' &&
                              context.read<AuthCubit>().state
                                  is Authenticated) {
                            context.read<AuthCubit>().signOut();
                            Utils.showSnackBar(
                                context: context,
                                message: deactivatedErrorMessageKey);
                            Utils.navigateToScreen(context, Routes.loginScreen,
                                replaceAll: true);
                          } else {
                            context.read<AuthCubit>().authenticateUser(
                                userDetails: state.userDetails,
                                token: state.token);
                          }
                        }
                        if (state is UserDetailsFetchFailure) {
                          Utils.showSnackBar(
                              context: context, message: state.errorMessage);
                          //errror code 102 means User Not Registered so we  will redirect it to login screen
                          if (state.errorCode == 102 ||
                              state.errorCode == 401) {
                            Utils.navigateToScreen(context, Routes.loginScreen,
                                replaceAll: true);
                          }
                        }
                      },
                      builder: (context, state) {
                        if (state is UserDetailsFetchFailure) {
                          return ErrorScreen(
                              text: state.errorMessage, onPressed: callUserApi);
                        } else if (state is UserDetailsFetchInProgress) {
                          return CustomCircularProgressIndicator(
                            indicatorColor:
                                Theme.of(context).colorScheme.primary,
                          );
                        }
                        if (state is UserDetailsFetchSuccess) {
                          return IndexedStack(
                            index: _selectedIndex,
                            children: [
                              HomeScreen(
                                key: _homeKey,
                              ),
                              OrderScreen(key: _ordersKey),
                              WalletScreen(
                                key: _walletKey,
                              ),
                              CollectionScreen(key: _cashcollectionKey),
                              const ProfileScreen(),
                            ],
                          );
                        }
                        return ErrorScreen(
                            text: defaultErrorMessageKey,
                            onPressed: callUserApi);
                      },
                    );
                  },
                )),
      ),
    );
  }

  double _getBottomPadding() {
    // Add extra padding for Android 15's transparent navigation bar
    return DesignConfig.getAndroid15BottomPadding(context);
  }

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  Widget get buildBottomBar =>
      BlocBuilder<SettingsAndLanguagesCubit, SettingsAndLanguagesState>(
        builder: (context, state) {
          return BlocBuilder<UserDetailsCubit, UserDetailsState>(
            builder: (context, state) {
              if (state is UserDetailsFetchSuccess) {
                return Container(
                  height: bottomBarHeight + _getBottomPadding(),
                  decoration: BoxDecoration(
                    color: Theme.of(context)
                        .bottomNavigationBarTheme
                        .backgroundColor,
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x14201A1A),
                        blurRadius: 12,
                        offset: Offset(0, -2),
                        spreadRadius: 0,
                      )
                    ],
                  ),
                  child: Padding(
                    padding: EdgeInsetsDirectional.only(
                      start: 8,
                      end: 8.0,
                      bottom: _getBottomPadding(),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.max,
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: <Widget>[
                        _buildNavItem(
                            0, homeKey, 'home.svg', 'home_active.svg'),
                        _buildNavItem(1, myDeliveryKey, 'delivery.svg',
                            'delivery_active.svg'),
                        _buildNavItem(
                            2, walletKey, 'wallet.svg', 'wallet_active.svg'),
                        _buildNavItem(
                            3, cashKey, 'cash.svg', 'cash_active.svg'),
                        _buildNavItem(
                            4, userKey, 'user.svg', 'user_active.svg'),
                      ],
                    ),
                  ),
                );
              }
              return const SizedBox.shrink();
            },
          );
        },
      );
  Widget _buildNavItem(
      int index, String title, String inactiveIcon, String activeIcon) {
    return Expanded(
      child: GestureDetector(
        onTap: () {
          _onItemTapped(index);
        },
        child: Column(
          mainAxisSize: MainAxisSize.max,
          children: [
            AnimatedBar(isActive: _selectedIndex == index),
            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                const SizedBox(
                  height: 12,
                ),
                _selectedIndex == index
                    ? SvgPicture.asset(Utils.getImagePath(activeIcon))
                    : SvgPicture.asset(Utils.getImagePath(inactiveIcon)),
                const SizedBox(
                  height: 5,
                ),
                Text(
                  context
                      .read<SettingsAndLanguagesCubit>()
                      .getTranslatedValue(labelKey: title),
                  textAlign: TextAlign.center,
                  style: _selectedIndex == index
                      ? Theme.of(context).textTheme.labelMedium
                      : Theme.of(context).textTheme.bodySmall,
                )
              ],
            )
          ],
        ),
      ),
    );
  }
}

class AnimatedBar extends StatelessWidget {
  const AnimatedBar({
    super.key,
    required bool isActive,
  }) : _isActive = isActive;

  final bool _isActive;

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      height: 3,
      width: _isActive ? (MediaQuery.of(context).size.width - 160) / 4 : 0,
      margin: const EdgeInsets.only(bottom: 2),
      duration: const Duration(seconds: 1),
      curve: Curves.fastLinearToSlowEaseIn,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primary,
        borderRadius: BorderRadius.circular(100),
      ),
    );
  }
}
