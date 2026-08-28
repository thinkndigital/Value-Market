import 'package:value_market_delivery_boy/core/constants/themeConstants.dart';
import 'package:value_market_delivery_boy/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_delivery_boy/features/profile/blocs/updateUserCubit.dart';
import 'package:value_market_delivery_boy/commons/widgets/customAppbar.dart';
import 'package:value_market_delivery_boy/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_delivery_boy/commons/widgets/customRoundedButton.dart';
import 'package:value_market_delivery_boy/commons/widgets/showHidePasswordButton.dart';
import 'package:value_market_delivery_boy/utils/designConfig.dart';
import 'package:value_market_delivery_boy/core/localization/labelKeys.dart';
import 'package:value_market_delivery_boy/utils/inputValidators.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:value_market_delivery_boy/core/api/apiEndPoints.dart';

import '../../../../core/routes/routes.dart';
import '../../../../utils/utils.dart';
import '../../../../commons/widgets/customTextButton.dart';
import '../../../../commons/widgets/customTextFieldContainer.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => UpdateUserCubit(),
        child: const ChangePasswordScreen(),
      );
  @override
  _ChangePasswordScreenState createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _curntPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _hideCurrentPassword = true,
      _hideCnfrmPassword = true,
      _hideNewPassword = true;
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final FocusNode _newPasswordFocus = FocusNode(),
      _cnfrmPasswordFocus = FocusNode();
  @override
  void dispose() {
    _curntPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    _newPasswordFocus.dispose();
    _cnfrmPasswordFocus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(
        titleKey: changePasswordKey,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.max,
            children: [
              buildCurrentPwdField(),
              buildForgotPwdLabel(),
              buildNewPwdField(),
              buildConfirmNewPwdField(),
              DesignConfig.defaultHeightSizedBox,
              BlocConsumer<UpdateUserCubit, UpdateUserState>(
                listener: (context, state) {
                  if (state is UpdateUserFetchFailure) {
                    Utils.showSnackBar(
                        context: context, message: state.errorMessage);
                  }
                  if (state is UpdateUserFetchSuccess) {
                    _curntPasswordController.clear();
                    _newPasswordController.clear();
                    _confirmPasswordController.clear();
                    Utils.showSnackBar(
                        context: context, message: state.successMessage);
                  }
                },
                builder: (context, state) {
                  return CustomRoundedButton(
                      widthPercentage: 1.0,
                      buttonTitle: savePasswordKey,
                      showBorder: false,
                      child: state is UpdateUserFetchInProgress
                          ? const CustomCircularProgressIndicator()
                          : null,
                      onTap: () async {
                        FocusScope.of(context).unfocus();
                        if (_formKey.currentState!.validate()) {
                          if (state is! UpdateUserFetchInProgress) {
                            context.read<UpdateUserCubit>().updateUser(params: {
                              ApiURL.oldApiKey:
                                  _curntPasswordController.text.trim(),
                              ApiURL.newApiKey:
                                  _newPasswordController.text.trim(),
                            });
                          }
                        }
                      });
                },
              )
            ],
          ),
        ),
      ),
    );
  }

  buildCurrentPwdField() {
    return CustomTextFieldContainer(
      hintTextKey: currentPasswordKey,
      textEditingController: _curntPasswordController,
      labelKey: '',
      prefixWidget: const Icon(Icons.lock_outline),
      hideText: _hideCurrentPassword,
      maxLines: 1,
      textInputAction: TextInputAction.next,
      validator: (v) => Validator.validatePassword(context, v),
      suffixWidget: ShowHidePasswordButton(
        hidePassword: _hideCurrentPassword,
        onTapButton: () {
          setState(() {
            _hideCurrentPassword = !_hideCurrentPassword;
          });
        },
      ),
      onFieldSubmitted: (v) {
        FocusScope.of(context).requestFocus(_newPasswordFocus);
      },
    );
  }

  buildForgotPwdLabel() {
    return Align(
        alignment: Alignment.bottomRight,
        child: CustomTextButton(
          buttonTextKey: forgotPasswordKey,
          onTapButton: () {
            Utils.navigateToScreen(context, Routes.forgotPasswordScreen);
          },
          textStyle: Theme.of(context).textTheme.bodyLarge!.copyWith(
              color: Theme.of(context).colorScheme.primary,
              fontWeight: FontWeight.w400),
        ));
  }

  buildNewPwdField() {
    return CustomTextFieldContainer(
      hintTextKey: newPasswordKey,
      textEditingController: _newPasswordController,
      labelKey: '',
      prefixWidget: const Icon(Icons.lock_outline),
      hideText: _hideNewPassword,
      maxLines: 1,
      focusNode: _newPasswordFocus,
      textInputAction: TextInputAction.next,
      validator: (v) => Validator.validatePassword(context, v),
      suffixWidget: ShowHidePasswordButton(
        hidePassword: _hideNewPassword,
        onTapButton: () {
          setState(() {
            _hideNewPassword = !_hideNewPassword;
          });
        },
      ),
      onFieldSubmitted: (v) {
        FocusScope.of(context).requestFocus(_cnfrmPasswordFocus);
      },
    );
  }

  buildConfirmNewPwdField() {
    return CustomTextFieldContainer(
      hintTextKey: confirmNewPasswordKey,
      textEditingController: _confirmPasswordController,
      labelKey: '',
      prefixWidget: const Icon(Icons.lock_outline),
      hideText: _hideCnfrmPassword,
      maxLines: 1,
      focusNode: _cnfrmPasswordFocus,
      textInputAction: TextInputAction.done,
      suffixWidget: ShowHidePasswordButton(
        hidePassword: _hideCnfrmPassword,
        onTapButton: () {
          setState(() {
            _hideCnfrmPassword = !_hideCnfrmPassword;
          });
        },
      ),
      onFieldSubmitted: (v) {
        _cnfrmPasswordFocus.unfocus();
      },
      validator: (value) {
        if (value.toString().trim() != _newPasswordController.text.trim()) {
          return context
              .read<SettingsAndLanguagesCubit>()
              .getTranslatedValue(labelKey: passwordMismatchMessageKey);
        } else {
          return null;
        }
      },
    );
  }
}
