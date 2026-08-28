import 'dart:io';
import 'package:flutter/material.dart';

/// A wrapper widget that automatically applies bottom padding for Android 15
/// This can be used to wrap individual screens or applied globally
class ScreenWrapper extends StatelessWidget {
  final Widget child;
  final bool applyBottomPadding;
  final EdgeInsets? additionalPadding;

  const ScreenWrapper({
    super.key,
    required this.child,
    this.applyBottomPadding = true,
    this.additionalPadding,
  });

  @override
  Widget build(BuildContext context) {
    if (!applyBottomPadding || !Platform.isAndroid) {
      return child;
    }

    final bottomPadding = MediaQuery.paddingOf(context).bottom;
    final totalBottomPadding = bottomPadding + (additionalPadding?.bottom ?? 0);

    if (totalBottomPadding <= 0) {
      return child;
    }

    return Padding(
      padding: EdgeInsets.only(bottom: totalBottomPadding),
      child: child,
    );
  }

  /// Factory constructor for screens that need bottom padding
  factory ScreenWrapper.withBottomPadding({
    required Widget child,
    EdgeInsets? additionalPadding,
  }) {
    return ScreenWrapper(
      child: child,
      applyBottomPadding: true,
      additionalPadding: additionalPadding,
    );
  }

  /// Factory constructor for screens that don't need bottom padding
  /// (like screens with bottom navigation)
  factory ScreenWrapper.withoutBottomPadding({
    required Widget child,
  }) {
    return ScreenWrapper(
      child: child,
      applyBottomPadding: false,
    );
  }
}
