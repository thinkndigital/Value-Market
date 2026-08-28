import 'package:app_links/app_links.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/core/theme/colors.dart';
import 'package:eshop_plus/ui/auth/cubits/authCubit.dart';

import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/commons/models/settings.dart';
import 'package:eshop_plus/commons/repositories/settingsRepository.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';
import 'package:eshop_plus/utils/deepLinkHandler.dart';

import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/svg.dart';
import '../commons/blocs/userDetailsCubit.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();

  static Widget getRouteInstance() => const SplashScreen();
}

class _SplashScreenState extends State<SplashScreen>
    with TickerProviderStateMixin {
  late final AnimationController _logoController;
  late final Animation<double> _logoAnimation;

  @override
  void initState() {
    super.initState();

    _logoController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
    _logoAnimation = CurvedAnimation(
      parent: _logoController,
      curve: Curves.easeIn,
    );
    _logoController.forward();
    callApi();
  }

  @override
  void dispose() {
    _logoController.dispose();
    SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      systemNavigationBarColor: Colors.transparent,
      systemNavigationBarDividerColor: Colors.transparent,
      systemNavigationBarIconBrightness: Brightness.dark,
    ));
    super.dispose();
  }

  void navigateToNextScreen() async {
    Settings settings = context.read<SettingsAndLanguagesCubit>().getSettings();
    Uri? pendingDeepLink = await AppLinks().getLatestLink();
    if (pendingDeepLink != null) {
      Utils.navigateToScreen(
        context,
        Routes.mainScreen,
      );
      DeepLinkHandler().handleDeepLink(pendingDeepLink, context);
    } else {
      if (context.read<AuthCubit>().state is Unauthenticated) {
        if (context
            .read<SettingsAndLanguagesCubit>()
            .getshowOnBoardingScreen()) {
          ///[If there is no image or video added by admin then do not navigate to onborading screen]
          if ((settings.systemSettings?.showVideosInOnBoardingScreen() ??
              false)) {
            if ((settings.systemSettings?.onBoardingVideo?.isEmpty ?? false)) {
              navigateToLoginScreen(context);
            } else {
              Utils.navigateToScreen(context, Routes.onBoardingScreen,
                  replaceAll: true);
            }
          } else {
            if ((settings.systemSettings?.onBoardingImage?.isEmpty ?? false)) {
              navigateToLoginScreen(context);
            } else {
              Utils.navigateToScreen(context, Routes.onBoardingScreen,
                  replaceAll: true);
            }
          }
        } else {
          navigateToLoginScreen(context);
        }
      } else {
        Future.delayed(Duration(seconds: 2), () {
          Utils.navigateToScreen(context, Routes.mainScreen, replaceAll: true);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle(
        statusBarColor: primaryColor,
        systemNavigationBarColor: primaryColor,
      ),
      child: Scaffold(
        backgroundColor: primaryColor,
        body:
            BlocConsumer<SettingsAndLanguagesCubit, SettingsAndLanguagesState>(
                listener: (context, state) {
          if (state is SettingsAndLanguagesFetchSuccess) {
            navigateToNextScreen();
          } else if (state is SettingsAndLanguagesFetchFailure) {
            Utils.showSnackBar(message: state.errorMessage, context: context);
          }
        }, builder: (context, state) {
          if (state is SettingsAndLanguagesFetchFailure) {
            return ErrorScreen(
              text: state.errorMessage,
              onPressed: () => context
                  .read<SettingsAndLanguagesCubit>()
                  .fetchSettingsAndLanguages(),
              child: state is SettingsAndLanguagesFetchInProgress
                  ? CustomCircularProgressIndicator(
                      indicatorColor: Theme.of(context).colorScheme.onPrimary,
                    )
                  : null,
            );
          }

          return Center(
            child: AnimatedBuilder(
              animation: _logoAnimation,
              builder: (context, child) {
                return Opacity(
                  opacity: _logoAnimation.value,
                  child: Transform.scale(
                    scale: _logoAnimation.value,
                    child: child,
                  ),
                );
              },
              child: SvgPicture.asset(
                Utils.getBrandingImagePath(
                  'app_logo.svg',
                ),
                width: 200,
                height: 200,
              ),
            ),
          );
        }),
      ),
    );
  }

  void callApi() {
    Future.delayed(Duration.zero, () async {
      await context
          .read<SettingsAndLanguagesCubit>()
          .fetchSettingsAndLanguages();
      context.read<StoresCubit>().fetchStores();
      if (context.read<AuthCubit>().state is Authenticated) {
        context
            .read<UserDetailsCubit>()
            .fetchUserDetails(params: Utils.getParamsForVerifyUser(context));
      } else {
        context.read<UserDetailsCubit>().resetUserDetailsState();
      }
    });
  }

  static void navigateToLoginScreen(BuildContext context) {
    DeepLinkHandler().init(context);
    //if guest user open app first time , it will redirect to login screen
    //if user is logged in and open app second time or further then it will redirect to main screen

    if (SettingsRepository().getFirstTimeUser()) {
      Utils.navigateToScreen(context, Routes.loginScreen, replaceAll: true);
    } else {
      context.read<AuthCubit>().unAuthenticateUser();
      Utils.navigateToScreen(context, Routes.mainScreen, replaceAll: true);
    }
  }
}
