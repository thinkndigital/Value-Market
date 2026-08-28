import 'dart:io';
import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

class DesignConfig {
  static get appShadow => const [
        BoxShadow(
          color: Color(0x1E000000),
          blurRadius: 4,
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
  static shimmerEffect(double height, double width) => Shimmer.fromColors(
        baseColor: Colors.grey[300]!,
        highlightColor: Colors.grey[100]!,
        child: Container(
          width: width,
          height: height,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(8),
          ),
        ),
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
    return 10;
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
