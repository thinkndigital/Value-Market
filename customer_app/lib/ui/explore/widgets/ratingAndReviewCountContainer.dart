import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';

class RatingAndReviewCountContainer extends StatelessWidget {
  final String rating;
  final String ratingCount;
  final Color? textColor;

  const RatingAndReviewCountContainer(
      {super.key,
      required this.rating,
      required this.ratingCount,
      this.textColor});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding:
          const EdgeInsetsDirectional.symmetric(horizontal: 2.5, vertical: 2.5),
      decoration: BoxDecoration(
        border: textColor != null
            ? null
            : Border.all(
                color: Theme.of(context)
                    .colorScheme
                    .secondary
                    .withValues(alpha: 0.67),
                width: 0.5),
        color: textColor == null
            ? Theme.of(context).colorScheme.primaryContainer
            : textColor!.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(3.0),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.star,
            size: 12.0,
            color: textColor ?? Theme.of(context).colorScheme.primary),
        const SizedBox(
          width: 2.5,
        ),
        CustomTextContainer(
          textKey: rating,
          style: Theme.of(context).textTheme.labelSmall!.copyWith(
              color: textColor ?? Theme.of(context).colorScheme.secondary),
        ),
        if (ratingCount.isNotEmpty) ...[
          const SizedBox(
            width: 2.5,
          ),
          Container(
            width: 1.0,
            height: 10.0,
            color: textColor ?? Theme.of(context).colorScheme.secondary,
          ),
          const SizedBox(
            width: 2.5,
          ),
          CustomTextContainer(
              textKey: ratingCount,
              style: Theme.of(context).textTheme.labelSmall!.copyWith(
                    color: textColor ?? Theme.of(context).colorScheme.secondary,
                  )),
          const SizedBox(
            width: 2.5,
          ),
        ]
      ]),
    );
  }
}
