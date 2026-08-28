import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../commons/blocs/settingsAndLanguagesCubit.dart';
import '../core/localization/labelKeys.dart';
import 'utils.dart';

class Validator {
  static String emailPattern =
      r"[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)"
      r"*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+"
      r"[a-z0-9](?:[a-z0-9-]*[a-z0-9])?";

  static validateEmail(String? email, BuildContext context) {
    if ((email ??= "").trim().isEmpty) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: emptyEmailMessageKey);
    } else if (!RegExp(emailPattern).hasMatch(email)) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: invalidEmailMessageKey);
    } else {
      return null;
    }
  }

//'Please enter some text'
  static emptyValueValidation(String? value, BuildContext context,
      {String? errmsg}) {
    String message = "";
    if (errmsg == null) {
      message = context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: emptyValueMessageKey);
    } else {
      message = context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: errmsg);
    }

    return (value ??= "").trim().isEmpty ? message : null;
  }

  static validatePhoneNumber(String? value, BuildContext context,
      {bool isShowSnackbar = false}) {
    String? validatemsg;
    if ((value ??= "").trim().isEmpty) {
      validatemsg = context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: emptyValueMessageKey);
    }
    if (value.length < 4 || value.length > 15) {
      validatemsg = context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: invalidPhoneMessageKey);

//return getLables(invalidPhoneMessage;
    }
    if (validatemsg != null && isShowSnackbar) {
      Utils.showSnackBar(message: validatemsg, context: context);
    }
    return validatemsg;
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
}

class AlwaysDisabledFocusNode extends FocusNode {
  @override
  bool get hasFocus => false;
}
