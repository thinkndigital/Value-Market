import 'dart:async';

import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/main.dart';
import 'package:eshop_plus/ui/profile/address/blocs/addNewAddressCubit.dart';
import 'package:eshop_plus/ui/profile/address/blocs/getAddressCubit.dart';
import 'package:eshop_plus/ui/auth/cubits/authCubit.dart';
import 'package:eshop_plus/ui/auth/cubits/generateReferCodeCubit.dart';
import 'package:eshop_plus/ui/home/brand/brandsCubit.dart';
import 'package:eshop_plus/ui/cart/cubits/checkCartProductDelCubit.dart';
import 'package:eshop_plus/ui/cart/cubits/getUserCart.dart';
import 'package:eshop_plus/ui/cart/cubits/manageCartCubit.dart';
import 'package:eshop_plus/ui/cart/cubits/removeProductFromCartCubit.dart';
import 'package:eshop_plus/ui/home/categorySlider/categorySliderCubit.dart';
import 'package:eshop_plus/ui/favorites/blocs/addFavoriteCubit.dart';
import 'package:eshop_plus/ui/favorites/blocs/removeFavoriteCubit.dart';
import 'package:eshop_plus/ui/home/seller/featuredSellerCubit.dart';
import 'package:eshop_plus/ui/home/offer/offerCubit.dart';
import 'package:eshop_plus/ui/explore/cubits/comboProductsCubit.dart';
import 'package:eshop_plus/ui/home/mostSellingProduct/mostSellingProductCubit.dart';
import 'package:eshop_plus/commons/product/blocs/productsCubit.dart';
import 'package:eshop_plus/ui/home/featuredSection/featuredSectionCubit.dart';
import 'package:eshop_plus/ui/home/seller/bestSellerCubit.dart';
import 'package:eshop_plus/commons/seller/blocs/sellersCubit.dart';
import 'package:eshop_plus/ui/home/slider/sliderCubit.dart';
import 'package:eshop_plus/commons/blocs/updateUserCubit.dart';
import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/ui/profile/address/models/address.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/ui/profile/promoCode/models/promoCode.dart';
import 'package:eshop_plus/ui/notification/repositories/notificationRepository.dart';
import 'package:eshop_plus/ui/cart/screens/cartScreen.dart';
import 'package:eshop_plus/ui/categoty/screens/categoryScreen.dart';
import 'package:eshop_plus/ui/explore/screens/exploreScreen.dart';
import 'package:eshop_plus/ui/profile/chat/screens/chatScreen.dart';
import 'package:eshop_plus/ui/profile/profileScreen.dart';
import 'package:eshop_plus/commons/widgets/appUnderMaintenanceContainer.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';

import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:eshop_plus/utils/notificationUtility.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/svg.dart';
import 'package:hive/hive.dart';

import '../core/routes/routes.dart';

import '../commons/blocs/settingsAndLanguagesCubit.dart';
import '../commons/blocs/storesCubit.dart';
import '../core/localization/labelKeys.dart';
import '../utils/utils.dart';
import '../commons/widgets/customCircularProgressIndicator.dart';
import '../commons/widgets/error_screen.dart';
import 'home/homeScreen.dart';
import '../utils/designConfig.dart';

GlobalKey<MainScreenState>? mainScreenKey;

class MainScreen extends StatefulWidget {
  MainScreen({Key? key}) : super(key: key);

  static Widget getRouteInstance() => MultiBlocProvider(
        providers: [
          BlocProvider<CategorySliderCubit>(
              create: (_) => CategorySliderCubit()),
          BlocProvider<OfferCubit>(create: (context) => OfferCubit()),
          BlocProvider<SliderCubit>(create: (context) => SliderCubit()),
          BlocProvider<FeaturedSellerCubit>(
              create: (context) => FeaturedSellerCubit()),
          BlocProvider<MostSellingProductsCubit>(
              create: (context) => MostSellingProductsCubit()),

          BlocProvider<FeaturedSectionCubit>(
              create: (context) => FeaturedSectionCubit()),
          BlocProvider<BrandsCubit>(create: (context) => BrandsCubit()),

          ///[ProductsCubit]
          BlocProvider<ProductsCubit>(create: (context) => ProductsCubit()),

          ///[Combo ProductsCubit]
          BlocProvider<ComboProductsCubit>(
              create: (context) => ComboProductsCubit()),

          ///[SellersCubit]
          BlocProvider<SellersCubit>(create: (context) => SellersCubit()),

          ///[BestSellersCubit for best sellers]
          BlocProvider<BestSellersCubit>(
              create: (context) => BestSellersCubit()),
          BlocProvider<UpdateUserCubit>(create: (context) => UpdateUserCubit()),

          /// to generate referral code
          BlocProvider<GenerateReferCodeCubit>(
              create: (context) => GenerateReferCodeCubit()),
          //remove product from cart cubit
        ],
        child: MainScreen(key: mainScreenKey),
      );
  @override
  MainScreenState createState() => MainScreenState();
}

List<Product> comparableProducts = [];
String? zipcode;
Map<int, PromoCode?> updatedPromocodes = {};

class MainScreenState extends State<MainScreen> with WidgetsBindingObserver {
  int _selectedIndex = 0;
  bool _isLoading = false;
  String channelName = '';
  GlobalKey _homeKey = GlobalKey(),
      _categoryKey = GlobalKey(),
      _exploreKey = GlobalKey(),
      _cartKey = GlobalKey();
  DateTime? currentBackPressTime;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.dark);
    NotificationUtility()
        .setUpNotificationService(navigatorKey.currentContext ?? context);
    zipcode = null;

    _initializeApp();
  }

  void _initializeApp() async {
    if (!mounted) return;
    Future.delayed(Duration.zero, () {
      final authCubit = context.read<AuthCubit>();
      if (authCubit.state is Authenticated) {
        channelName =
            context.read<SettingsAndLanguagesCubit>().getPusherChannerName();
        context.read<GetAddressCubit>().getAddress();
      }

      final userDetailsCubit = context.read<UserDetailsCubit>();
      if (userDetailsCubit.state is UserDetailsFetchFailure &&
          !userDetailsCubit.isGuestUser()) {
        callUserApi();
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) async {
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
    context
        .read<UserDetailsCubit>()
        .fetchUserDetails(params: Utils.getParamsForVerifyUser(context));
  }

  changeCurrentIndex(int index) {
    setState(() {
      _selectedIndex = index;
      if (index == 3) {
        _cartKey = GlobalKey();
      }
    });
  }

  @override
  void dispose() {
    if (pusherChannel != null) {
      pusherService.disconnectPusher(channelName);
      pusherChannel?.unsubscribe();
    }
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // DeepLinkService().init(context);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        systemNavigationBarColor: Colors.transparent,
        systemNavigationBarDividerColor: Colors.transparent,
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
                    message: pressBackAgainToExitKey, context: context);
                setState(() {});
              }
            }
          },
          child: context.read<SettingsAndLanguagesCubit>().appUnderMaintenance()
              ? const AppUnderMaintenanceContainer()
              : Scaffold(
                  extendBody: true,
                  extendBodyBehindAppBar: true,
                  bottomNavigationBar: buildBottomBar,
                  body: MultiBlocListener(
                    listeners: [
                      BlocListener<AuthCubit, AuthState>(
                          listener: (context, state) async {
                        resetAllStates();
                        Hive.box(favoritesBoxKey).clear();
                        if (state is Authenticated) {
                          channelName = context
                              .read<SettingsAndLanguagesCubit>()
                              .getPusherChannerName();
                          context.read<GetAddressCubit>().getAddress();
                          NotificationUtility.initFirebaseState(context);
                        }
                      }),
                      BlocListener<GenerateReferCodeCubit,
                          GenerateReferCodeState>(
                        listener: (context, state) {
                          if (state is GenerateReferCodeFetchSuccess) {
                            context.read<UpdateUserCubit>().updateUser(params: {
                              ApiURL.userIdApiKey:
                                  context.read<UserDetailsCubit>().getUserId(),
                              ApiURL.referralCodeApiKey: state.referCode
                            });
                          }
                        },
                      ),
                      BlocListener<AddNewAddressCubit, AddNewAddressState>(
                        listener: (context, state) {
                          if (state is AddNewAddressFetchSuccess) {
                            List<Address> addresses = [];
                            if (context.read<GetAddressCubit>().state
                                is GetAddressFetchSuccess) {
                              addresses = (context.read<GetAddressCubit>().state
                                      as GetAddressFetchSuccess)
                                  .addresses;
                            }
                            // If the new or edited address is set as default
                            if (state.address.isDefault == 1) {
                              // Find the current default address and set its is_default to 0
                              final defaultIndex = addresses.indexWhere(
                                  (element) => element.isDefault == 1);
                              if (defaultIndex != -1) {
                                addresses[defaultIndex] =
                                    addresses[defaultIndex]
                                        .copyWith(isDefault: 0);
                              }
                            }
                            final index = addresses.indexWhere(
                                (element) => element.id == state.address.id);

                            if (index != -1) {
                              addresses[index] = state.address;
                            } else {
                              addresses.insert(0, state.address);
                            }
                            if (context
                                        .read<GetUserCartCubit>()
                                        .getCartDetail()
                                        .selectedAddress !=
                                    null &&
                                context
                                        .read<GetUserCartCubit>()
                                        .getCartDetail()
                                        .selectedAddress!
                                        .id ==
                                    state.address.id) {
                              context.read<GetUserCartCubit>().emitSuccessState(
                                  context
                                      .read<GetUserCartCubit>()
                                      .changeSelectedAddress(state.address));
                            }
                            context
                                .read<GetAddressCubit>()
                                .emitSuccessState(addresses);
                          }

                          if (state is AddNewAddressFetchFailure) {
                            Utils.showSnackBar(
                                message: state.errorMessage, context: context);
                          }
                        },
                      ),
                      BlocListener<RemoveFavoriteCubit, RemoveFavoriteState>(
                          listener: (context, state) {
                        if (state is RemoveFavoriteSuccess) {
                          Utils.showSnackBar(
                              context: context, message: state.successMessage);
                        }

                        if (state is RemoveFavoriteFailure) {
                          Utils.showSnackBar(
                              context: context, message: state.errorMessage);
                        }
                      }),
                      BlocListener<AddFavoriteCubit, AddFavoriteState>(
                          listener: (context, state) {
                        if (state is AddFavoriteSuccess) {
                          Utils.showSnackBar(
                              context: context, message: state.successMessage);
                        }
                        if (state is AddFavoriteFailure) {
                          Utils.showSnackBar(
                              context: context, message: state.errorMessage);
                        }
                      }),
                      BlocListener<ManageCartCubit, ManageCartState>(
                          listener: (context, state) {
                        if (state is ManageCartFetchSuccess &&
                            state.isAddedToSaveLater == false &&
                            state.changeQuantity == false) {
                          Utils.showSnackBar(
                              context: context,
                              message: itemAddedCartMessageKey);
                        }

                        if (state is ManageCartFetchFailure) {
                          Utils.showSnackBar(
                              context: context, message: state.errorMessage);
                        }
                      })
                    ],
                    child: BlocConsumer<StoresCubit, StoresState>(
                      listener: (context, state) {
                        if (state is StoresFetchSuccess) {
                          //when store change, need to CAll all apis of bottom tabs to fetch new data due to new store
                          //creating new global keys for each tab will call initState() of each tab

                          resetAllStates();
                        }
                      },
                      builder: (context, state) {
                        if (state is StoresFetchSuccess) {
                          return BlocConsumer<UserDetailsCubit,
                              UserDetailsState>(
                            listener: (context, state) async {
                              if (state is UserDetailsFetchSuccess) {
                                NotificationUtility().setUpNotificationService(
                                    navigatorKey.currentContext ?? context);
                                if (state.userDetails.active == '0' &&
                                    context.read<AuthCubit>().state
                                        is Authenticated) {
                                  context.read<AuthCubit>().signOut(context);
                                  Utils.showSnackBar(
                                      context: context,
                                      message: deactivatedErrorMessageKey);
                                  Utils.navigateToScreen(
                                      context, Routes.loginScreen,
                                      replaceAll: true);
                                } else {
                                  if (context
                                              .read<UserDetailsCubit>()
                                              .getUserName() !=
                                          null &&
                                      state.userDetails.referralCode != null &&
                                      state.userDetails.referralCode!.isEmpty) {
                                    context
                                        .read<GenerateReferCodeCubit>()
                                        .getGenerateReferCode();
                                  }
                                }
                              }
                              if (state is UserDetailsFetchFailure) {
                                Utils.showSnackBar(
                                    context: context,
                                    message: state.errorMessage);
                                //errror code 102 means User Not Registered so we  will redirect it to login screen
                                if (state.errorCode == 102) {
                                  Utils.navigateToScreen(
                                      context, Routes.loginScreen,
                                      replaceAll: true);
                                }
                              }
                            },
                            builder: (context, state) {
                              if (state is UserDetailsFetchFailure) {
                                return ErrorScreen(
                                  text: state.errorMessage,
                                  onPressed: () {
                                    callUserApi();
                                    setState(() {
                                      _isLoading = true;
                                    });
                                    Future.delayed(const Duration(seconds: 2),
                                        () {
                                      setState(() {
                                        _isLoading = false;
                                      });
                                    });
                                  },
                                  child: _isLoading
                                      ? const CircularProgressIndicator()
                                      : null,
                                );
                              } else if (state is UserDetailsFetchInProgress) {
                                return CustomCircularProgressIndicator(
                                  indicatorColor:
                                      Theme.of(context).colorScheme.primary,
                                );
                              }
                              return IndexedStack(
                                index: _selectedIndex,
                                children: [
                                  HomeScreen(key: _homeKey),
                                  CategoryScreen(key: _categoryKey),
                                  ExploreScreen(
                                      key: _exploreKey, isExploreScreen: true),
                                  MultiBlocProvider(
                                    providers: [
                                      BlocProvider(
                                        create: (context) =>
                                            RemoveFromCartCubit(),
                                      ),
                                      BlocProvider(
                                        create: (context) =>
                                            CheckCartProductDeliverabilityCubit(),
                                      ),
                                    ],
                                    child: CartScreen(key: _cartKey),
                                  ),
                                  const ProfileScreen()
                                ],
                              );
                            },
                          );
                        } else if (state is StoresFetchInProgress) {
                          return CustomCircularProgressIndicator(
                            indicatorColor:
                                Theme.of(context).colorScheme.primary,
                          );
                        }
                        return ErrorScreen(
                          text: state is StoresFetchFailure
                              ? state.errorMessage
                              : defaultErrorMessageKey,
                          onPressed: () =>
                              context.read<StoresCubit>().fetchStores(),
                          child: state is StoresFetchInProgress
                              ? CustomCircularProgressIndicator(
                                  indicatorColor:
                                      Theme.of(context).colorScheme.onPrimary,
                                )
                              : null,
                        );
                      },
                    ),
                  ))),
    );
  }

  resetAllStates() {
    // Change the key to force a rebuild
    _homeKey = GlobalKey();
    _exploreKey = GlobalKey();
    _cartKey = GlobalKey();
    _categoryKey = GlobalKey();

    setState(() {});
  }

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
      if (index == 3) {
        _cartKey = GlobalKey();
      }
    });
  }

  refreshProducts({bool onlyExplore = false}) {
    if (!onlyExplore) {
      _homeKey = GlobalKey();
    }
    _exploreKey = GlobalKey();
    setState(() {});
  }

  Widget get buildBottomBar => Container(
        height: bottomBarHeight + _getBottomPadding(),
        decoration: BoxDecoration(
          color: Theme.of(context).bottomNavigationBarTheme.backgroundColor,
          boxShadow: const [
            BoxShadow(
              color: Color(0x14201A1A), // semi-transparent
              blurRadius: 12,
              offset: Offset(0, -8), // Moves shadow upward
              spreadRadius: 0,
            ),
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
                  1, categoryKey, 'category.svg', 'category_active.svg'),
              _buildNavItem(2, exploreKey, 'explore.svg', 'explore_active.svg'),
              _buildNavItem(3, cartKey, 'cart.svg', 'cart_active.svg'),
              _buildNavItem(4, userKey, 'user.svg', 'user_active.svg'),
            ],
          ),
        ),
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
                      BlocBuilder<GetUserCartCubit, GetUserCartState>(
                        builder: (context, state) {
                          return Badge(
                            backgroundColor:
                                Theme.of(context).colorScheme.primary,
                            textColor: Theme.of(context).colorScheme.onPrimary,
                            isLabelVisible: index == 3 &&
                                    context
                                            .read<GetUserCartCubit>()
                                            .getCartProductLength() !=
                                        0 &&
                                    !context
                                        .read<UserDetailsCubit>()
                                        .isGuestUser()
                                ? true
                                : false,
                            label: context
                                            .read<GetUserCartCubit>()
                                            .getCartProductLength() !=
                                        0 &&
                                    !context
                                        .read<UserDetailsCubit>()
                                        .isGuestUser()
                                ? Text(context
                                    .read<GetUserCartCubit>()
                                    .getCartDetail()
                                    .cartProducts!
                                    .length
                                    .toString())
                                : null,
                            child: _selectedIndex == index
                                ? SvgPicture.asset(
                                    Utils.getImagePath(activeIcon),
                                    colorFilter: ColorFilter.mode(
                                        Theme.of(context).colorScheme.secondary,
                                        BlendMode.srcIn),
                                  )
                                : SvgPicture.asset(
                                    Utils.getImagePath(inactiveIcon)),
                          );
                        },
                      ),
                      const SizedBox(
                        height: 5,
                      ),
                      CustomTextContainer(
                        textKey: title,
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
      margin: const EdgeInsetsDirectional.only(bottom: 2),
      duration: const Duration(seconds: 1),
      curve: Curves.fastLinearToSlowEaseIn,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primary,
        borderRadius: BorderRadius.circular(100),
      ),
    );
  }
}
