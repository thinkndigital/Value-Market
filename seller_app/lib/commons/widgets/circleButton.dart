import 'package:flutter/material.dart';

class CircleButton extends StatelessWidget {
  final Widget child;
  final double? heightAndWidth;
  final Function()? onTap;
  final Color? backgroundColor;
  final List<BoxShadow>? boxShadow;
  const CircleButton({
    super.key,
    required this.child,
    this.heightAndWidth = 40.0,
    this.backgroundColor,
    this.boxShadow,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: heightAndWidth,
        height: heightAndWidth,
        alignment: Alignment.center,
        decoration: BoxDecoration(
            shape: BoxShape.circle,
            boxShadow: boxShadow,
            color: backgroundColor ??
                Theme.of(context).colorScheme.secondary.withValues(alpha: 0.1)),
        child: child,
      ),
    );
  }
}
