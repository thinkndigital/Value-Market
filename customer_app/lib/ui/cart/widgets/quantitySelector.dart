import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/ui/cart/cubits/getUserCart.dart';
import 'package:value_market_customer/ui/cart/cubits/manageCartCubit.dart';
import 'package:value_market_customer/ui/cart/models/cart.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class QuantitySelector extends StatefulWidget {
  final int initialQuantity;
  final int minimumOrderQuantity;
  final int maximumAllowedQuantity;
  final String stockType;
  final String stock;
  final int quantityStepSize;
  final CartProduct product;
  final bool primaryTheme;

  const QuantitySelector({
    Key? key,
    required this.initialQuantity,
    required this.minimumOrderQuantity,
    required this.maximumAllowedQuantity,
    required this.quantityStepSize,
    required this.stockType,
    required this.stock,
    required this.product,
    required this.primaryTheme,
  }) : super(key: key);

  @override
  _QuantitySelectorState createState() => _QuantitySelectorState();
}

class _QuantitySelectorState extends State<QuantitySelector> {
  late int _currentQuantity;

  @override
  void initState() {
    super.initState();
    _currentQuantity = widget.initialQuantity;
  }

  void _increaseQuantity() {
    if (widget.stockType != "" &&
        int.parse(widget.stock) < _currentQuantity + widget.quantityStepSize) {
      Utils.showSnackBar(context: context, message: stockLimitReachedKey);
      return;
    }
    if (widget.maximumAllowedQuantity == 0 ||
        _currentQuantity + widget.quantityStepSize <=
            widget.maximumAllowedQuantity) {
      setState(() {
        _currentQuantity += widget.quantityStepSize;
      });
      onQuantityChanged(_currentQuantity);
    } else {
      Utils.showSnackBar(context: context, message: maxQuantityReachedKey);
      return;
    }
  }

  void _decreaseQuantity() {
    if (_currentQuantity - widget.quantityStepSize >=
        widget.minimumOrderQuantity) {
      setState(() {
        _currentQuantity -= widget.quantityStepSize;
      });
      onQuantityChanged(_currentQuantity);
    } else {
      Utils.showSnackBar(context: context, message: minQuantityReachedKey);
      return;
    }
  }

  onQuantityChanged(int newQty) {
    context.read<ManageCartCubit>().manageUserCart(
        widget.product.cartProductType == comboType
            ? widget.product.id
            : widget.product.productVariantId,
        reloadCart: false,
        changeQuantity: true,
        params: {
          ApiURL.storeIdApiKey: widget.product.storeId,
          ApiURL.productVariantIdApiKey:
              widget.product.cartProductType == comboType
                  ? widget.product.id
                  : widget.product.productVariantId,
          ApiURL.productTypeApiKey: widget.product.cartProductType == comboType
              ? comboType
              : regularType,
          ApiURL.isSavedForLaterApiKey: 0,
          ApiURL.qtyApiKey: newQty,
          ApiURL.addressIdApiKey: context
                      .read<GetUserCartCubit>()
                      .getCartDetail()
                      .selectedAddress !=
                  null
              ? context
                  .read<GetUserCartCubit>()
                  .getCartDetail()
                  .selectedAddress!
                  .id!
              : '',
        });
  }

  @override
  Widget build(BuildContext context) {
    Color fontColor = widget.primaryTheme
        ? Theme.of(context).colorScheme.onPrimary
        : Theme.of(context).colorScheme.secondary;
    return Container(
      height: 26,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: widget.primaryTheme
            ? Theme.of(context).colorScheme.primary
            : Theme.of(context).scaffoldBackgroundColor,
        border: Border.all(
            width: 0.5,
            color: widget.primaryTheme
                ? Theme.of(context).colorScheme.onPrimary
                : Theme.of(context).inputDecorationTheme.iconColor!),
        borderRadius: BorderRadius.circular(4.0),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          BlocBuilder<ManageCartCubit, ManageCartState>(
            builder: (context, state) {
              return GestureDetector(
                onTapDown: (_) {
                  if (state is ManageCartFetchInProgress &&
                      state.cartProductId ==
                          (widget.product.cartProductType == comboType
                              ? widget.product.id
                              : widget.product.productVariantId)) return;
                  _decreaseQuantity();
                },
                child: IconButton(
                  visualDensity: VisualDensity(vertical: -4, horizontal: -2),
                  padding: EdgeInsets.zero,
                  icon: Icon(
                    Icons.remove,
                    color: _currentQuantity - widget.quantityStepSize >=
                            widget.minimumOrderQuantity
                        ? fontColor
                        : Colors.grey,
                  ),
                  onPressed: () {
                    if (state is ManageCartFetchInProgress &&
                        state.cartProductId ==
                            (widget.product.cartProductType == comboType
                                ? widget.product.id
                                : widget.product.productVariantId)) return;
                    _decreaseQuantity();
                  },
                ),
              );
            },
          ),
          Text('$_currentQuantity',
              style: Theme.of(context)
                  .textTheme
                  .bodyMedium!
                  .copyWith(color: fontColor)),
          BlocBuilder<ManageCartCubit, ManageCartState>(
            builder: (context, state) {
              return GestureDetector(
                onTapDown: (_) {
                  if (state is ManageCartFetchInProgress &&
                      state.cartProductId ==
                          (widget.product.cartProductType == comboType
                              ? widget.product.id
                              : widget.product.productVariantId)) return;
                  _increaseQuantity();
                },
                child: IconButton(
                    visualDensity: VisualDensity(vertical: -4, horizontal: -2),
                    padding: EdgeInsets.zero,
                    icon: Icon(
                      Icons.add,
                      color: widget.maximumAllowedQuantity == 0 ||
                              _currentQuantity + widget.quantityStepSize <=
                                  widget.maximumAllowedQuantity
                          ? fontColor
                          : Colors.grey,
                    ),
                    onPressed: () {
                      if (state is ManageCartFetchInProgress &&
                          state.cartProductId ==
                              (widget.product.cartProductType == comboType
                                  ? widget.product.id
                                  : widget.product.productVariantId)) return;
                      _increaseQuantity();
                    }),
              );
            },
          ),
        ],
      ),
    );
  }
}
