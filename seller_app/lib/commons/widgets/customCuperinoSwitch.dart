import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

class CustomCuperinoSwitch extends StatelessWidget {
  final bool value;
  final void Function(bool)? onChanged;
  const CustomCuperinoSwitch(
      {Key? key, required this.value, required this.onChanged})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Transform.scale(
      scale: 0.7,
      child: CupertinoSwitch(
        value: value,
        onChanged: onChanged,
        thumbColor:
            value ? Theme.of(context).colorScheme.primary : Colors.white,
        activeTrackColor:
            Theme.of(context).colorScheme.primary.withValues(alpha: 0.3),
      ),
    );
  }
}
