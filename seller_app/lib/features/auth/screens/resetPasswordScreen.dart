import 'package:eshopplus_seller/features/auth/widgets/loginContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';

import '../../../commons/widgets/showHidePasswordButton.dart';

class ResetPasswordScreen extends StatefulWidget {
  const ResetPasswordScreen({Key? key}) : super(key: key);

  static Widget getRouteInstance() => const ResetPasswordScreen();
  @override
  _ResetPasswordScreenState createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  final TextEditingController _newPasswordController = TextEditingController();
  final TextEditingController _confirmPasswordController =
      TextEditingController();
  bool _hidePassword = true, _hideNewPassword = true;
  final FocusNode _newPasswordFocus = FocusNode(),
      _cnfrmPasswordFocus = FocusNode();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
        body: LoginContainer(
      titleText: resetPasswordKey,
      descriptionText: pleaseTypesomethingKey,
      buttonText: resetPasswordKey,
      onTapButton: () {},
      content: buildContent(),
    ));
  }

  Widget buildContent() {
    return Padding(
      padding: const EdgeInsets.only(top: 25),
      child: Column(
        children: <Widget>[
          CustomTextFieldContainer(
            hintTextKey: newPasswordKey,
            textEditingController: _newPasswordController,
            labelKey: '',
            prefixWidget: const Icon(Icons.lock_outline),
            hideText: _hidePassword,
            maxLines: 1,
            focusNode: _newPasswordFocus,
            textInputAction: TextInputAction.next,
            suffixWidget: ShowHidePasswordButton(
              hidePassword: _hidePassword,
              onTapButton: () {
                setState(() {
                  _hidePassword = !_hidePassword;
                });
              },
            ),
            onFieldSubmitted: (v) {
              FocusScope.of(context).requestFocus(_newPasswordFocus);
            },
          ),
          CustomTextFieldContainer(
            hintTextKey: confirmNewPasswordKey,
            textEditingController: _confirmPasswordController,
            labelKey: '',
            prefixWidget: const Icon(Icons.lock_outline),
            hideText: _hideNewPassword,
            maxLines: 1,
            focusNode: _cnfrmPasswordFocus,
            textInputAction: TextInputAction.done,
            suffixWidget: ShowHidePasswordButton(
              hidePassword: _hideNewPassword,
              onTapButton: () {
                setState(() {
                  _hideNewPassword = !_hideNewPassword;
                });
              },
            ),
            onFieldSubmitted: (v) {
              _cnfrmPasswordFocus.unfocus();
            },
          ),
        ],
      ),
    );
  }
}
