import 'package:flutter/material.dart';

class ShowHidePasswordButton extends StatelessWidget {
  final bool hidePassword;
  final Function()? onTapButton;

  const ShowHidePasswordButton(
      {super.key, required this.hidePassword, required this.onTapButton});

  @override
  Widget build(BuildContext context) {
    return IconButton(
        highlightColor: Colors.transparent,
        splashColor: Colors.transparent,
        onPressed: onTapButton,
        padding: EdgeInsets.zero,
        icon: Icon(
          hidePassword
              ? Icons.visibility_outlined
              : Icons.visibility_off_outlined,
          color:
              Theme.of(context).colorScheme.secondary.withValues(alpha: 0.67),
          size: 24,
        ));
  }
}
