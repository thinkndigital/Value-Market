import 'dart:io';

import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';

class CustomBottomButtonContainer extends StatelessWidget {
  final Widget child;
  final double? bottomPadding;
  const CustomBottomButtonContainer(
      {super.key, required this.child, this.bottomPadding});

  @override
  Widget build(BuildContext context) {
    return Container(
        padding: EdgeInsetsDirectional.only(
            start: appContentHorizontalPadding,
            end: appContentHorizontalPadding,
            top: 8,
            bottom: Platform.isIOS
                ? 10
                : bottomPadding ?? MediaQuery.of(context).padding.bottom),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primaryContainer,
          boxShadow: [
            BoxShadow(
                color: Theme.of(context).shadowColor,
                blurRadius: 4,
                offset: const Offset(0, -4),
                spreadRadius: 0)
          ],
        ),
        child: child);
  }
}
