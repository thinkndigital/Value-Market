import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class ErrorContainer extends StatelessWidget {
  final String errorMessage;
  final bool? showRetryButton;
  final bool? showErrorImage;
  final Color? errorMessageColor;
  final double? errorMessageFontSize;
  final Function? onTapRetry;
  final Color? retryButtonTextColor;

  const ErrorContainer({
    super.key,
    required this.errorMessage,
    this.errorMessageColor,
    this.errorMessageFontSize,
    this.onTapRetry,
    this.showErrorImage,
    this.retryButtonTextColor,
    this.showRetryButton,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          (showErrorImage ?? true)
              ? SizedBox(
                  height: MediaQuery.of(context).size.height * (0.25),
                )
              : const SizedBox(),
          Center(
            child: Padding(
              padding: const EdgeInsetsDirectional.symmetric(horizontal: 8),
              child: CustomTextContainer(
                textKey: errorMessage,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: errorMessageColor ??
                      Theme.of(context).colorScheme.secondary,
                  fontSize: errorMessageFontSize ?? 16,
                ),
              ),
            ),
          ),
          (showRetryButton ?? true)
              ? CupertinoButton(
                  child: CustomTextContainer(
                    textKey: retryKey,
                    style: TextStyle(
                        color: retryButtonTextColor ??
                            Theme.of(context).colorScheme.primary),
                  ),
                  onPressed: () {
                    onTapRetry?.call();
                  })
              : const SizedBox()
        ],
      ),
    );
  }
}
