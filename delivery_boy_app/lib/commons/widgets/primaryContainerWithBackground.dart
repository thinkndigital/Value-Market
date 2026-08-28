import 'package:value_market_delivery_boy/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';

class PrimaryContainerWithBackground extends StatelessWidget {
final Widget child;
const PrimaryContainerWithBackground({Key? key, required this.child})
: super(key: key);

@override
Widget build(BuildContext context) {
return Container(
width: double.maxFinite,
padding: EdgeInsets.all(appContentHorizontalPadding),
color: Theme.of(context).colorScheme.primaryContainer,
child: Container(
decoration: ShapeDecoration(
color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.8),
shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
),
child: Stack(clipBehavior: Clip.none, children: [
Positioned(
right: 0,
top: 0,
child: Container(
width: 67,
height: 62,
decoration: ShapeDecoration(
color: Colors.white.withValues(alpha: 0.08),
shape: const RoundedRectangleBorder(
borderRadius: BorderRadius.only(
topRight: Radius.circular(8),
bottomLeft: Radius.circular(250),
),
),
),
),
),
Positioned(
bottom: 0,
left: 0,
child: Container(
width: 67,
height: 62,
decoration: ShapeDecoration(
color: Colors.white.withValues(alpha: 0.08),
shape: const RoundedRectangleBorder(
borderRadius: BorderRadius.only(
topRight: Radius.circular(250),
bottomLeft: Radius.circular(borderRadius),
),
),
),
),
),
Container(
alignment: Alignment.center,
padding: const EdgeInsets.symmetric(
vertical: appContentHorizontalPadding),
child: child)
]),
),
);
}
}
