import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/ui/cart/cubits/getUserCart.dart';
import 'package:value_market_customer/ui/cart/cubits/manageCartCubit.dart';
import 'package:value_market_customer/ui/cart/cubits/placeOrderCubit.dart';
import 'package:value_market_customer/ui/cart/cubits/removeProductFromCartCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/ui/profile/address/models/address.dart';
import 'package:value_market_customer/commons/product/models/product.dart';
import 'package:value_market_customer/ui/cart/widgets/cartProductList.dart';
import 'package:value_market_customer/ui/cart/widgets/priceDetailContainer.dart';
import 'package:value_market_customer/commons/widgets/customTextButton.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_customer/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class FinalCartScreen extends StatefulWidget {
  final Function(int)? onInstAdded;
  final PlaceOrderState placeOrderState;
  final int storeId;
  const FinalCartScreen(
      {Key? key,
      this.onInstAdded,
      required this.placeOrderState,
      required this.storeId})
      : super(key: key);

  @override
  _FinalCartScreenState createState() => _FinalCartScreenState();
}

class _FinalCartScreenState extends State<FinalCartScreen> {
  late TextStyle bodyMedtextStyle;
  @override
  void initState() {
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    bodyMedtextStyle = Theme.of(context).textTheme.bodyMedium!.copyWith(
        color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8));
    return BlocProvider(
      create: (context) => RemoveFromCartCubit(),
      child: BlocListener<ManageCartCubit, ManageCartState>(
        listener: (context, state) {
          if (state is ManageCartFetchSuccess) {
            // if we are reloading cart, we need to get the user cart otherwise we will get the cart from the managecartstate
            if ((state.cart.cartProducts == null ||
                state.cart.cartProducts!.isEmpty)) {
              Utils.showSnackBar(
                  message: emptyCartErrorMessageKey, context: context);
              Navigator.of(context).pop();
            }
          }
        },
        child: BlocConsumer<GetUserCartCubit, GetUserCartState>(
          listener: (context, state) {
            //here we are checking if place order is  success or not, bcoz if its success we will move to order confirmed screen

            if (state is GetUserCartFetchSuccess &&
                widget.placeOrderState is! PlaceOrderSuccess) {
              if ((state.cart.cartProducts == null ||
                  state.cart.cartProducts!.isEmpty)) {
                Utils.showSnackBar(
                    message: emptyCartErrorMessageKey, context: context);
                Navigator.of(context).pop();
              }
            }
          },
          builder: (context, state) {
            if (state is GetUserCartFetchSuccess &&
                state.cart.cartProducts != null &&
                state.cart.cartProducts!.isNotEmpty) {
              return SingleChildScrollView(
                padding: const EdgeInsetsDirectional.symmetric(vertical: 12),
                child: Column(
                  children: <Widget>[
                    CartProductList(
                      cart: context.read<GetUserCartCubit>().getCartDetail(),
                      isFinalCartScreen: true,
                      removeFromCartCubit: context.read<RemoveFromCartCubit>(),
                    ),
                    DesignConfig.smallHeightSizedBox,

                    if (state.cart.cartProducts![0].type != digitalProductType)
                      deliveryAddressContainer(),
                    // deliveryEstimateContainer(),
                    paymentModeContainer(),
                    PriceDetailContainer(
                        cart: context.read<GetUserCartCubit>().getCartDetail())
                  ],
                ),
              );
            }
            return const SizedBox.shrink();
          },
        ),
      ),
    );
  }

  deliveryAddressContainer() {
    Address address =
        context.read<GetUserCartCubit>().getCartDetail().selectedAddress ??
            Address();
    return commonContainer(
        context,
        deliveryAddressKey,
        Padding(
          padding: const EdgeInsetsDirectional.symmetric(
              horizontal: appContentHorizontalPadding),
          child: Column(
            children: [
              Utils.getAddressWidget(context, address),
              // if (context
              //         .read<GetUserCartCubit>()
              //         .getCartDetail()
              //         .deliveryInstruction ==
              //     null)
              Padding(
                padding: EdgeInsetsDirectional.only(top: 16, bottom: 4),
                child: CustomTextButton(
                    buttonTextKey: addDeliveryInstructionKey,
                    textStyle: Theme.of(context)
                        .textTheme
                        .titleMedium!
                        .copyWith(color: Theme.of(context).colorScheme.primary),
                    onTapButton: () {
                      TextEditingController instructionController =
                          TextEditingController(
                              text: context
                                  .read<GetUserCartCubit>()
                                  .getCartDetail()
                                  .deliveryInstruction);
                      Utils.openModalBottomSheet(
                          context,
                          FilterContainerForBottomSheet(
                            title: addDeliveryInstructionKey,
                            borderedButtonTitle: '',
                            primaryButtonTitle: submitKey,
                            borderedButtonOnTap: () {},
                            primaryButtonOnTap: () {
                              if (instructionController.text.isNotEmpty) {
                                context
                                    .read<GetUserCartCubit>()
                                    .addDeliveryInstruction(
                                        instructionController.text);
                                Navigator.of(context).pop();
                              }
                            },
                            content: CustomTextFieldContainer(
                              hintTextKey: typeKey,
                              textEditingController: instructionController,
                              maxLines: 5,
                            ),
                          ),
                          staticContent: true,
                          isScrollControlled: true);
                    }),
              )
            ],
          ),
        ),
        prefixIcon: const Icon(
          Icons.location_on_outlined,
        ),
        suffixIcon: CustomTextButton(
          onTapButton: () {
            widget.onInstAdded?.call(1);
          },
          buttonTextKey: changekey,
          textStyle: Theme.of(context)
              .textTheme
              .bodyMedium!
              .copyWith(color: Theme.of(context).colorScheme.primary),
        ));
  }

  commonContainer(BuildContext context, String title, Widget content,
      {Widget? prefixIcon, Widget? suffixIcon}) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: 8.0),
      child: Container(
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
                  Expanded(
                    child: CustomTextContainer(
                      textKey: title,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  if (suffixIcon != null) suffixIcon,
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
      ),
    );
  }

  paymentModeContainer() {
    if (context
            .read<GetUserCartCubit>()
            .getCartDetail()
            .selectedPaymentMethod !=
        null) {
      return commonContainer(
          context,
          paymentModeKey,
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: GestureDetector(
              onTap: () => widget.onInstAdded?.call(2),
              child: Row(
                children: <Widget>[
                  Expanded(
                      child: CustomTextContainer(
                          textKey: paymentGatewayDisplayNames[context
                              .read<GetUserCartCubit>()
                              .getCartDetail()
                              .selectedPaymentMethod!
                              .name
                              .toString()]!)),
                  const Icon(Icons.arrow_forward_ios)
                ],
              ),
            ),
          ));
    } else {
      return const SizedBox.shrink();
    }
  }

  deliveryEstimateContainer() {
    return commonContainer(
        context,
        deliveryEstimatesKey,
        Padding(
          padding: const EdgeInsetsDirectional.symmetric(
              horizontal: appContentHorizontalPadding),
          child: Text.rich(
            TextSpan(
              children: [
                TextSpan(
                  text: context
                      .read<SettingsAndLanguagesCubit>()
                      .getTranslatedValue(labelKey: estimatedDeliveryByKey),
                  style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .secondary
                          .withValues(alpha: 0.8)),
                ),
                const TextSpan(text: " "),
                TextSpan(
                    text: '2024',
                    style: Theme.of(context).textTheme.titleMedium!),
              ],
            ),
            textAlign: TextAlign.center,
          ),
        ),
        prefixIcon: const Icon(
          Icons.delivery_dining_outlined,
        ));
  }
}
