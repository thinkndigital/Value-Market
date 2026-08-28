import 'package:eshoppro_deliveryboy/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshoppro_deliveryboy/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/svg.dart';

import '../../utils/inputValidators.dart';
import 'customLabelContainer.dart';

class CustomTextFieldContainer extends StatelessWidget {
  final String hintTextKey;
  final TextEditingController textEditingController;
  final String? labelKey;
  final String? sublabelKey;
  final bool? autofocus;
  final TextInputType? keyboardType;
  final String? prefixImagePath;
  final Widget? prefixWidget;
  final Widget? suffixWidget;
  final EdgeInsetsGeometry? margin;
  final bool? hideText;
  final TextStyle? labelTextStyle;
  final int? maxLines;
  final int? minLines;
  final bool? enable;
  final bool? readOnly;
  final FocusNode? focusNode;
  final TextInputAction? textInputAction;
  final List<TextInputFormatter>? inputFormatters;
  final Function? validator;
  final bool isFieldValueMandatory;
  final Function(String)? onFieldSubmitted;
  final Function()? onTap;
  final Function? onChangeFun;
  final bool isSetValidator;
  final String? errmsg;
  final TextStyle? textStyle;
  const CustomTextFieldContainer(
      {super.key,
      required this.hintTextKey,
      required this.textEditingController,
      this.labelKey,
      this.sublabelKey,
      this.autofocus,
      this.textStyle,
      this.prefixImagePath,
      this.onChangeFun,
      this.prefixWidget,
      this.suffixWidget,
      this.hideText,
      this.keyboardType,
      this.margin,
      this.maxLines,
      this.minLines,
      this.enable,
      this.readOnly,
      this.focusNode,
      this.textInputAction,
      this.inputFormatters,
      this.validator,
      this.labelTextStyle,
      this.isFieldValueMandatory = true,
      this.isSetValidator = false,
      this.errmsg,
      this.onTap,
      this.onFieldSubmitted});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: margin ?? const EdgeInsets.symmetric(vertical: 7.5),
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
                    if (sublabelKey != null && sublabelKey!.trim().isNotEmpty)
                      CustomLabelContainer(
                        textKey: sublabelKey!,
                        isFieldValueMandatory: false,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    const SizedBox(
                      height: 10,
                    ),
                  ],
                ),
          TextFormField(
            controller: textEditingController,
            enabled: enable ?? true,
            focusNode: focusNode,
            readOnly: readOnly ?? false,
            obscureText: hideText ?? false,
            autofocus: autofocus ?? false,
            maxLines:
                (keyboardType ?? TextInputType.text) == TextInputType.multiline
                    ? null
                    : maxLines ?? 1,
            minLines: minLines ?? 1,
            keyboardType: keyboardType ?? TextInputType.text,
            textAlign: TextAlign.left,
            textAlignVertical: TextAlignVertical.center,
            textInputAction: textInputAction,
            style: textStyle ??
                const TextStyle(
                  fontSize: 16.0,
                ),
            inputFormatters: inputFormatters,
            onFieldSubmitted: onFieldSubmitted,
            validator: (value) {
              if (validator != null) {
                return validator!(value);
              }
              if (isSetValidator) {
                if (keyboardType == TextInputType.emailAddress) {
                  return Validator.validateEmail(value, context);
                }
                if (keyboardType == TextInputType.phone) {
                  return Validator.validatePhoneNumber(value, context);
                }

                if (value == null || value.toString().trim().isEmpty) {
                  return Validator.emptyValueValidation(value, context,
                      errmsg: errmsg);
                }
              }
              return null;
            },
            decoration: InputDecoration(
              fillColor:
                  enable ?? true ? Colors.transparent : const Color(0xffE3E2E2),
              filled: true,
              contentPadding: const EdgeInsets.all(15),
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
            ),
            onTap: onTap,
            onChanged: (value) {
              if (onChangeFun != null) {
                onChangeFun!(value);
              }
            },
          )
        ],
      ),
    );
  }
}
