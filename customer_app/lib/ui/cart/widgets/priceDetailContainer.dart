import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/ui/cart/cubits/getUserCart.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/ui/cart/models/cart.dart';
import 'package:value_market_customer/commons/product/models/product.dart';
import 'package:value_market_customer/core/theme/colors.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class PriceDetailContainer extends StatelessWidget {
  final Cart cart;
  PriceDetailContainer({
    Key? key,
    required this.cart,
  }) : super(key: key);
  TextStyle? bodyMedtextStyle;
  @override
  Widget build(BuildContext context) {
    bodyMedtextStyle = Theme.of(context).textTheme.bodyMedium!.copyWith(
        color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8));
    return BlocBuilder<GetUserCartCubit, GetUserCartState>(
      builder: (context, state) {
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
                  child: CustomTextContainer(
                    textKey:
                        '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: priceDetailsKey)} (${cart.cartProducts!.length} Items)',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                const Divider(
                  height: 12,
                  thickness: 0.5,
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    buildPriceDetailRow(
                        '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: itemTotalKey)} (${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: taxAlreadyIncludedKey)})',
                        Utils.priceWithCurrencySymbol(
                            price: cart.itemTotal ?? 0, context: context)),
                    buildPriceDetailRow(
                        discountKey,
                        cart.discount != 0
                            ? '-${Utils.priceWithCurrencySymbol(price: cart.discount ?? 0, context: context)}'
                            : Utils.priceWithCurrencySymbol(
                                price: cart.discount ?? 0, context: context),
                        textStyle: cart.discount != 0
                            ? Theme.of(context)
                                .textTheme
                                .bodyMedium!
                                .copyWith(color: successStatusColor)
                            : null),
                    buildPriceDetailRow(
                        couponDiscountKey,
                        cart.promoCode != null &&
                                cart.promoCode!.isCashback == 1
                            ? Utils.priceWithCurrencySymbol(
                                price: 0, context: context)
                            : cart.couponDiscount != 0
                                ? '-${Utils.priceWithCurrencySymbol(price: cart.couponDiscount ?? 0, context: context)}'
                                : Utils.priceWithCurrencySymbol(
                                    price: cart.couponDiscount ?? 0,
                                    context: context),
                        textStyle: cart.promoCode != null &&
                                cart.promoCode!.isCashback == 0 &&
                                cart.couponDiscount != 0
                            ? Theme.of(context)
                                .textTheme
                                .bodyMedium!
                                .copyWith(color: successStatusColor)
                            : null),
                    if (cart.cartProducts![0].type != digitalProductType &&
                        context
                                .read<GetUserCartCubit>()
                                .getCartDetail()
                                .selectedAddress !=
                            null)
                      buildPriceDetailRow(
                        deliveryChargesKey,
                        cart.deliveryCharge != 0
                            ? '+${Utils.priceWithCurrencySymbol(price: cart.deliveryCharge ?? 0, context: context)}'
                            : Utils.priceWithCurrencySymbol(
                                price: cart.deliveryCharge ?? 0,
                                context: context),
                      ),
                    if (cart.useWalletBalance == true)
                      buildPriceDetailRow(
                        walletBalanceKey,
                        cart.walletAmount != 0
                            ? '-${Utils.priceWithCurrencySymbol(price: cart.walletAmount ?? 0, context: context)}'
                            : Utils.priceWithCurrencySymbol(
                                price: cart.walletAmount ?? 0,
                                context: context),
                      ),
                    const Divider(
                      height: 20,
                      thickness: 0.5,
                    ),
                    buildPriceDetailRow(
                        totalAmountKey,
                        Utils.priceWithCurrencySymbol(
                            price: cart.overallAmount ?? 0, context: context),
                        textStyle: Theme.of(context).textTheme.titleSmall!),
                    Padding(
                      padding: const EdgeInsets.symmetric(
                          horizontal: appContentHorizontalPadding),
                      child: Column(
                        children: [
                          if (cart.promoCode != null &&
                              cart.promoCode!.isCashback == 1)
                            Padding(
                              padding:
                                  const EdgeInsets.symmetric(vertical: 8.0),
                              child: CustomTextContainer(
                                  textKey:
                                      '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: couponDiscountKey)} : ${cart.couponDiscount} will be added to your wallet after your order is successfully delivered.'),
                            ),
                          if (context
                                  .read<GetUserCartCubit>()
                                  .getCartDetail()
                                  .selectedAddress ==
                              null)
                            Padding(
                              padding:
                                  const EdgeInsets.symmetric(vertical: 8.0),
                              child: CustomTextContainer(
                                  textKey: deliveryChargeNoteKey),
                            )
                        ],
                      ),
                    ),
                  ],
                )
              ]),
        );
      },
    );
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
            style: bodyMedtextStyle,
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
