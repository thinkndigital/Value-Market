import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/svg.dart';

import 'customLabelContainer.dart';

class CustomTextFieldContainer extends StatelessWidget {
  final String hintTextKey;
  final TextEditingController textEditingController;
  final String? labelKey;
  final TextInputType? keyboardType;
  final String? prefixImagePath;
  final Widget? prefixWidget;
  final Widget? suffixWidget;
  final EdgeInsetsGeometry? margin;
  final bool? hideText;
  final TextStyle? labelTextStyle;
  final int? maxLines;
  final bool? enable;
  final bool? readOnly;
  final bool? autofocus;
  final FocusNode? focusNode;
  final TextInputAction? textInputAction;
  final List<TextInputFormatter>? inputFormatters;
  final Function? validator;
  final bool isFieldValueMandatory;
  final Function(String)? onFieldSubmitted;
  final Function(String)? onChanged;
  final Function()? onTap;
  const CustomTextFieldContainer(
      {super.key,
      required this.hintTextKey,
      required this.textEditingController,
      this.labelKey,
      this.prefixImagePath,
      this.prefixWidget,
      this.suffixWidget,
      this.hideText,
      this.keyboardType,
      this.margin,
      this.maxLines,
      this.enable,
      this.readOnly,
      this.autofocus,
      this.focusNode,
      this.textInputAction,
      this.inputFormatters,
      this.validator,
      this.labelTextStyle,
      this.isFieldValueMandatory = true,
      this.onTap,
      this.onFieldSubmitted,
      this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: margin ?? const EdgeInsetsDirectional.symmetric(vertical: 7.5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          labelKey == '' || labelKey == null
              ? const SizedBox()
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CustomLabelContainer(
                      textKey: labelKey!,
                      isFieldValueMandatory: isFieldValueMandatory,
                    ),
                    const SizedBox(
                      height: 10,
                    ),
                  ],
                ),
          TextFormField(
            textDirection: Directionality.of(context),
            controller: textEditingController,
            enabled: enable ?? true,
            focusNode: focusNode,
            readOnly: readOnly ?? false,
            obscureText: hideText ?? false,
            autofocus: autofocus ?? false,
            maxLines: maxLines ?? 1,
            keyboardType: keyboardType ?? TextInputType.text,
            textAlign: TextAlign.left,
            textAlignVertical: TextAlignVertical.center,
            textInputAction: textInputAction,
            style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                color: Theme.of(context)
                    .colorScheme
                    .secondary
                    .withValues(alpha: 0.67)),
            inputFormatters: inputFormatters,
            validator: validator != null ? (val) => validator!(val) : null,
            onFieldSubmitted: onFieldSubmitted,
            onChanged: onChanged,
            decoration: InputDecoration(
                fillColor: enable ?? true
                    ? Colors.transparent
                    : const Color(0xffE3E2E2),
                filled: true,
                contentPadding: const EdgeInsetsDirectional.all(15),
                border: InputBorder.none,
                hintText: context
                    .read<SettingsAndLanguagesCubit>()
                    .getTranslatedValue(labelKey: hintTextKey),
                hintStyle: Theme.of(context)
                    .textTheme
                    .bodyMedium!
                    .copyWith(color: Theme.of(context).hintColor),
                prefixIconColor: Theme.of(context).hintColor,
                suffixIconColor: Theme.of(context).hintColor,
                prefixIcon: (prefixImagePath ?? "").isEmpty
                    ? prefixWidget
                    : SizedBox(
                        width: 30,
                        child: SvgPicture.asset(
                            Utils.getImagePath(prefixImagePath!)),
                      ),
                suffixIcon: suffixWidget,
                errorMaxLines: 2),
            onTap: onTap,
          )
        ],
      ),
    );
  }
}
