import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../commons/widgets/customRoundedButton.dart';

class LoginContainer extends StatelessWidget {
  final String titleText;
  final String descriptionText;
  final String buttonText;
  final VoidCallback onTapButton;
  final Widget content;
  final Widget? footerWidget;
  final Widget? buttonWidget;
  const LoginContainer(
      {Key? key,
      required this.titleText,
      required this.descriptionText,
      required this.buttonText,
      required this.onTapButton,
      required this.content,
      this.buttonWidget,
      this.footerWidget})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(
          appContentHorizontalPadding,
          MediaQuery.of(context).padding.top + 70,
          appContentHorizontalPadding,
          20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          CustomImageWidget(
              url: context
                      .read<SettingsAndLanguagesCubit>()
                      .getSettings()
                      .logo ??
                  '',
              width: MediaQuery.of(context).size.width,
              height: 118,
              borderRadius: 8,
              boxFit: BoxFit.fitWidth),
          const SizedBox(
            height: 40,
          ),
          CustomTextContainer(
            textKey: titleText,
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(
            height: 10,
          ),
          CustomTextContainer(
            textKey: descriptionText,
            style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                color: Theme.of(context)
                    .colorScheme
                    .secondary
                    .withValues(alpha: 0.8)),
          ),
          content,
          const SizedBox(
            height: 25,
          ),
          CustomRoundedButton(
            widthPercentage: 1,
            buttonTitle: buttonText,
            radius: 5,
            onTap: onTapButton,
            showBorder: false,
            child: buttonWidget,
          ),
          const SizedBox(
            height: 25,
          ),
          footerWidget ?? const SizedBox(),
        ],
      ),
    );
  }
}
