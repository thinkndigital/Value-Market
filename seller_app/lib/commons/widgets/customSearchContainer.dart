import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';

class CustomSearchContainer extends StatelessWidget {
  final TextEditingController? textEditingController;
  final Widget? suffixWidget;
  final Widget? prefixWidget;
  final String? hintTextKey;
  final bool? showVoiceIcon;
  final Function()? onTap;
  final VoidCallback? onVoiceIconTap;
  Function(String)? onChanged;
  final bool? readOnly;
  final bool? autoFocus;
  final Function(String)? onFieldSubmitted;

  CustomSearchContainer(
      {super.key,
      this.textEditingController,
      this.autoFocus,
      this.suffixWidget,
      this.prefixWidget,
      this.hintTextKey,
      this.showVoiceIcon,
      this.onVoiceIconTap,
      this.onTap,
      this.onChanged,
      this.readOnly,
      this.onFieldSubmitted});

  @override
  Widget build(BuildContext context) {
    final color =
        Theme.of(context).colorScheme.secondary.withValues(alpha: 0.46);
    return SizedBox(
      width: double.maxFinite,
      height: 70,
      child: CustomTextFieldContainer(
          onTap: onTap,
          readOnly: readOnly ?? false,
          autofocus: autoFocus ?? true,
          textEditingController:
              textEditingController ?? TextEditingController(),
          hintTextKey: hintTextKey ?? searchKey,
          onChangeFun: onChanged,
          prefixWidget: prefixWidget ??
              Icon(
                Icons.search,
                color: color,
              ),
          suffixWidget: showVoiceIcon ?? true
              ? IconButton(
                  icon: const Icon(Icons.keyboard_voice_outlined),
                  color: color,
                  onPressed: onVoiceIconTap)
              : suffixWidget,
          onFieldSubmitted: onFieldSubmitted),
    );
  }
}
