import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';

class CustomBottomsheet extends StatelessWidget {
  final Widget child;
  final String titleLabelKey;
  final bool? doesHaveTextField;

  const CustomBottomsheet(
      {super.key,
      required this.child,
      required this.titleLabelKey,
      this.doesHaveTextField});

  Widget _buildContent({required BuildContext context}) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 100,
          height: 5,
          decoration: BoxDecoration(
              color: Theme.of(context)
                  .colorScheme
                  .secondary
                  .withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12.5)),
        ),
        Padding(
          padding: const EdgeInsetsDirectional.symmetric(
            vertical: 20,
          ),
          child: CustomTextContainer(
            textKey: titleLabelKey,
            style: const TextStyle(fontSize: 20.0, fontWeight: FontWeight.w800),
          ),
        ),
        child
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: MediaQuery.of(context).size.width,
      padding: const EdgeInsetsDirectional.symmetric(
          horizontal: appContentHorizontalPadding,
          vertical: appContentHorizontalPadding * (1.25)),
      decoration: BoxDecoration(
          color: Theme.of(context).scaffoldBackgroundColor,
          borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(bottomsheetBorderRadius),
              topRight: Radius.circular(bottomsheetBorderRadius))),
      child: (doesHaveTextField ?? false)
          ? SingleChildScrollView(
              child: _buildContent(context: context),
            )
          : _buildContent(context: context),
    );
  }
}
