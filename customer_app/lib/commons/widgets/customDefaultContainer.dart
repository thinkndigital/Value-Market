import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';

class CustomDefaultContainer extends StatelessWidget {
  final Widget child;
  final double? borderRadius;
  const CustomDefaultContainer(
      {super.key, required this.child, this.borderRadius});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: MediaQuery.of(context).size.width,
      padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
      color: borderRadius == null
          ? Theme.of(context).colorScheme.primaryContainer
          : null,
      decoration: borderRadius != null
          ? BoxDecoration(
              color: Theme.of(context).colorScheme.primaryContainer,
              borderRadius: BorderRadius.circular(borderRadius!))
          : null,
      child: child,
    );
  }
}
