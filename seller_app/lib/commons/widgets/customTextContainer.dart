import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class CustomTextContainer extends StatelessWidget {
  final TextStyle? style;
  final TextAlign? textAlign;
  final int? maxLines;
  final String textKey;
  final TextOverflow? overflow;
  final bool isTranslated;

  const CustomTextContainer(
      {super.key,
      required this.textKey,
      this.isTranslated = false,
      this.maxLines,
      this.style,
      this.textAlign,
      this.overflow});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<SettingsAndLanguagesCubit, SettingsAndLanguagesState>(
      builder: (context, state) {
        return Text(
          isTranslated
              ? textKey
              : context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: textKey),
          style: style,
          maxLines: maxLines,
          overflow: overflow,
          textAlign: textAlign,
        );
      },
    );
  }
}
