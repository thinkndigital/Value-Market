import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';

class TagContainer extends StatelessWidget {
  final String tag;
  final Color? color;
  final double? fontSize;
  const TagContainer(
      {Key? key, required this.tag, this.color, this.fontSize = 12})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
   
      padding: EdgeInsets.all(6),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(borderRadius),
        color: color ?? Theme.of(context).colorScheme.secondary,
      ),
      child: CustomTextContainer(
        textKey: tag,
        style: Theme.of(context).textTheme.labelSmall!.copyWith(
            color: Theme.of(context).colorScheme.onPrimary, fontSize: fontSize),
      ),
    );
  }
}
