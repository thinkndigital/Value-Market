import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshop_plus/ui/auth/cubits/resetPasswordCubit.dart';
import 'package:eshop_plus/ui/auth/widgets/loginContainer.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/routes/routes.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import '../../../utils/utils.dart';
import '../../../utils/validator.dart';
import '../../../commons/widgets/customCircularProgressIndicator.dart';
import '../../../commons/widgets/customTextFieldContainer.dart';

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
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
        body: BlocConsumer<ResetPasswordCubit, ResetPasswordState>(
          listener: (context, state) {
            if (state is ResetPasswordSuccess) {
              Utils.showSnackBar(
                  message: state.successMessage, context: context);
              Utils.navigateToScreen(context, Routes.loginScreen);
            } else if (state is ResetPasswordFailure) {
              Utils.showSnackBar(message: state.errorMessage, context: context);
            }
          },
          builder: (context, state) {
            return LoginContainer(
              titleText: forgotPasswordTitleKey,
              descriptionText: weWillSendVerificationCodeToKey,
              buttonText: resetPasswordKey,
              onTapButton: state is ResetPasswordInProgress ? () {} : callApi,
              content: buildContent(),
              buttonWidget: state is ResetPasswordInProgress
                  ? const CustomCircularProgressIndicator()
                  : null,
            );
          },
        ),
      ),
    );
  }

  Widget buildContent() {
    return Form(
      key: _formKey,
      child: Padding(
        padding: const EdgeInsetsDirectional.only(top: 25, bottom: 120),
        child: CustomTextFieldContainer(
          hintTextKey: mobileNumberKey,
          textInputAction: TextInputAction.done,
          keyboardType: TextInputType.phone,
          textEditingController: _mobileController,
          inputFormatters: [
            FilteringTextInputFormatter.digitsOnly, // Allow only digits
            LengthLimitingTextInputFormatter(15), // Limit to 15 digits
          ],
          labelKey: '',
          validator: (v) => Validator.validatePhoneNumber(v, context),
          onChanged: (p0) => setState(() {}),
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
      ),
    );
  }

  callApi() {
    FocusScope.of(context).unfocus();

    if (_formKey.currentState!.validate()) {
      {
        context.read<ResetPasswordCubit>().resetPassword(params: {
          ApiURL.mobileNoApiKey: _mobileController.text.trim(),
        });
      }
    }
  }
}
