import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/features/auth/blocs/authCubit.dart';
import 'package:eshopplus_seller/features/home/screens/homeScreen.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getContactsCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getMessageCubit.dart';
import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/userDetailsCubit.dart';
import 'package:eshopplus_seller/features/notification/repositories/notificationRepository.dart';
import 'package:eshopplus_seller/features/orders/screens/orderScreen.dart';
import 'package:eshopplus_seller/features/product/screens/productScreen.dart';
import 'package:eshopplus_seller/features/profile/chat/screens/chatScreen.dart';
import 'package:eshopplus_seller/features/profile/profileScreen.dart';
import 'package:eshopplus_seller/commons/widgets/appUnderMaintenanceContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/utils/notificationUtility.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/flutter_svg.dart';

late GetMessageCubit getMessageCubit;
late GetContactsCubit getContactsCubit;
GlobalKey<MainScreenState>? mainScreenKey;

class MainScreen extends StatefulWidget {
  const MainScreen({Key? key}) : super(key: key);

  static Widget getRouteInstance() => MainScreen(key: mainScreenKey);
  @override
  MainScreenState createState() => MainScreenState();
}

ProductsCubit? regularProductsCubit = ProductsCubit();
ProductsCubit? comboProductsCubit = ProductsCubit();
ProductsCubit? stockManagementCubit = ProductsCubit();
DateTime? currentBackPressTime;

class MainScreenState extends State<MainScreen> with WidgetsBindingObserver {
  GlobalKey _homeKey = GlobalKey(),
      _ordersKey = GlobalKey(),
      _productsKey = GlobalKey();
  String channelName = '';
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    Future.delayed(Duration.zero).then((value) {
      getMessageCubit = context.read<GetMessageCubit>();
      getContactsCubit = context.read<GetContactsCubit>();
      if (context.read<AuthCubit>().state is Authenticated) {
        channelName =
            context.read<SettingsAndLanguagesCubit>().getPusherChannerName();
        NotificationUtility.setUpNotificationService(context);
        // callUserApi();
      }
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
  }

  changeCurrentIndex(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  int _selectedIndex = 0;
  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    if (pusherChannel != null) {
      pusherService.disconnectPusher(channelName);
      pusherChannel?.unsubscribe();
    }
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
              Utils.showSnackBar(message: pressBackAgainToExitKey);
              setState(() {});
            }
          }
        },
        child: context.read<SettingsAndLanguagesCubit>().appUnderMaintenance()
            ? const AppUnderMaintenanceContainer()
            : Scaffold(
                bottomNavigationBar: buildBottomBar,
                body: BlocListener<StoresCubit, StoresState>(
                    listener: (context, state) {
                  if (state is StoresFetchSuccess) {
                    setState(() {
                      resetAllStates();
                    });
                  }
                }, child: BlocBuilder<StoresCubit, StoresState>(
                        builder: (context, state) {
                  if (state is StoresFetchSuccess) {
                    return BlocConsumer<UserDetailsCubit, UserDetailsState>(
                      listener: (context, state) {
                        if (state is UserDetailsFetchSuccess) {
                          if (state.userDetails.active == '0' &&
                              context.read<AuthCubit>().state
                                  is Authenticated) {
                            context.read<AuthCubit>().signOut(context);
                            Utils.showSnackBar(
                                message: deactivatedErrorMessageKey);
                            Utils.navigateToScreen(context, Routes.loginScreen,
                                replaceAll: true);
                          } else {
                            NotificationUtility.initFirebaseState(context);
                            context.read<AuthCubit>().authenticateUser(
                                userDetails: state.userDetails,
                                token: state.token);
                          }
                        }
                        if (state is UserDetailsFetchFailure) {
                          Utils.showSnackBar(message: state.errorMessage);
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
                              HomeScreen(key: _homeKey),
                              OrderScreen(key: _ordersKey),
                              ProductScreen(key: _productsKey),
                              const ProfileScreen(),
                            ],
                          );
                        }
                        return ErrorScreen(
                            text: defaultErrorMessageKey,
                            onPressed: callUserApi);
                      },
                    );
                  }
                  if (state is StoresFetchFailure) {
                    return Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      mainAxisSize: MainAxisSize.max,
                      children: [
                        ErrorScreen(
                          text: state.errorMessage,
                          onPressed: () =>
                              context.read<StoresCubit>().fetchStores(),
                        ),
                        if (state.errorMessage == emptyAciveStoreErrorMsgKey)
                          ErrorScreen(
                              text: '',
                              buttonText: logoutKey,
                              onPressed: () {
                                context.read<AuthCubit>().signOut(context);
                                Utils.navigateToScreen(
                                    context, Routes.loginScreen,
                                    replaceAll: true);
                              }),
                      ],
                    );
                  }
                  return const SizedBox();
                }))),
      ),
    );
  }

  resetAllStates() {
    _homeKey = GlobalKey(); // Change the key to force a rebuild
    _ordersKey = GlobalKey(); // Change the key to force a rebuild
    _productsKey = GlobalKey(); // Change the key to force a rebuild
    //we have made this global cubit to access product cubits from other screens
    regularProductsCubit = ProductsCubit();
    comboProductsCubit = ProductsCubit();
    setState(() {});
  }

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  Widget get buildBottomBar => BlocBuilder<UserDetailsCubit, UserDetailsState>(
        builder: (context, state) {
          if (state is UserDetailsFetchSuccess) {
            return Container(
              height: bottomBarHeight + _getBottomPadding(),
              decoration: BoxDecoration(
                color:
                    Theme.of(context).bottomNavigationBarTheme.backgroundColor,
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
                    _buildNavItem(0, homeKey, 'home.svg', 'home_active.svg'),
                    _buildNavItem(
                        1, ordersKey, 'order.svg', 'order_active.svg'),
                    _buildNavItem(
                        2, productsKey, 'product.svg', 'product_active.svg'),
                    _buildNavItem(3, userKey, 'user.svg', 'user_active.svg'),
                  ],
                ),
              ),
            );
          }
          return const SizedBox.shrink();
        },
      );
  Widget _buildNavItem(
      int index, String title, String inactiveIcon, String activeIcon) {
    return Expanded(
      child: BlocBuilder<SettingsAndLanguagesCubit, SettingsAndLanguagesState>(
        builder: (context, state) {
          return GestureDetector(
            onTap: () {
              _onItemTapped(index);
            },
            child: Container(
              decoration:
                  BoxDecoration(border: Border.all(color: Colors.transparent)),
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
                          ? SvgPicture.asset(
                              Utils.getImagePath(activeIcon),
                              colorFilter: ColorFilter.mode(
                                  Theme.of(context).colorScheme.secondary,
                                  BlendMode.srcIn),
                            )
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
                            ? Theme.of(context).textTheme.labelMedium!.copyWith(
                                  color:
                                      Theme.of(context).colorScheme.secondary,
                                )
                            : Theme.of(context).textTheme.bodySmall!.copyWith(
                                  color:
                                      Theme.of(context).colorScheme.secondary,
                                ),
                      )
                    ],
                  )
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  double _getBottomPadding() {
    // Add extra padding for Android 15's transparent navigation bar
    return DesignConfig.getAndroid15BottomPadding(context);
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
