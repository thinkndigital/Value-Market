import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/theme/colors.dart';
import 'package:flutter/material.dart';

import '../models/order.dart';
import '../../../../utils/designConfig.dart';
import '../../../../core/localization/labelKeys.dart';
import '../../../../utils/utils.dart';
import '../../../../commons/widgets/customTextContainer.dart';

class DeliveryAndPriceDetailsContainer extends StatelessWidget {
  final Order order;
  final int selectedItemId;
  DeliveryAndPriceDetailsContainer(
      {Key? key, required this.order, required this.selectedItemId})
      : super(key: key);
  late TextStyle bodyMedtextStyle;
  @override
  Widget build(BuildContext context) {
    bodyMedtextStyle = Theme.of(context).textTheme.bodyMedium!.copyWith(
        color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8));
    return Column(
      children: <Widget>[
        if (order.orderItems!
                .firstWhere((element) => element.id == selectedItemId)
                .productType !=
            digitalProductType) ...[
          deliveryAddressContainer(context, order),
          DesignConfig.smallHeightSizedBox,
        ],
        buildPriceDetailsContainer(context)
      ],
    );
  }

  deliveryAddressContainer(BuildContext context, Order order) {
    return commonContainer(
      context,
      deliveryAddressKey,
      Padding(
          padding: const EdgeInsetsDirectional.symmetric(
              horizontal: appContentHorizontalPadding),
          child: CustomTextContainer(
            textKey: order.address ?? "",
            style: bodyMedtextStyle,
          )),
      prefixIcon: const Icon(
        Icons.location_on_outlined,
      ),
    );
  }

  commonContainer(BuildContext context, String title, Widget content,
      {Widget? prefixIcon}) {
    return Container(
      padding: const EdgeInsetsDirectional.symmetric(
          vertical: appContentVerticalSpace),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (prefixIcon != null) ...[
                  prefixIcon,
                  DesignConfig.smallWidthSizedBox
                ],
                CustomTextContainer(
                  textKey: title,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ],
            ),
          ),
          const Divider(
            height: 12,
            thickness: 0.5,
          ),
          content
        ],
      ),
    );
  }

  buildPriceDetailsContainer(
    BuildContext context,
  ) {
    return commonContainer(
        context,
        priceDetailsKey,
        Column(
          children: <Widget>[
            buildPriceDetailRow(
                itemTotalKey,
                Utils.priceWithCurrencySymbol(
                    price: order.itemTotal ?? 0, context: context)),
            buildPriceDetailRow(
                discountKey,
                order.discount != 0
                    ? '-${Utils.priceWithCurrencySymbol(price: order.discount ?? 0, context: context)}'
                    : Utils.priceWithCurrencySymbol(
                        price: order.discount ?? 0, context: context),
                textStyle: order.discount != 0
                    ? Theme.of(context)
                        .textTheme
                        .bodyMedium!
                        .copyWith(color: successStatusColor)
                    : null),
            buildPriceDetailRow(
                couponDiscountKey,
                order.promoDiscount != 0
                    ? '-${Utils.priceWithCurrencySymbol(price: order.promoDiscount ?? 0, context: context)}'
                    : Utils.priceWithCurrencySymbol(
                        price: order.promoDiscount ?? 0, context: context),
                textStyle: order.promoDiscount != 0
                    ? Theme.of(context)
                        .textTheme
                        .bodyMedium!
                        .copyWith(color: successStatusColor)
                    : null),
            if (order.orderItems!
                    .firstWhere((element) => element.id == selectedItemId)
                    .productType !=
                digitalProductType)
              buildPriceDetailRow(
                deliveryChargesKey,
                order.deliveryCharge != 0
                    ? '+${Utils.priceWithCurrencySymbol(price: order.deliveryCharge ?? 0, context: context)}'
                    : Utils.priceWithCurrencySymbol(
                        price: order.deliveryCharge ?? 0, context: context),
              ),
            if (order.walletBalance != 0)
              buildPriceDetailRow(
                walletBalanceKey,
                '-${Utils.priceWithCurrencySymbol(price: order.walletBalance ?? 0, context: context)}',
              ),
            buildPriceDetailRow(
                totalpayableKey,
                Utils.priceWithCurrencySymbol(
                    price: order.totalPayable ?? 0, context: context),
                textStyle: Theme.of(context).textTheme.titleSmall!),
            const Divider(
              height: 20,
              thickness: 0.5,
            ),
            buildPriceDetailRow(
                totalAmountKey,
                Utils.priceWithCurrencySymbol(
                    price: order.finalTotal ?? 0, context: context),
                textStyle: Theme.of(context)
                    .textTheme
                    .titleMedium!
                    .copyWith(fontWeight: FontWeight.bold)),
          ],
        ));
  }

  buildPriceDetailRow(String title, String value, {TextStyle? textStyle}) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(
          start: appContentHorizontalPadding,
          end: appContentHorizontalPadding,
          bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          CustomTextContainer(
            textKey: title,
            style: textStyle ?? bodyMedtextStyle,
          ),
          CustomTextContainer(
            textKey: value,
            style: textStyle ?? bodyMedtextStyle,
          )
        ],
      ),
    );
  }
}
