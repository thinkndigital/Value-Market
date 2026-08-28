import 'package:flutter/material.dart';

class CustomBottomButtonContainer extends StatelessWidget {
final Widget child;
const CustomBottomButtonContainer({Key? key, required this.child})
: super(key: key);

@override
Widget build(BuildContext context) {
return Container(
padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
decoration: BoxDecoration(
color: Theme.of(context).colorScheme.primaryContainer,
boxShadow: [
BoxShadow(
color: Theme.of(context).shadowColor,
blurRadius: 4,
offset: const Offset(0, -2),
spreadRadius: 0,
)
],
),
child: child);
}
}
