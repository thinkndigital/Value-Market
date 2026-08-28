import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';

class OfferContainer extends StatelessWidget {
  final String title;

  const OfferContainer({Key? key, required this.title}) : super(key: key);
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, Routes.promoCodeScreen,
          arguments: {'fromCartScreen': false}),
      child: Padding(
        padding: const EdgeInsets.only(bottom: 8.0),
        child: CustomDefaultContainer(
            child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
                child: CustomTextContainer(
              textKey: title,
              style: Theme.of(context).textTheme.titleMedium,
            )),
            const Icon(Icons.arrow_forward_ios, size: 24)
          ],
        )),
      ),
    );
  }
}
