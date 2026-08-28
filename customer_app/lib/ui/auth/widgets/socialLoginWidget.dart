import 'dart:io';

import 'package:value_market_customer/core/configs/appConfig.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/auth/cubits/authCubit.dart';
import 'package:value_market_customer/ui/auth/cubits/signUpCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';

import 'package:value_market_customer/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customRoundedButton.dart';

import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/utils/designConfig.dart';

import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class SocialLoginWidget extends StatelessWidget {
  const SocialLoginWidget({
    super.key,
    required bool isSignUpScreen,
  }) : _isSignUpScreen = isSignUpScreen;

  final bool _isSignUpScreen;

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<SignUpCubit, SignUpState>(
        listener: (context, state) async {
      if (state is SignUpSuccess) {
        if (state.userDetails.active == '0') {
          Utils.showSnackBar(
              context: context, message: deactivatedErrorMessageKey);
          return;
        }
        context.read<AuthCubit>().authenticateUser(
            userDetails: state.userDetails, token: state.token);
        context
            .read<UserDetailsCubit>()
            .emitUserSuccessState(state.userDetails.toJson(), state.token);
        await Utils.syncFavoritesToUser(context);
        FocusScope.of(context).unfocus();

        Utils.navigateToScreen(context, Routes.mainScreen, replaceAll: true);
      }
      if (state is SignUpFailure) {
        Utils.showSnackBar(context: context, message: state.errorMessage);
      }
    }, builder: (context, state) {
      return Platform.isAndroid
          ? buildSocialLoginButton(context, state, googleLoginType)
          : context
                          .read<SettingsAndLanguagesCubit>()
                          .getSettings()
                          .systemSettings!
                          .apple ==
                      1 &&
                  context
                          .read<SettingsAndLanguagesCubit>()
                          .getSettings()
                          .systemSettings!
                          .google !=
                      1
              ? buildSocialLoginButton(context, state, appleLoginType)
              : context
                              .read<SettingsAndLanguagesCubit>()
                              .getSettings()
                              .systemSettings!
                              .google ==
                          1 &&
                      context
                              .read<SettingsAndLanguagesCubit>()
                              .getSettings()
                              .systemSettings!
                              .apple !=
                          1
                  ? buildSocialLoginButton(context, state, googleLoginType)
                  : Row(
                      children: [
                        Flexible(
                            child: buildSocialLoginButton(
                                context, state, googleLoginType)),
                        DesignConfig.defaultWidthSizedBox,
                        Flexible(
                            child: buildSocialLoginButton(
                                context, state, appleLoginType)),
                      ],
                    );
    });
  }

  Widget buildSocialLoginButton(
      BuildContext context, SignUpState state, String loginType) {
    if ((loginType == googleLoginType &&
            context
                    .read<SettingsAndLanguagesCubit>()
                    .getSettings()
                    .systemSettings!
                    .google ==
                1) ||
        ((loginType == appleLoginType &&
            context
                    .read<SettingsAndLanguagesCubit>()
                    .getSettings()
                    .systemSettings!
                    .apple ==
                1))) {
      return CustomRoundedButton(
        widthPercentage: 1,
        buttonTitle: '',
        showBorder: true,
        borderColor: Theme.of(context).inputDecorationTheme.iconColor,
        backgroundColor: Theme.of(context).colorScheme.primaryContainer,
        onTap: state is SignUpProgress && state.loginType == loginType
            ? () {}
            : () {
                context.read<SignUpCubit>().signUpUser(loginType);
              },
        child: state is SignUpProgress && state.loginType == loginType
            ? CustomCircularProgressIndicator(
                indicatorColor: Theme.of(context).colorScheme.primary,
              )
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  Utils.setSvgImage(loginType == googleLoginType
                      ? 'google_logo'
                      : 'apple_logo'),
                  DesignConfig.defaultWidthSizedBox,
                  CustomTextContainer(
                    textKey: _isSignUpScreen
                        ? loginType == googleLoginType
                            ? signUpWithGoogleKey
                            : signUpWithAppleKey
                        : loginType == googleLoginType
                            ? signInWithGoogleKey
                            : signInWithAppleKey,
                    style: Theme.of(context).textTheme.bodyLarge!.copyWith(
                        color: Theme.of(context)
                            .colorScheme
                            .secondary
                            .withValues(alpha: 0.67)),
                  )
                ],
              ),
      );
    }
    return const SizedBox.shrink();
  }
}
