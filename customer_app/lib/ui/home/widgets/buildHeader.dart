import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';

class BuildHeader extends StatelessWidget {
  final String title;
  final String? subtitle;
  final bool showSeeAllButton;
  final VoidCallback onTap;
  const BuildHeader(
      {Key? key,
      required this.title,
      this.subtitle,
      required this.onTap,
      this.showSeeAllButton = true})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Padding(
        padding: const EdgeInsetsDirectional.symmetric(
            horizontal: appContentHorizontalPadding),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CustomTextContainer(
                    textKey: title,
                    style: Theme.of(context).textTheme.bodyLarge,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (subtitle != null)
                    CustomTextContainer(
                      textKey: subtitle!,
                      style: Theme.of(context).textTheme.bodySmall!.copyWith(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.67)),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                ],
              ),
            ),
            DesignConfig.defaultWidthSizedBox,
            if (showSeeAllButton)
              GestureDetector(
                onTap: onTap,
                child: Row(
                  children: <Widget>[
                    CustomTextContainer(
                      textKey: seeAllKey,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(
                      width: 4,
                    ),
                    const Icon(
                      Icons.arrow_circle_right_outlined,
                      size: 18,
                    )
                  ],
                ),
              )
          ],
        ));
  }
}
