import 'package:eshoppro_deliveryboy/commons/widgets/circleButton.dart';
import 'package:flutter/material.dart';

class CustomModalBotomSheet extends StatelessWidget {
final Widget child;
final bool? staticContent;
const CustomModalBotomSheet(
{super.key, required this.child, this.staticContent});

@override
Widget build(BuildContext context) {
return Material(
color: Colors.transparent,
child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
CircleButton(
heightAndWidth: 50,
backgroundColor: Colors.black,
onTap: Navigator.of(context).pop,
child: const Icon(
Icons.close,
color: Colors.white,
),
),
Container(
color: Colors.transparent,
height: 20,
),
staticContent == true
? Container(
clipBehavior: Clip.hardEdge,
decoration: BoxDecoration(
color: Theme.of(context).colorScheme.primaryContainer,
borderRadius: const BorderRadius.only(
topLeft: Radius.circular(28),
topRight: Radius.circular(28),
),
),
child: child)
: Expanded(
child: Container(
clipBehavior: Clip.hardEdge,
decoration: BoxDecoration(
color: Theme.of(context).colorScheme.primaryContainer,
borderRadius: const BorderRadius.only(
topLeft: Radius.circular(28),
topRight: Radius.circular(28),
),
),
child: child),
)
]));
}
}
