
import 'package:value_market_delivery_boy/core/localization/labelKeys.dart';
import 'package:value_market_delivery_boy/features/auth/blocs/resetPasswordCubit.dart';
import 'package:value_market_delivery_boy/features/auth/widgets/loginContainer.dart';
import 'package:value_market_delivery_boy/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_delivery_boy/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_delivery_boy/core/configs/appConfig.dart';
import 'package:value_market_delivery_boy/utils/inputValidators.dart';
import 'package:value_market_delivery_boy/core/api/apiEndPoints.dart';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/routes/routes.dart';
import '../../../utils/utils.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => ResetPasswordCubit(),
        child: const ForgotPasswordScreen(),
      );
  @override
  _ForgotPasswordScreenState createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final TextEditingController _mobileController = TextEditingController();

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<ResetPasswordCubit, ResetPasswordState>(
      listener: (context, state) {
        if (state is ResetPasswordSuccess) {
          Utils.showSnackBar(message: state.successMessage, context: context);
          Utils.navigateToScreen(context, Routes.loginScreen);
        } else if (state is ResetPasswordFailure) {
          Utils.showSnackBar(message: state.errorMessage, context: context);
        }
      },
      builder: (context, state) {
        return Scaffold(
          body: LoginContainer(
            titleText: forgotPasswordTitleKey,
            descriptionText: weWillSendVerificationCodeToKey,
            buttonText: resetPasswordKey,
            buttonWidget: state is ResetPasswordInProgress
                ? const CustomCircularProgressIndicator()
                : null,
            onTapButton: state is ResetPasswordInProgress ? () {} : callApi,
            content: buildContent(),
          ),
        );
      },
    );
  }

  callApi() {
    FocusScope.of(context).unfocus();

    if (_mobileController.text.isNotEmpty) {
      {
        if (isDemoApp) {
          Utils.showSnackBar(message: demoModeOnKey, context: context);
          return;
        }
        context.read<ResetPasswordCubit>().resetPassword(params: {
          ApiURL.mobileNoApiKey: _mobileController.text.trim(),
        });
      }
    } else {
      Utils.showSnackBar(message: enterValidMobileNumberKey, context: context);
    }
  }

  Widget buildContent() {
    return Padding(
      padding: const EdgeInsets.only(top: 25),
      child: CustomTextFieldContainer(
        hintTextKey: mobileNumberKey,
        textInputAction: TextInputAction.done,
        keyboardType: TextInputType.number,
        textEditingController: _mobileController,
        labelKey: '',
        inputFormatters: [
          FilteringTextInputFormatter.digitsOnly, // Allow only digits
          LengthLimitingTextInputFormatter(15), // Limit to 15 digits
        ],
        validator: (v) => Validator.validatePhoneNumber(v, context),
        onChangeFun: (p0) => setState(() {}),
        suffixWidget: _mobileController.text.isNotEmpty
            ? IconButton(
                icon: Icon(
                  Icons.close_rounded,
                  color: Theme.of(context).colorScheme.secondary,
                ),
                onPressed: () => _mobileController.clear(),
              )
            : null,
      ),
    );
  }
}
