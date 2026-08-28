import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class CustomLabelContainer extends StatelessWidget {
  final TextStyle? style;
  final TextAlign? textAlign;
  final String textKey;
  final bool? isFieldValueMandatory;

  const CustomLabelContainer({
    super.key,
    required this.textKey,
    this.style,
    this.textAlign,
    this.isFieldValueMandatory = true,
  });

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<SettingsAndLanguagesCubit, SettingsAndLanguagesState>(
      builder: (context, state) {
        return Row(
          children: [
            Text(
              context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: textKey),
              style: style ?? Theme.of(context).textTheme.bodyMedium,
              maxLines: 1,
              overflow: TextOverflow.visible,
              textAlign: textAlign ?? TextAlign.start,
            ),
            if (isFieldValueMandatory == true)
              Text(
                ' *',
                style: style ??
                    Theme.of(context)
                        .textTheme
                        .bodyMedium!
                        .copyWith(color: Theme.of(context).colorScheme.error),
              ),
          ],
        );
      },
    );
  }
}
