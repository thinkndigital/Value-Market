import 'package:eshopplus_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:eshopplus_seller/commons/widgets/showHidePasswordButton.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/features/auth/blocs/authCubit.dart';
import 'package:eshopplus_seller/features/profile/settings/blocs/deleteAccountCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customRoundedButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../utils/utils.dart';

class DeleteAccountScreen extends StatelessWidget {
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => DeleteAccountCubit(),
        child: const DeleteAccountScreen(),
      );
  const DeleteAccountScreen({Key? key}) : super(key: key);
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(
        titleKey: deleteAccountKey,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
        child: Column(
          children: <Widget>[
            CustomTextContainer(
              textKey: deleteAccntWarning1Key,
              style: Theme.of(context).textTheme.bodyLarge!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.8)),
            ),
            const SizedBox(
              height: 8,
            ),
            CustomTextContainer(
              textKey: deleteAccntWarning2Key,
              style: Theme.of(context).textTheme.bodyLarge!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.8)),
            ),
            DesignConfig.defaultHeightSizedBox,
            buildDeleteButton(),
            const SizedBox(
              height: 8,
            ),
            CustomTextButton(
                buttonTextKey: backToSettingsKey,
                textStyle: Theme.of(context)
                    .textTheme
                    .bodyLarge!
                    .copyWith(color: Theme.of(context).colorScheme.primary),
                onTapButton: () {
                  Utils.popNavigation(context);
                })
          ],
        ),
      ),
    );
  }

  Widget buildDeleteButton() {
    return BlocConsumer<DeleteAccountCubit, DeleteAccountState>(
      listener: (context, state) async {
        if (state is DeleteAccountFailure) {
          if (state.errorMessage == requireRecentLoginErrorMessageKey) {
            Utils.openAlertDialog(
              context,
              message: requireRecentLoginKey,
              content: state.errorMessage,
              noLabel: cancelKey,
              yesLabel: logoutKey,
              onTapYes: () {
                Navigator.of(context).pop();
                context.read<AuthCubit>().signOut(context);
                Utils.navigateToScreen(context, Routes.loginScreen,
                    replaceAll: true);
              },
            );
          } else {
            Utils.showSnackBar(message: state.errorMessage);
          }
        }
        if (state is AccountDeleted) {
          Utils.showSnackBar(message: state.successMessage);
          await Future.delayed(const Duration(seconds: 1));

          Utils.navigateToScreen(context, Routes.loginScreen, replaceAll: true);
        }
      },
      builder: (context, state) {
        return CustomRoundedButton(
          widthPercentage: 1.0,
          buttonTitle: deleteMyAccountKey,
          showBorder: false,
          child: state is DeleteAccountProgress
              ? const CustomCircularProgressIndicator()
              : null,
          onTap: () {
            if (state is DeleteAccountProgress) {
              return;
            }
            if (isDemoApp) {
              Utils.showSnackBar(message: demoModeOnKey);
              return;
            }
            openDialog(context);
          },
        );
      },
    );
  }

  openDialog(BuildContext context) {
    final TextEditingController _mobileController = TextEditingController();
    final TextEditingController _passwordController = TextEditingController();
    FocusNode? _passwordFocus = FocusNode();
    bool _hidePassword = true;
    final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
    Utils.openModalBottomSheet(context,
            StatefulBuilder(builder: (context, StateSetter setState) {
      return BlocProvider(
        create: (context) => DeleteAccountCubit(),
        child: BlocConsumer<DeleteAccountCubit, DeleteAccountState>(
          listener: (context, state) async {
            if (state is DeleteAccountFailure) {
              Navigator.of(context).pop();
              Utils.showSnackBar(message: state.errorMessage);
            }
            if (state is AccountDeleted) {
              Utils.showSnackBar(message: state.successMessage);
              await Future.delayed(const Duration(seconds: 1));

              Utils.navigateToScreen(context, Routes.loginScreen,
                  replaceAll: true);
            }
          },
          builder: (context, state) {
            return FilterContainerForBottomSheet(
                title: deleteAccountKey,
                borderedButtonTitle: cancelKey,
                primaryButtonTitle: deleteAccountKey,
                borderedButtonOnTap: () => Navigator.of(context).pop(),
                primaryChild: state is DeleteAccountProgress
                    ? const CustomCircularProgressIndicator()
                    : null,
                primaryButtonOnTap: () {
                  if (state is DeleteAccountProgress) {
                    return;
                  }
                  if (_formKey.currentState!.validate()) {
                    context
                        .read<DeleteAccountCubit>()
                        .deleteUserAccount(isSocialLogin: false, params: {
                      ApiURL.mobileApiKey: _mobileController.text.trim(),
                      ApiURL.passwordApiKey: _passwordController.text.trim()
                    });
                  }
                },
                content: Form(
                  key: _formKey,
                  autovalidateMode: AutovalidateMode.onUserInteraction,
                  child: Column(
                    children: <Widget>[
                      CustomTextFieldContainer(
                        hintTextKey: mobileNumberKey,
                        textEditingController: _mobileController,
                        labelKey: '',
                        keyboardType: TextInputType.number,
                        textInputAction: TextInputAction.next,
                        inputFormatters: [
                          FilteringTextInputFormatter
                              .digitsOnly, // Allow only digits
                          LengthLimitingTextInputFormatter(
                              15), // Limit to 15 digits
                        ],
                        prefixWidget: const Icon(Icons.call_outlined),
                        validator: (v) =>
                            Validator.validatePhoneNumber(v, context),
                        onFieldSubmitted: (v) {
                          FocusScope.of(context).requestFocus(_passwordFocus);
                        },
                      ),
                      CustomTextFieldContainer(
                        hintTextKey: passwordKey,
                        textEditingController: _passwordController,
                        labelKey: '',
                        prefixWidget: const Icon(Icons.lock_outline),
                        hideText: _hidePassword,
                        keyboardType: TextInputType.text,
                        focusNode: _passwordFocus,
                        textInputAction: TextInputAction.done,
                        maxLines: 1,
                        validator: (v) =>
                            Validator.validatePassword(context, v),
                        inputFormatters: [
                          FilteringTextInputFormatter.deny(RegExp('[ ]')),
                        ],
                        suffixWidget: ShowHidePasswordButton(
                          hidePassword: _hidePassword,
                          onTapButton: () {
                            setState(() {
                              _hidePassword = !_hidePassword;
                            });
                          },
                        ),
                        onFieldSubmitted: (v) {
                          _passwordFocus.unfocus();
                        },
                      ),
                    ],
                  ),
                ));
          },
        ),
      );
    }), staticContent: true)
        .then((value) {
      FocusScope.of(context).unfocus();
    });
  }
}
