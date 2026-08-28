import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/ui/cart/cubits/getUserCart.dart';
import 'package:eshop_plus/ui/cart/cubits/manageCartCubit.dart';
import 'package:eshop_plus/ui/cart/models/cart.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/product/models/productVariant.dart';
import 'package:eshop_plus/ui/cart/widgets/quantitySelector.dart';
import 'package:eshop_plus/core/theme/colors.dart';
import 'package:eshop_plus/commons/widgets/addToCartButton.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class VariantSelector extends StatefulWidget {
  final List<ProductVariant> variants; // List of product variants
  final Product product;
  final bool isFromVariantSelectorPopup;
  final Function onVariantSelected;
  const VariantSelector(
      {Key? key,
      required this.variants,
      required this.product,
      this.isFromVariantSelectorPopup = false,
      required this.onVariantSelected})
      : super(key: key);

  @override
  _VariantSelectorState createState() => _VariantSelectorState();
}

class _VariantSelectorState extends State<VariantSelector> {
  int selectedVariantIndex = -1;
  int quantity = 1;
  late Cart? cart;
  List<ProductVariant> selectedProductVariants = [];
  CartProduct? product;
  @override
  Widget build(BuildContext context) {
    cart = context.read<GetUserCartCubit>().getCartDetail();

    return BlocConsumer<ManageCartCubit, ManageCartState>(
      listener: (context, state) {
        if (state is ManageCartFetchFailure) {
          Navigator.of(context).pop();
        }
      },
      builder: (context, state) {
        return CustomDefaultContainer(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CustomTextContainer(
                  textKey: selectPackKey,
                  style: Theme.of(context).textTheme.titleMedium),
              DesignConfig.defaultHeightSizedBox,
              ListView.separated(
                separatorBuilder: (context, index) =>
                    DesignConfig.smallHeightSizedBox,
                shrinkWrap: true,
                itemCount: widget.variants.length,
                physics: const NeverScrollableScrollPhysics(),
                itemBuilder: (context, index) {
                  final variant = widget.variants[index];
                  if (context
                          .read<GetUserCartCubit>()
                          .getCartDetail()
                          .cartProducts !=
                      null) {
                    product = context
                        .read<GetUserCartCubit>()
                        .getCartDetail()
                        .cartProducts!
                        .firstWhereOrNull(
                          (element) => element.productVariantId == variant.id,
                        );
                  }
                  return GestureDetector(
                    onTap: () {
                      setState(() {
                        widget.product.selectedVariant = variant;
                        widget.onVariantSelected();
                      });
                    },
                    child: Container(
                      padding: const EdgeInsetsDirectional.all(8),
                      decoration: BoxDecoration(
                        color: Theme.of(context).scaffoldBackgroundColor,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          CustomImageWidget(
                            url: variant.images != null &&
                                    variant.images!.isNotEmpty
                                ? variant.images![0]
                                : variant.productImage!,
                            height: 50,
                            width: 50,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                variant.swatcheType == '1'
                                    ? Container(
                                        margin: const EdgeInsets.all(4),
                                        decoration: BoxDecoration(
                                          shape: BoxShape.circle,
                                          color: Utils.hexToColor(
                                              variant.swatcheValue!),
                                        ),
                                        width: 30,
                                        height: 30,
                                      )
                                    : variant.swatcheType == '2'
                                        ? CustomImageWidget(
                                            url: variant.swatcheValue!,
                                            height: 50,
                                            width: 50,
                                          )
                                        : CustomTextContainer(
                                            textKey: variant.variantValues!,
                                            style: Theme.of(context)
                                                .textTheme
                                                .bodyMedium,
                                          ),
                                variant.getPrice() != 0.0
                                    ? Text.rich(TextSpan(children: [
                                        TextSpan(
                                          text: Utils.priceWithCurrencySymbol(
                                              price: variant.getPrice(),
                                              context: context),
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodyMedium,
                                        ),
                                        const TextSpan(text: "  "),
                                        TextSpan(
                                            text: Utils.priceWithCurrencySymbol(
                                                price: variant.getBasePrice(),
                                                context: context),
                                            style: Theme.of(context)
                                                .textTheme
                                                .bodySmall
                                                ?.copyWith(
                                                    decoration: TextDecoration
                                                        .lineThrough,
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .secondary
                                                        .withValues(
                                                            alpha: 0.67))),
                                      ]))
                                    : CustomTextContainer(
                                        textKey: Utils.priceWithCurrencySymbol(
                                            price: variant.getBasePrice(),
                                            context: context),
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodyMedium,
                                      ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 10),
                          widget.product.isVariantOutOfStock(variant)
                              ? CustomTextContainer(
                                  textKey: outOfStockKey,
                                  style: Theme.of(context)
                                      .textTheme
                                      .labelSmall!
                                      .copyWith(color: cancelledStatusColor))
                              : BlocBuilder<GetUserCartCubit, GetUserCartState>(
                                  builder: (context, state) {
                                    if (state is GetUserCartFetchSuccess &&
                                        state.cart.cartProducts != null) {
                                      product = state.cart.cartProducts!
                                          .firstWhereOrNull((element) =>
                                              element.productVariantId ==
                                              variant.id);
                                    }
                                    if (product != null) {
                                      return QuantitySelector(
                                        initialQuantity: product!.qty ??
                                            product!.minimumOrderQuantity ??
                                            1,
                                        minimumOrderQuantity:
                                            product!.minimumOrderQuantity ?? 1,
                                        quantityStepSize:
                                            product!.quantityStepSize ?? 1,
                                        maximumAllowedQuantity:
                                            product!.totalAllowedQuantity ?? 1,
                                        product: product!,
                                        primaryTheme: true,
                                        stock: variant.stock!,
                                        stockType: widget.product.stockType!,
                                      );
                                    } else {
                                      return AddToCartButton(
                                          storeId: widget.product.storeId!,
                                          widthPercentage: 0.2,
                                          height: 32,
                                          title: addKey,
                                          productId: widget.product.type ==
                                                  comboProductType
                                              ? widget.product.id!
                                              : variant.id!,
                                          type: widget.product.type ==
                                                  comboProductType
                                              ? comboType
                                              : regularType,
                                          stockType: widget.product.stockType!,
                                          stock: variant.stock!,
                                          productType:
                                              widget.product.productType!,
                                          sellerId: widget.product.sellerId!,
                                          qty: widget
                                              .product.minimumOrderQuantity,
                                          reloadCart: false,
                                          isFromVariantSelectorPopup: widget
                                              .isFromVariantSelectorPopup);
                                    }
                                  },
                                )
                        ],
                      ),
                    ),
                  );
                },
              ),
              if (selectedVariantIndex != -1)
                Container(
                  color: Colors.blue,
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Item total: \$${widget.variants[selectedVariantIndex].specialPrice! * quantity}',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                        ),
                      ),
                      ElevatedButton(
                        onPressed: () {
                          // Confirm and add to cart logic here
                        },
                        child: const Text("Confirm"),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}
