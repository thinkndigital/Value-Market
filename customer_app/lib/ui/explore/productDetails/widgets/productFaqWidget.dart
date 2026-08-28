import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/ui/profile/faq/models/faq.dart';
import 'package:flutter/material.dart';

class ProductFaqWidget extends StatelessWidget {
  const ProductFaqWidget({
    super.key,
    required this.faq,
  });

  final FAQ faq;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(border: Border.all(color: Colors.transparent)),
      padding: const EdgeInsetsDirectional.symmetric(
          horizontal: appContentHorizontalPadding),
      child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text.rich(
              TextSpan(
                style: Theme.of(context).textTheme.titleSmall!,
                children: [
                  const TextSpan(text: 'Q'),
                  const TextSpan(
                    text: ' : ',
                  ),
                  TextSpan(
                    text: faq.question,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 4),
            Text.rich(
              TextSpan(
                style: Theme.of(context).textTheme.bodyMedium!,
                children: [
                  const TextSpan(text: 'A'),
                  const TextSpan(
                    text: ' : ',
                  ),
                  TextSpan(
                    text: faq.answer,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 4),
            Text.rich(
              TextSpan(
                style: Theme.of(context).textTheme.bodySmall!.copyWith(
                    color: Theme.of(context)
                        .colorScheme
                        .secondary
                        .withValues(alpha: 0.80)),
                children: [
                  TextSpan(text: faq.answeredBy),
                  const TextSpan(
                    text: ' | ',
                  ),
                  TextSpan(
                    text: faq.createdAt,
                  ),
                ],
              ),
            ),
          ]),
    );
  }
}
