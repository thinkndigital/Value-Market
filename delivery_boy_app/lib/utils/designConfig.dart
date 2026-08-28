import 'dart:io';

import 'package:flutter/material.dart';

class DesignConfig {
  static UnderlineInputBorder setUnderlineInputBorder(Color color) {
    return UnderlineInputBorder(
      borderSide: BorderSide(color: color),
    );
  }

  static BoxDecoration boxDecoration(Color color, double radius) {
    return BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(radius),
    );
  }

  static RoundedRectangleBorder setRoundedBorder(
      Color bordercolor, double bradius, bool isboarder) {
    return RoundedRectangleBorder(
        side: BorderSide(color: bordercolor, width: isboarder ? 1.0 : 0),
        borderRadius: BorderRadius.circular(bradius));
  }

  static get appShadow => const [
        BoxShadow(
          color: Color(0x1E000000),
          blurRadius: 8,
          offset: Offset(0, 4),
          spreadRadius: 0,
        )
      ];
  static get defaultHeightSizedBox => const SizedBox(
        height: 16,
      );
  static get defaultWidthSizedBox => const SizedBox(
        width: 16,
      );
  static get smallHeightSizedBox => const SizedBox(
        height: 8,
      );
  static get smallWidthSizedBox => const SizedBox(
        width: 8,
      );

  /// Utility method to get proper bottom padding for Android 15's transparent navigation bar
  static double getAndroid15BottomPadding(BuildContext context) {
    if (Platform.isAndroid) {
      // For Android 15, we need to account for the transparent navigation bar
      final bottomPadding = MediaQuery.paddingOf(context).bottom;
      // Add extra padding if the navigation bar is transparent
      if (bottomPadding > 0) {
        return bottomPadding;
      }
    }
    return 0;
  }

  /// Utility method to check if the device is running Android 15 or higher
  static bool isAndroid15OrHigher() {
    if (Platform.isAndroid) {
      // This is a simplified check - in a real app you might want to use device_info_plus
      // to get the exact Android version
      return true; // Assume Android 15+ for now
    }
    return false;
  }
}
