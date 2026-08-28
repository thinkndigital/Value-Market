import 'dart:async';

import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/models/productVariant.dart';
import 'package:eshopplus_seller/commons/widgets/customDropDownContainer.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/models/product.dart';

import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/commons/widgets/customStatusContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/features/mainScreen.dart';
import 'package:eshopplus_seller/features/profile/stockManagement/blocs/manageStockCubit.dart';
import 'package:eshopplus_seller/features/profile/stockManagement/widgets/stockManagementHelper.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../utils/designConfig.dart';
import '../../../../utils/utils.dart';

class StockManagementScreen extends StatefulWidget {
  final dynamic sortByParams;
  final dynamic filterParams;
  final String searchText;
  final bool isComboProductScreen;
  const StockManagementScreen({
    Key? key,
    required this.searchText,
    required this.sortByParams,
    required this.filterParams,
    required this.isComboProductScreen,
  }) : super(key: key);

  @override
  _StockManagementScreenState createState() => _StockManagementScreenState();
}

class _StockManagementScreenState extends State<StockManagementScreen>
    with AutomaticKeepAliveClientMixin<StockManagementScreen> {
  @override
  bool get wantKeepAlive => true;
  Map<String, TextEditingController> controllers = {};
  Map<String, FocusNode> focusNodes = {};
  final List formFields = [
    currentStockKey,
    quantityKey,
    typeKey,
  ];

  @override
  void initState() {
    super.initState();
    for (var key in formFields) {
      controllers[key] = TextEditingController();
      focusNodes[key] = FocusNode();
    }
    Future.delayed(Duration.zero, () {
      getProducts();
    });
  }

  void getProducts() {
    final sortByParams = widget.sortByParams;
    final filterParams = widget.filterParams;
    context.read<ProductsCubit>().getProducts(
        storeId: context.read<StoresCubit>().getDefaultStore().id!,
        sortBy: sortByParams.sortBy,
        orderBy: sortByParams.orderBy,
        topRatedProduct: sortByParams.topRatedProduct,
        flag: filterParams.flag,
        type: filterParams.type,
        searchText: widget.searchText,
        showOnlyStockroducts: 1,
        showOnlyActiveProducts: 1,
        isComboProduct: widget.isComboProductScreen);
  }

  void loadMoreProducts() {
    final sortByParams = widget.sortByParams;
    final filterParams = widget.filterParams;
    context.read<ProductsCubit>().loadMore(
        storeId: context.read<StoresCubit>().getDefaultStore().id!,
        sortBy: sortByParams.sortBy,
        orderBy: sortByParams.orderBy,
        topRatedProduct: sortByParams.topRatedProduct,
        flag: filterParams.flag,
        type: filterParams.type,
        searchText: widget.searchText,
        showOnlyStockroducts: 1,
        isComboProduct: widget.isComboProductScreen);
  }

  @override
  void dispose() {
    controllers.forEach((key, controller) {
      controller.dispose();
    });
    focusNodes.forEach((key, focusNode) {
      focusNode.dispose();
    });
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Column(
      children: [
        const SizedBox(height: 12),
        Expanded(
          child: Padding(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: BlocBuilder<ProductsCubit, ProductsState>(
              builder: (context, state) {
                if (state is ProductsFetchSuccess) {
                  return RefreshIndicator(
                    onRefresh: () async {
                      getProducts();
                    },
                    child: NotificationListener<ScrollUpdateNotification>(
                      onNotification: (notification) {
                        if (notification.metrics.pixels ==
                            notification.metrics.maxScrollExtent) {
                          if (context.read<ProductsCubit>().hasMore()) {
                            loadMoreProducts();
                          }
                        }
                        return true;
                      },
                      child: ListView.separated(
                          separatorBuilder: (context, index) => state
                                          .products[index].type ==
                                      variableProductType &&
                                  (state.products[index].variants == null ||
                                      state.products[index].variants!.isEmpty)
                              ? SizedBox.shrink()
                              : DesignConfig.defaultHeightSizedBox,
                          padding: const EdgeInsets.only(top: 50),
                          itemCount: state.products.length,
                          shrinkWrap: true,
                          physics: const AlwaysScrollableScrollPhysics(),
                          itemBuilder: (context, index) {
                            final product = state.products[index];
                            if (context.read<ProductsCubit>().hasMore()) {
                              if (index == state.products.length - 1) {
                                if (context
                                    .read<ProductsCubit>()
                                    .fetchMoreError()) {
                                  return Center(
                                    child: CustomTextButton(
                                        buttonTextKey: retryKey,
                                        onTapButton: () {
                                          loadMoreProducts();
                                        }),
                                  );
                                }

                                return Center(
                                  child: CustomCircularProgressIndicator(
                                      indicatorColor: Theme.of(context)
                                          .colorScheme
                                          .primary),
                                );
                              }
                            }
                            if (product.type == variableProductType &&
                                (product.variants == null ||
                                    product.variants!.isEmpty)) {
                              return SizedBox.shrink();
                            }
                            if (product.type == variableProductType &&
                                product.stockType == '2') {
                              return ListView.separated(
                                separatorBuilder: (context, index) =>
                                    DesignConfig.defaultHeightSizedBox,
                                itemCount: product.variants!.length,
                                shrinkWrap: true,
                                physics: const NeverScrollableScrollPhysics(),
                                itemBuilder: (context, index) {
                                  return ProductStockInfoContainer(
                                    product: product,
                                    controllers: controllers,
                                    focusNodes: focusNodes,
                                    productVariant: product.variants![index],
                                    productsCubit:
                                        context.read<ProductsCubit>(),
                                  );
                                },
                              );
                            }

                            return ProductStockInfoContainer(
                              product: state.products[index],
                              controllers: controllers,
                              focusNodes: focusNodes,
                              productVariant: null,
                              productsCubit: context.read<ProductsCubit>(),
                            );
                          }),
                    ),
                  );
                }
                if (state is ProductsFetchFailure) {
                  return ErrorScreen(
                    text: state.errorMessage,
                    onPressed: () {
                      getProducts();
                    },
                  );
                }
                return Center(
                  child: CustomCircularProgressIndicator(
                    indicatorColor: Theme.of(context).colorScheme.primary,
                  ),
                );
              },
            ),
          ),
        ),
        const SizedBox(
          height: 60,
        )
      ],
    );
  }
}

class ProductStockInfoContainer extends StatelessWidget {
  final Product product;
  final Map<String, TextEditingController> controllers;
  final Map<String, FocusNode> focusNodes;
  final ProductVariant? productVariant;
  final ProductsCubit productsCubit;
  const ProductStockInfoContainer({
    Key? key,
    required this.product,
    required this.controllers,
    required this.focusNodes,
    this.productVariant,
    required this.productsCubit,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsetsDirectional.all(8),
      decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primaryContainer,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.transparent)),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.start,
        children: <Widget>[
          buildProductImage(context),
          DesignConfig.smallWidthSizedBox,
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [buildProductName(context), buildEditIcon(context)],
                ),
                if (productVariant != null)
                  Utils.buildVariantContainer(
                      context,
                      productVariant!.attrName!,
                      productVariant!.variantValues!),
                DesignConfig.smallHeightSizedBox,
                buildLabelAndValue(
                    context,
                    priceKey,
                    Utils.priceWithCurrencySymbol(
                        price: productVariant != null
                            ? productVariant!.getPrice()
                            : product.hasSpecialPrice()
                                ? product.getPrice()
                                : product.getBasePrice(),
                        context: context)),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: <Widget>[
                    buildLabelAndValue(
                        context,
                        quantityKey,
                        StockManagementHelper.getProductQuantity(
                            product, productVariant)),
                    CustomStatusContainer(
                        getValueList: Utils.getStockTextAndColor,
                        status: getStockStatus(context, product,
                                productVariant: productVariant)
                            .toString())
                  ],
                )
              ],
            ),
          )
        ],
      ),
    );
  }

  Expanded buildProductName(BuildContext context) {
    return Expanded(
      child: CustomTextContainer(
        textKey: product.name ?? "",
        maxLines: 2,
        style: Theme.of(context).textTheme.bodyMedium,
        overflow: TextOverflow.ellipsis,
      ),
    );
  }

  int getStockStatus(BuildContext context, Product product,
      {ProductVariant? productVariant}) {
    int availability = 0, lowStockLimit = 0, stock = 0;
    lowStockLimit = int.parse(context
        .read<SettingsAndLanguagesCubit>()
        .getSettings()
        .systemSettings!
        .lowStockLimit
        .toString());

    if (product.type == variableProductType) {
      if (product.variants != null && product.variants!.isNotEmpty) {
        if (product.stockType == '1' && product.variants != null) {
          availability = int.parse(product.variants![0].availability!);

          stock = product.variants![0].stock != null &&
                  product.variants![0].stock!.isNotEmpty
              ? int.parse(product.variants![0].stock!)
              : 0;
        } else if (product.stockType == '2' && productVariant != null) {
          availability = int.parse(productVariant.availability!);

          stock =
              productVariant.stock != null && productVariant.stock!.isNotEmpty
                  ? int.parse(productVariant.stock!)
                  : 0;
        }
      }
    } else {
      availability = int.parse(product.availability!);

      stock = product.stock != null && product.stock!.isNotEmpty
          ? int.parse(product.stock!)
          : 0;
    }
    return availability == 0
        ? 0 //[Out of stock]
        : stock >= lowStockLimit
            ? 1 // [In stock]
            : 2; //[Low in stock]
  }

  Widget buildProductImage(BuildContext context) {
    double imageSize = MediaQuery.of(context).size.height * 0.1;
    return CustomImageWidget(
      url: productVariant != null &&
              productVariant!.images != null &&
              productVariant!.images!.isNotEmpty
          ? productVariant!.images!.first
          : product.image ?? '',
      width: imageSize,
      height: imageSize,
      borderRadius: 4,
    );
  }

  buildLabelAndValue(BuildContext context, String title, String value) {
    return Text.rich(
      TextSpan(
        children: [
          TextSpan(
              text:
                  '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: title)} : ',
              style: Theme.of(context).textTheme.labelMedium!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.67))),
          TextSpan(
              text: value,
              style: Theme.of(context)
                  .textTheme
                  .labelSmall!
                  .copyWith(color: Theme.of(context).colorScheme.secondary)),
        ],
      ),
    );
  }

  buildEditIcon(BuildContext context) {
    return GestureDetector(
      onTap: () {
        controllers[typeKey]!.text = stockUpdateTypes.entries.first.key;
        controllers[currentStockKey]!.text =
            StockManagementHelper.getProductQuantity(product, productVariant);
        controllers[quantityKey]!.clear();
        Utils.openModalBottomSheet(
                context, buildEditContainer(product, context),
                staticContent: true)
            .then((value) {});
      },
      child: Container(
          width: 26,
          height: 26,
          alignment: Alignment.center,
          margin: const EdgeInsetsDirectional.only(start: 8),
          decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primary,
              borderRadius: BorderRadius.circular(4)),
          child: const Icon(
            Icons.edit_outlined,
            size: 18,
            color: Colors.white,
          )),
    );
  }

  Widget buildEditContainer(Product product, BuildContext context) {
    return StatefulBuilder(
        builder: (BuildContext buildcontext, StateSetter setState) {
      return BlocProvider(
        create: (context) => ManageStockCubit(),
        child: BlocConsumer<ManageStockCubit, ManageStockState>(
          listener: (context, state) {
            if (state is ManageStockSuccess) {
              Navigator.of(context).pop();
              Utils.showSnackBar(message: state.successMessage);

              if (state.product.type == comboProductType) {
                comboProductsCubit!.updateProductDetails(state.product);
              } else {
                regularProductsCubit!.updateProductDetails(state.product);
              }
              List<Product> products =
                  (productsCubit.state as ProductsFetchSuccess).products;
              int index =
                  products.indexWhere((element) => element.id == product.id);

              if (index != -1) {
                products[index] = state.product;
              }

              //we will fetch the updated product details
              productsCubit.emitSuccessState(products,
                  (productsCubit.state as ProductsFetchSuccess).total);
            }
            if (state is ManageStockFailure) {
              Navigator.of(context).pop();
              Utils.showSnackBar(message: state.errorMessage);
            }
          },
          builder: (context, state) {
            return GestureDetector(
              onTap: () => FocusScope.of(context).unfocus(),
              child: FilterContainerForBottomSheet(
                title: updateStockKey,
                borderedButtonTitle: '',
                primaryButtonTitle: updateKey,
                borderedButtonOnTap: () {},
                content: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Container(
                        padding: const EdgeInsetsDirectional.all(8),
                        decoration: BoxDecoration(
                          color: Theme.of(context).scaffoldBackgroundColor,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: <Widget>[
                            CustomImageWidget(
                              url: product.image ?? '',
                              width: MediaQuery.of(context).size.height * 0.08,
                              height: MediaQuery.of(context).size.height * 0.08,
                              borderRadius: 4,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: CustomTextContainer(
                                textKey: product.name ?? "",
                                overflow: TextOverflow.visible,
                              ),
                            )
                          ],
                        ),
                      ),
                      DesignConfig.defaultHeightSizedBox,
                      CustomTextFieldContainer(
                        hintTextKey: currentStockKey,
                        textEditingController: controllers[currentStockKey]!,
                        labelKey: currentStockKey,
                        textInputAction: TextInputAction.done,
                        readOnly: true,
                        enable: false,
                      ),
                      CustomTextFieldContainer(
                          hintTextKey: quantityKey,
                          textEditingController: controllers[quantityKey]!,
                          labelKey: quantityKey,
                          textInputAction: TextInputAction.next,
                          validator: (v) =>
                              Validator.emptyValueValidation(v, context),
                          keyboardType: TextInputType.number,
                          inputFormatters: [
                            FilteringTextInputFormatter
                                .digitsOnly, // Allow only digits
                          ],
                          focusNode: focusNodes[quantityKey],
                          onFieldSubmitted: (v) => FocusScope.of(context)
                              .requestFocus(focusNodes[typeKey])),
                      CustomDropDownContainer(
                          labelKey: typeKey,
                          dropDownDisplayLabels:
                              stockUpdateTypes.values.toList(),
                          selectedValue: controllers[typeKey]!.text,
                          onChanged: (value) {
                            setState(() {
                              controllers[typeKey]!.text = value.toString();
                            });
                          },
                          values: stockUpdateTypes.keys.toList()),
                    ],
                  ),
                ),
                primaryChild: state is ManageStockProgress
                    ? CustomCircularProgressIndicator(
                        indicatorColor: Theme.of(context).colorScheme.primary,
                      )
                    : null,
                primaryButtonOnTap: () {
                  if (state is! ManageStockProgress) {
                    if (isDemoApp) {
                      Navigator.of(context).pop();
                      Utils.showSnackBar(message: demoModeOnKey);
                      return;
                    }
                    context.read<ManageStockCubit>().manageStock(
                        apiUrl: product.type == comboProductType
                            ? ApiURL.manageComboStock
                            : ApiURL.manageStock,
                        isComboProduct: product.type == comboProductType,
                        params: {
                          if (product.type != comboProductType)
                            ApiURL.productVariantIdApiKey:
                                productVariant != null
                                    ? productVariant!.id!
                                    : product.variants!.first.id!,
                          if (product.type == comboProductType)
                            ApiURL.productIdApiKey: product.id,
                          ApiURL.quantityApiKey:
                              controllers[quantityKey]!.text.trim(),
                          ApiURL.typeApiKey:
                              controllers[typeKey]!.text.trim().toLowerCase(),
                          ApiURL.currentStockApiKey:
                              controllers[currentStockKey]!.text.trim(),
                        });
                  }
                },
              ),
            );
          },
        ),
      );
    });
  }
}
