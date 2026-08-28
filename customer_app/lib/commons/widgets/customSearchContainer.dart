import 'package:value_market_customer/ui/search/blocs/searchProductCubit.dart';
import 'package:value_market_customer/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_customer/commons/widgets/speechToTextIcon.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:speech_to_text/speech_to_text.dart';

class CustomSearchContainer extends StatelessWidget {
  final TextEditingController? textEditingController;
  final Widget? suffixWidget;
  final Widget? prefixWidget;
  final String? hintTextKey;
  final bool? showVoiceIcon;
  final Function()? onTap;
  final StateSetter? onVoiceIconTap;
  Function(String)? onChanged;
  final bool? readOnly;
  final bool? autoFocus;
  final FocusNode? focusNode;
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
      this.focusNode,
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
          focusNode: focusNode,
          autofocus: autoFocus ?? true,
          textEditingController:
              textEditingController ?? TextEditingController(),
          hintTextKey: hintTextKey ?? hintedSearchTextKey,
          onChanged: onChanged,
          prefixWidget: prefixWidget ??
              Icon(
                Icons.search,
                color: color,
              ),
          suffixWidget: showVoiceIcon ?? true
              ? BlocProvider(
                  create: (context) => SearchProductCubit(),
                  child: SpeechToTextIcon(
                      speechToText: SpeechToText(),
                      setState: onVoiceIconTap ?? (VoidCallback fn) {},
                      callback: (v) {}),
                )
              : suffixWidget,
          onFieldSubmitted: onFieldSubmitted),
    );
  }
}
