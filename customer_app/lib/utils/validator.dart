import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../commons/blocs/settingsAndLanguagesCubit.dart';

class Validator {
  static String emailPattern =
      r"[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)"
      r"*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+"
      r"[a-z0-9](?:[a-z0-9-]*[a-z0-9])?";
  static validateName(BuildContext context, String? value) {
    final pattern = RegExp(r'^[a-zA-Z ]+$');
    if (value!.trim().isEmpty) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: emptyValueErrorMessageKey);
    } else if (!pattern.hasMatch(value)) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: invalidNameErrorMessageKey);
    } else {
      return null;
    }
  }

  static validateEmail(BuildContext context, String? email) {
    if (email!.isEmpty) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: emptyValueErrorMessageKey);
    } else if (!RegExp(emailPattern).hasMatch(email)) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: invalidEmailErrorMessageKey);
    } else {
      return null;
    }
  }

  static emptyValueValidation(BuildContext context, String? value) {
    if (value!.trim().isEmpty) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: emptyValueErrorMessageKey);
    } else {
      return null;
    }
  }

  static validatePassword(BuildContext context, String? value) {
    if (value!.trim().isEmpty) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: emptyValueErrorMessageKey);
    } else if (value.length < 6) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: incorrectLengthOfPasswordErrorKey);
    } else {
      return null;
    }
  }

  static validatePhoneNumber(String? value, BuildContext context,
      {bool isShowSnackbar = false}) {
    String? validatemsg;
    if ((value ??= "").trim().isEmpty) {
      validatemsg = context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: emptyValueErrorMessageKey);
    }
    if (value.length < 4 || value.length > 15) {
      validatemsg = context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: invalidMobileErrorMsgKey);
    }
    if (validatemsg != null && isShowSnackbar) {
      Utils.showSnackBar(message: validatemsg, context: context);
    }
    return validatemsg;
  }
}
