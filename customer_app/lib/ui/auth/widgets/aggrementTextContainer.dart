import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/routes/routes.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../core/localization/labelKeys.dart';

class AggrementTextContainer extends StatelessWidget {
  const AggrementTextContainer({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final settings = context.read<SettingsAndLanguagesCubit>().getSettings();
    return Text.rich(
      textAlign: TextAlign.center,
      TextSpan(
        children: [
          TextSpan(
              text:
                  '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: byContinuingYouAgreeToOurKey)} ',
              style: Theme.of(context).textTheme.bodyMedium),
          TextSpan(
              text: context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: termsOfServicesKey),
              style: Theme.of(context).textTheme.titleSmall,
              recognizer: TapGestureRecognizer()
                ..onTap = () => Utils.navigateToScreen(
                        context, Routes.policyScreen, arguments: {
                      'title': termsAndConditionKey,
                      'content': settings.termsAndConditions
                    })),
          TextSpan(
              text:
                  ' ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: andKey)} ',
              style: Theme.of(context).textTheme.bodyMedium),
          TextSpan(
              text: context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: privacyPolicyKey),
              style: Theme.of(context).textTheme.titleSmall,
              recognizer: TapGestureRecognizer()
                ..onTap = () => Utils.navigateToScreen(
                        context, Routes.policyScreen, arguments: {
                      'title': privacyPolicyKey,
                      'content': settings.privacyPolicy
                    }))
        ],
      ),
    );
  }
}
