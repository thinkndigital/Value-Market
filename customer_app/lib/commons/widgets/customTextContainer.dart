import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class CustomTextContainer extends StatelessWidget {
  final TextStyle? style;
  final TextAlign? textAlign;
  final int? maxLines;
  final String textKey;
  final TextOverflow? overflow;

  const CustomTextContainer(
      {super.key,
      required this.textKey,
      this.maxLines,
      this.style,
      this.textAlign,
      this.overflow});

  @override
  Widget build(BuildContext context) {
    TextStyle effectiveStyle;

    final TextStyle defaultBaseStyle = TextStyle(
      color: Theme.of(context).colorScheme.secondary, // Your default color
    );

    if (style == null) {
      // If no style is passed at all, use our default base style.
      effectiveStyle = defaultBaseStyle;
    } else {
      effectiveStyle = TextStyle().merge(style); // Start with the passed style

      if (style!.color == null) {
        effectiveStyle = effectiveStyle.copyWith(color: Colors.red);
      }
    }

    TextStyle currentStyle = TextStyle(
        color: Theme.of(context).colorScheme.secondary); // Start with default
    if (style != null) {
      currentStyle = currentStyle.merge(style); // Merge passed style
    }

    // Now, if currentStyle.color is still null AFTER merging, it means
    // the original 'style' (if provided) also had a null color.
    // In this specific scenario, apply red.
    if (currentStyle.color == null) {
      currentStyle = currentStyle.copyWith(color: Colors.red);
    }
    // If the 'style' passed (e.g., Theme.of(context).textTheme.labelMedium)
    // already has a color, then currentStyle.color will not be null here,
    // and the red will not be applied. This is the desired behavior based on your clarification.

    return Text(
      context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: textKey),
      style: currentStyle,
      maxLines: maxLines,
      overflow: overflow,
      textAlign: textAlign,
      textDirection: Directionality.of(context),
      
      softWrap: true,
    );
  }
}
