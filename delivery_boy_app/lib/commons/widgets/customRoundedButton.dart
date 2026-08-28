import 'package:value_market_delivery_boy/core/constants/themeConstants.dart';
import 'package:value_market_delivery_boy/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';

class CustomRoundedButton extends StatelessWidget {
  final String? buttonTitle;
  final double? height;
  final double? widthPercentage;
  final Function? onTap;
  final Color? backgroundColor;
  final double? radius;
  final Color? shadowColor;
  final bool showBorder;
  final Color? borderColor;
  final double? elevation;
  final Widget? child;
  final TextStyle? style;
  final double? horizontalPadding;

//if child pass then button title will be ignored
  const CustomRoundedButton({
    super.key,
    this.widthPercentage,
    this.backgroundColor,
    this.borderColor,
    this.elevation,
    required this.buttonTitle,
    this.onTap,
    this.radius,
    this.shadowColor,
    this.child,
    required this.showBorder,
    this.height,
    this.style,
    this.horizontalPadding,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(radius ?? borderRadius),
      onTap: onTap as void Function()?,
      child: Material(
        shadowColor: shadowColor ?? Colors.black54,
        elevation: elevation ?? 0.0,
        color: backgroundColor ?? Theme.of(context).colorScheme.primary,
        borderRadius: BorderRadius.circular(radius ?? borderRadius),
        child: Container(
          padding: EdgeInsetsDirectional.symmetric(
            horizontal: horizontalPadding ?? 12.0, /*  vertical: 8.0 */
          ),
          alignment: Alignment.center,
          height: height ?? 40.0,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(radius ?? borderRadius),
            border: showBorder
                ? Border.all(
                    color: borderColor ??
                        Theme.of(context).scaffoldBackgroundColor,
                  )
                : null,
          ),
          width: widthPercentage != null
              ? MediaQuery.of(context).size.width * widthPercentage!
              : null,
          child: Center(
            child: child ??
                CustomTextContainer(
                  textKey: buttonTitle ?? "",
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: style ??
                      Theme.of(context).textTheme.bodyMedium!.copyWith(
                          color: Theme.of(context).colorScheme.onPrimary),
                ),
          ),
        ),
      ),
    );
  }
}
