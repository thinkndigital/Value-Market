import 'dart:io';
import 'package:flutter/material.dart';

/// A reusable widget that provides SafeArea with proper bottom padding
/// to handle Android 15's transparent navigation bar
class SafeAreaWithBottomPadding extends StatelessWidget {
  final Widget child;
  final bool maintainBottomViewPadding;
  final EdgeInsets minimum;

  const SafeAreaWithBottomPadding({
    super.key,
    required this.child,
    this.maintainBottomViewPadding = true,
    this.minimum = EdgeInsets.zero,
  });

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      bottom: Platform.isIOS ? true : false,
      top: false,
      maintainBottomViewPadding: maintainBottomViewPadding,
      minimum: minimum,
      child: Padding(
        padding: EdgeInsets.only(
          bottom: _getBottomPadding(context),
        ),
        child: child,
      ),
    );
  }

  double _getBottomPadding(BuildContext context) {
    // Add extra padding for Android 15's transparent navigation bar
    if (Platform.isAndroid) {
      final bottomPadding = MediaQuery.paddingOf(context).bottom;
      // Only add padding if there's actual system navigation bar padding
      if (bottomPadding > 0) {
        return bottomPadding;
      }
    }
    return 0;
  }
}
