import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/commons/widgets/showHidePasswordButton.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import '../../../utils/utils.dart';

class SignupPage1 extends StatefulWidget {
  final bool? isEditProfileScreen;
  final Map<String, TextEditingController> controllers;
  final Map<String, dynamic> files;
  final Map<String, FocusNode> focusNodes;

  const SignupPage1(
      {super.key,
      required this.controllers,
      required this.focusNodes,
      required this.files,
      this.isEditProfileScreen = false});

  @override
  State<SignupPage1> createState() => _SignupPage1State();
}

class _SignupPage1State extends State<SignupPage1> {
  bool _hideCurrentPassword = true, _hideCnfrmPassword = true;
  @override
  void initState() {
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: !widget.isEditProfileScreen!
          ? EdgeInsets.symmetric(
              horizontal: appContentHorizontalPadding,
              vertical: appContentVerticalSpace)
          : EdgeInsets.only(
              top: appContentVerticalSpace,
              left: appContentHorizontalPadding,
              right: appContentHorizontalPadding,
            ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (widget.isEditProfileScreen == false)
            Center(
              child: CustomImageWidget(
                url: context
                        .read<SettingsAndLanguagesCubit>()
                        .getSettings()
                        .logo ??
                    '',
                width: MediaQuery.of(context).size.width,
                height: 118,
                boxFit: BoxFit.fitWidth,
              ),
            ),
          Utils.buildSignupHeader(context, personalDetailsKey),
          CustomTextFieldContainer(
              hintTextKey: nameKey,
              textEditingController: widget.controllers[nameKey]!,
              labelKey: nameKey,
              textInputAction: TextInputAction.next,
              focusNode: widget.focusNodes[nameKey],
              isSetValidator: true,
              errmsg: enterNameKey,
              onFieldSubmitted: (v) => FocusScope.of(context)
                  .requestFocus(widget.focusNodes[emailKey])),
          CustomTextFieldContainer(
              hintTextKey: emailKey,
              textEditingController: widget.controllers[emailKey]!,
              labelKey: emailKey,
              textInputAction: TextInputAction.next,
              keyboardType: TextInputType.emailAddress,
              focusNode: widget.focusNodes[emailKey],
              isSetValidator: true,
              onFieldSubmitted: (v) => FocusScope.of(context)
                  .requestFocus(widget.focusNodes[mobileNumberKey])),
          CustomTextFieldContainer(
              hintTextKey: mobileNumberKey,
              textEditingController: widget.controllers[mobileNumberKey]!,
              labelKey: mobileNumberKey,
              readOnly: widget.isEditProfileScreen,
              keyboardType: TextInputType.phone,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly, // Allow only digits
                LengthLimitingTextInputFormatter(15), // Limit to 15 digits
              ],
              validator: (v) => Validator.validatePhoneNumber(v, context),
              textInputAction: TextInputAction.next,
              focusNode: widget.focusNodes[mobileNumberKey],
              isSetValidator: true,
              onFieldSubmitted: (v) => FocusScope.of(context).requestFocus(
                  widget.focusNodes[!widget.isEditProfileScreen!
                      ? passwordKey
                      : addressKey])),
          if (!widget.isEditProfileScreen!)
            CustomTextFieldContainer(
                hintTextKey: passwordKey,
                textEditingController: widget.controllers[passwordKey]!,
                labelKey: passwordKey,
                hideText: _hideCurrentPassword,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[passwordKey],
                isSetValidator: true,
                suffixWidget: ShowHidePasswordButton(
                  hidePassword: _hideCurrentPassword,
                  onTapButton: () {
                    setState(() {
                      _hideCurrentPassword = !_hideCurrentPassword;
                    });
                  },
                ),
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[confirmPasswordKey])),
          if (!widget.isEditProfileScreen!)
            CustomTextFieldContainer(
                hintTextKey: confirmPasswordKey,
                textEditingController: widget.controllers[confirmPasswordKey]!,
                labelKey: confirmPasswordKey,
                hideText: _hideCnfrmPassword,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[confirmPasswordKey],
                isSetValidator: true,
                suffixWidget: ShowHidePasswordButton(
                  hidePassword: _hideCnfrmPassword,
                  onTapButton: () {
                    setState(() {
                      _hideCnfrmPassword = !_hideCnfrmPassword;
                    });
                  },
                ),
                validator: (String? value) {
                  if (value.toString().trim().isEmpty) {
                    return context
                        .read<SettingsAndLanguagesCubit>()
                        .getTranslatedValue(labelKey: enterconfirmpasswordKey);
                  } else if (widget.controllers[passwordKey]!.text !=
                      widget.controllers[confirmPasswordKey]!.text) {
                    return context
                        .read<SettingsAndLanguagesCubit>()
                        .getTranslatedValue(
                            labelKey: confirmpasswordnotmatchedKey);
                  }
                },
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[addressKey])),
          CustomTextFieldContainer(
              hintTextKey: addressKey,
              textEditingController: widget.controllers[addressKey]!,
              labelKey: addressKey,
              maxLines: 3,
              textInputAction: TextInputAction.done,
              focusNode: widget.focusNodes[addressKey],
              isSetValidator: true,
              isFieldValueMandatory: true,
              onFieldSubmitted: (v) =>
                  widget.focusNodes[addressKey]!.unfocus()),
          if (!widget.isEditProfileScreen!) ...[
            Utils.buildImageUploadWidget(
                context: context,
                labelKey: authorizedSignatureKey,
                file: widget.files[authorizedSignatureKey],
                onTapUpload: () =>
                    Utils.openFileExplorer(fileType: FileType.image)
                        .then((value) {
                      if (value != null) {
                        setState(() {
                          widget.files[authorizedSignatureKey] = value.first;
                        });
                      }
                    }),
                onTapClose: () {
                  setState(() {
                    widget.files[authorizedSignatureKey] = null;
                  });
                }),
            Utils.buildImageUploadWidget(
                context: context,
                labelKey: nationalIdentityCardKey,
                file: widget.files[nationalIdentityCardKey],
                onTapUpload: () =>
                    Utils.openFileExplorer(fileType: FileType.image)
                        .then((value) {
                      if (value != null) {
                        setState(() {
                          widget.files[nationalIdentityCardKey] = value.first;
                        });
                      }
                    }),
                onTapClose: () {
                  setState(() {
                    widget.files[nationalIdentityCardKey] = null;
                  });
                })
          ]
        ],
      ),
    );
  }
}
