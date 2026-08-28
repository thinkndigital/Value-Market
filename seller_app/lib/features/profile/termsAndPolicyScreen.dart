import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../utils/utils.dart';

class TermsAndPolicyScreen extends StatelessWidget {
  static Widget getRouteInstance() => const TermsAndPolicyScreen();
  const TermsAndPolicyScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(titleKey: termsAndPolicyKey),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(appContentHorizontalPadding),
        child: Column(
          children: <Widget>[
            buildListTile(
              context,
              sellerTermsAndConditionKey,
            ),
            buildListTile(context, sellerPrivacyPolicyKey),
            buildListTile(context, shippingPolicyKey),
            buildListTile(context, returnPolicyKey),
          ],
        ),
      ),
    );
  }

  Widget buildListTile(
    BuildContext context,
    String title,
  ) {
    return GestureDetector(
      onTap: () => navigatoToPolicyScreen(title, context),
      child: Container(
        width: double.infinity,
        margin: const EdgeInsetsDirectional.only(bottom: 8),
        padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
        decoration: ShapeDecoration(
          color: Theme.of(context).colorScheme.primaryContainer,
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(borderRadius)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            CustomTextContainer(
              textKey: title,
              style: Theme.of(context).textTheme.titleMedium!.copyWith(
                    color: Theme.of(context)
                        .colorScheme
                        .secondary
                        .withValues(alpha: 0.8),
                  ),
            ),
            Icon(
              Icons.arrow_forward_ios,
              color: Theme.of(context)
                  .colorScheme
                  .secondary
                  .withValues(alpha: 0.8),
            ),
          ],
        ),
      ),
    );
  }

  void navigatoToPolicyScreen(String policy, BuildContext context) {
    final settings = context.read<SettingsAndLanguagesCubit>().getSettings();

    Utils.navigateToScreen(context, Routes.policyScreen, arguments: {
      'title': policy,
      'content': policy == sellerTermsAndConditionKey
          ? settings.sellerTermsAndConditions
          : policy == sellerPrivacyPolicyKey
              ? settings.sellerPrivacyPolicy
              : policy == shippingPolicyKey
                  ? settings.shippingPolicy
                  : settings.returnPolicy ?? ''
    });
  }
}
