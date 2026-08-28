import 'dart:io';

import 'package:eshop_plus/commons/widgets/circleButton.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';

class CustomModalBotomSheet extends StatelessWidget {
  final Widget child;
  final bool? staticContent; // Renamed to isStaticContent for clarity
  const CustomModalBotomSheet(
      {super.key,
      required this.child,
      this.staticContent = false}); // Default to false

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent, // Keep this transparent
      child: Column(
        mainAxisSize: MainAxisSize
            .min, // Use min to wrap content, allowing sheet to determine height
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          // Dismiss button (moved outside the main container for visual separation)
          CircleButton(
            heightAndWidth: 50,
            backgroundColor: Colors.black,
            onTap: Navigator.of(context).pop,
            child: const Icon(
              Icons.close,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 20), // Spacing between button and sheet

          // Main content container with rounded corners and background color
          Container(
            clipBehavior: Clip.hardEdge,
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primaryContainer,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(28),
                topRight: Radius.circular(28),
              ),
            ),
            // Conditionally apply height constraints or expand
            child: Column(
              children: [
                Container(
                  height: 4,
                  width: 40,
                  margin: EdgeInsets.all(appContentHorizontalPadding),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(100),
                    color: Theme.of(context)
                        .colorScheme
                        .secondary
                        .withValues(alpha: 0.23),
                  ),
                ),
                staticContent == true
                    ? IntrinsicHeight(
                        // Adjusts height based on content
                        child: child,
                      )
                    : ConstrainedBox(
                        // Use ConstrainedBox for min/max height if needed
                        constraints: BoxConstraints(
                          minHeight: MediaQuery.of(context).size.height *
                              0.3, // Example min height
                          maxHeight: MediaQuery.of(context).size.height *
                              0.8, // Example max height
                        ),
                        child: child,
                      ),
                if (Platform.isIOS)
                  Container(
                    height: 10,
                    color: Theme.of(context).colorScheme.primaryContainer,
                  ), // Add spacing for iOS
              ],
            ),
          ),
        ],
      ),
    );
  }
}
