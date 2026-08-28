import 'dart:io';

import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/features/product/blocs/deleteProductCubit.dart';
import 'package:eshopplus_seller/features/product/blocs/updateProductSatusCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/features/product/widgets/comboProductList.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../commons/models/product.dart';
import '../../../utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customBottomButtonContainer.dart';
import '../../../commons/widgets/customCuperinoSwitch.dart';
import '../../../commons/widgets/customDefaultContainer.dart';
import '../../../commons/widgets/customRoundedButton.dart';
import '../../../commons/widgets/customTextContainer.dart';
import 'productInfoContainer.dart';

class ProductDetailsTab extends StatefulWidget {
  final Product product;
  final ProductsCubit? productsCubit;
  const ProductDetailsTab({Key? key, required this.product, this.productsCubit})
      : super(key: key);

  @override
  _ProductDetailsTabState createState() => _ProductDetailsTabState();
}

class _ProductDetailsTabState extends State<ProductDetailsTab> {
  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 28),
          child: Column(
            children: [
              Expanded(
                child: ListView(
                  shrinkWrap: true,
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: <Widget>[
                    DesignConfig.smallHeightSizedBox,
                    ProductInfoContainer(
                      product: widget.product,
                      getValueList: Utils.getProductStatusTextAndColor,
                      isProductDetailScreen: true,
                    ),
                    DesignConfig.smallHeightSizedBox,
                    buildProductStatus(),
                    if (widget.product.type == comboProductType) ...[
                      DesignConfig.smallHeightSizedBox,
                      ComboProductList(product: widget.product),
                    ],
                    DesignConfig.smallHeightSizedBox,
                    buildProductDetailSection(),
                    DesignConfig.smallHeightSizedBox,
                    if ((widget.product.description != null &&
                            widget.product.description!.isNotEmpty) ||
                        (widget.product.extraDescription != null &&
                            widget.product.extraDescription!.isNotEmpty))
                      buildProductDescriptionSection(),
                  ],
                ),
              ),
            ],
          ),
        ),
        buildBottomBar(),
      ],
    );
  }

  buildProductStatus() {
    return CustomDefaultContainer(
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          buildTitle(productStatusKey),
          BlocConsumer<UpdateProductStatusCubit, UpdateProductStatusState>(
            listener: (context, state) {
              if (state is UpdateProductStatusSuccess) {
                widget.product.changeStatus(state.status);
                setState(() {});
                List<Product> products = [];

                int total = 0;

                Utils.showSnackBar(message: state.successMessage);
                ProductsCubit? productsCubit = widget.productsCubit;

                if (productsCubit != null) {
                  products =
                      (productsCubit.state as ProductsFetchSuccess).products;
                  total = (productsCubit.state as ProductsFetchSuccess).total;

                  productsCubit.emitSuccessState(products, total);
                }
              }
              if (state is UpdateProductStatusFailure) {
                Utils.showSnackBar(message: state.errorMessage);
              }
            },
            builder: (context, state) {
              return SizedBox(
                height: 30,
                child: FittedBox(
                    child: state is UpdateProductStatusProgress
                        ? CustomCircularProgressIndicator(
                            indicatorColor:
                                Theme.of(context).colorScheme.primary)
                        : CustomCuperinoSwitch(
                            value: widget.product.status == 1,
                            onChanged: (value) {
                              if (isDemoApp) {
                                Utils.showSnackBar(message: demoModeOnKey);
                                return;
                              }
                              setState(() {});
                              if (state is! UpdateProductStatusProgress) {
                                context
                                    .read<UpdateProductStatusCubit>()
                                    .updateProductStatus(params: {
                                  ApiURL.storeIdApiKey: widget.product.storeId,
                                  ApiURL.productIdApiKey: widget.product.id,
                                  ApiURL.statusApiKey: value == true ? 1 : 0,
                                });
                              }
                            })),
              );
            },
          )
        ],
      ),
    );
  }

  buildProductDetailSection() {
    return CustomDefaultContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          buildTitle(productsDetailsKey),
          DesignConfig.defaultHeightSizedBox,
          buildListTile(nameKey, widget.product.name ?? ''),
          if (widget.product.productType == variableProductType)
            ...widget.product.attributes!
                .map((variant) => buildListTile(
                    context
                        .read<SettingsAndLanguagesCubit>()
                        .getTranslatedValue(labelKey: variant.attrName ?? ''),
                    variant.value ?? ''))
                .toList(),
          if (widget.product.madeIn != null &&
              widget.product.madeIn!.isNotEmpty)
            buildListTile(countryOfOriginKey, widget.product.madeIn!),
          if (widget.product.shortDescription != null) ...[
            Utils.buildDescription(widget.product.shortDescription ?? ''),
          ],
        ],
      ),
    );
  }

  buildProductDescriptionSection() {
    return CustomDefaultContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (widget.product.description != null &&
              widget.product.description!.isNotEmpty) ...[
            CustomTextContainer(
              textKey: descriptionKey,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            Utils.buildDescription(widget.product.description ?? ''),
          ],
          if (widget.product.extraDescription != null &&
              widget.product.extraDescription!.isNotEmpty) ...[
            DesignConfig.smallHeightSizedBox,
            CustomTextContainer(
              textKey: extraDescKey,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            Utils.buildDescription(widget.product.extraDescription ?? '')
          ]
        ],
      ),
    );
  }

  CustomTextContainer buildTitle(String title) {
    return CustomTextContainer(
        textKey: title, style: Theme.of(context).textTheme.titleMedium);
  }

  buildListTile(String title, String value) {
    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(vertical: 4),
      child: Text.rich(
        TextSpan(
          style: Theme.of(context).textTheme.bodyMedium!.copyWith(
              color: Theme.of(context)
                  .colorScheme
                  .secondary
                  .withValues(alpha: 0.8)),
          children: [
            TextSpan(
              text:
                  context.read<SettingsAndLanguagesCubit>().getTranslatedValue(
                        labelKey: title,
                      ),
            ),
            const TextSpan(
              text: ' : ',
            ),
            TextSpan(
              text:
                  context.read<SettingsAndLanguagesCubit>().getTranslatedValue(
                        labelKey: value,
                      ),
            ),
          ],
        ),
      ),
    );
  }

  buildBottomBar() {
    return Positioned(
      bottom: 0,
      child: SizedBox(
        width: MediaQuery.of(context).size.width,
        height: 65,
        child: CustomBottomButtonContainer(
          bottomPadding: Platform.isIOS ? 10 : 0,
          child: Row(
            children: [
              Expanded(
                child: BlocConsumer<DeleteProductCubit, DeleteProductState>(
                  listener: (context, state) {
                    if (state is ProductDeleted) {
                      //we will remove the deleted product from the list
                      Utils.showSnackBar(message: state.successMessage);

                      ProductsCubit? productsCubit = widget.productsCubit;

                      if (productsCubit != null) {
                        List<Product> products =
                            (productsCubit.state as ProductsFetchSuccess)
                                .products;
                        int total =
                            (productsCubit.state as ProductsFetchSuccess).total;
                        products.removeWhere(
                            (element) => element.id == widget.product.id);
                        productsCubit.emitSuccessState(products, total - 1);
                      }

                      Navigator.of(context).pop();
                    }
                    if (state is DeleteProductFailure) {
                      Utils.showSnackBar(message: state.errorMessage);
                    }
                  },
                  builder: (context, state) {
                    return CustomRoundedButton(
                        widthPercentage: 0.4,
                        buttonTitle: deleteKey,
                        showBorder: true,
                        backgroundColor:
                            Theme.of(context).colorScheme.onPrimary,
                        borderColor: Theme.of(context).hintColor,
                        style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                            color: Theme.of(context).colorScheme.secondary),
                        child: state is DeleteProductProgress
                            ? CustomCircularProgressIndicator(
                                indicatorColor:
                                    Theme.of(context).colorScheme.secondary,
                              )
                            : null,
                        onTap: () {
                          if (isDemoApp) {
                            Utils.showSnackBar(message: demoModeOnKey);
                            return;
                          }
                          Utils.openAlertDialog(context, onTapYes: () {
                            if (state is! DeleteProductProgress) {
                              context
                                  .read<DeleteProductCubit>()
                                  .deleteProduct(params: {
                                ApiURL.productIdApiKey: widget.product.id,
                                ApiURL.storeIdApiKey: context
                                    .read<StoresCubit>()
                                    .getDefaultStore()
                                    .id,
                              });
                              Navigator.of(context).pop();
                            }
                          }, message: deleteProductWarningKey);
                        });
                  },
                ),
              ),
              const SizedBox(
                width: 16,
              ),
              Expanded(
                child: CustomRoundedButton(
                  widthPercentage: 0.4,
                  buttonTitle: editKey,
                  showBorder: false,
                  onTap: () {
                    return Utils.navigateToScreen(
                        context, Routes.addProductScreen,
                        arguments: {
                          'product': widget.product,
                          'isAddEditCombo':
                              widget.product.type == comboProductType
                        });
                  },
                ),
              )
            ],
          ),
        ),
      ),
    );
  }
}
