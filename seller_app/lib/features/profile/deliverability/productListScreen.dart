import 'package:value_market_seller/commons/blocs/zoneListCubit.dart';
import 'package:value_market_seller/commons/blocs/productCubit.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/features/profile/deliverability/blocs/updateProductDeliverabilityCubit.dart';
import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_seller/commons/models/product.dart';
import 'package:value_market_seller/commons/models/userDetails.dart';
import 'package:value_market_seller/features/profile/deliverability/deliveryModal.dart';
import 'package:value_market_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_seller/commons/widgets/customImageWidget.dart';
import 'package:value_market_seller/commons/widgets/customRoundedButton.dart';
import 'package:value_market_seller/commons/widgets/errorScreen.dart';

import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class ProductListScreen extends StatefulWidget {
  final String searchText;
  final bool isComboProductScreen;
  const ProductListScreen({
    Key? key,
    required this.searchText,
    required this.isComboProductScreen,
  }) : super(key: key);

  @override
  _ProductListScreenState createState() => _ProductListScreenState();
}

class _ProductListScreenState extends State<ProductListScreen>
    with AutomaticKeepAliveClientMixin<ProductListScreen> {
  @override
  bool get wantKeepAlive => true;
  List<int> selectedProductIds = [];
  Map<String, TextEditingController> controllers = {};
  Map<String, String> deliverableTypes = Map.from(productDeliverableTypes);
  final Map<String, String> apiFormField = {
    deliverableTypeKey: "deliverable_type",
    selectZonesKey: "deliverable_zones[]",
  };
  @override
  void initState() {
    super.initState();
    apiFormField.forEach((key, value) {
      controllers[key] = TextEditingController();
    });
    Future.delayed(Duration.zero, () {
      getProducts();
      StoreData currentStore =
          context.read<UserDetailsCubit>().getDefaultStoreOfUser(context);

      //if seller has set deliverable type including/ exluding then we'll remove all option here
      if (currentStore.deliverableType == 2) {
        deliverableTypes.remove('1');
      }
      controllers[deliverableTypeKey]!.text = deliverableTypes.keys.first;
    });
  }

  void getProducts() {
    context.read<ProductsCubit>().getProducts(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          showOnlyStockroducts: 0,
          showOnlyActiveProducts: 1,
          isComboProduct: widget.isComboProductScreen,
          searchText: widget.searchText,
        );
  }

  void loadMoreProducts() {
    context.read<ProductsCubit>().loadMore(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          showOnlyStockroducts: 0,
          showOnlyActiveProducts: 1,
          isComboProduct: widget.isComboProductScreen,
          searchText: widget.searchText,
        );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Scaffold(
      body: BlocBuilder<ProductsCubit, ProductsState>(
        builder: (context, state) {
          if (state is ProductsFetchSuccess) {
            return Stack(
              clipBehavior: Clip.none,
              children: [
                RefreshIndicator(
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
                      separatorBuilder: (context, index) =>
                          DesignConfig.defaultHeightSizedBox,
                      padding: const EdgeInsetsDirectional.all(
                          appContentHorizontalPadding),
                      itemCount: state.products.length,
                      itemBuilder: (context, index) {
                        final product = state.products[index];
                        final isSelected =
                            selectedProductIds.contains(product.id);

                        return GestureDetector(
                          onTap: () {
                            setState(() {
                              if (!selectedProductIds.contains(product.id)) {
                                selectedProductIds.add(product.id!);
                              } else {
                                selectedProductIds.remove(product.id);
                              }
                            });
                          },
                          child: Container(
                              padding: const EdgeInsetsDirectional.all(8),
                              decoration: BoxDecoration(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .primaryContainer,
                                  borderRadius: BorderRadius.circular(8),
                                  border:
                                      Border.all(color: Colors.transparent)),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.start,
                                children: <Widget>[
                                  buildProductImage(product),
                                  DesignConfig.smallWidthSizedBox,
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: <Widget>[
                                        Text(
                                          product.name!,
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                        Text(
                                          '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: deliverableTypeKey)}: ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: productDeliverableTypes.entries.firstWhere((element) => element.key == product.deliverableType.toString()).value).split(' ').first}',
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodyMedium!
                                              .copyWith(
                                                  color: Theme.of(context)
                                                      .colorScheme
                                                      .secondary
                                                      .withValues(alpha: 0.67)),
                                        ),
                                        //only show deliverable zones if product  deliverable type is spesific zones
                                        if (product.deliverableType == 2)
                                          Text(
                                            '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: zonesKey)}: ${product.deliverableZones ?? ''}',
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: Theme.of(context)
                                                .textTheme
                                                .bodyMedium!
                                                .copyWith(
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .secondary
                                                        .withValues(
                                                            alpha: 0.67)),
                                          ),
                                      ],
                                    ),
                                  ),
                                  Checkbox(
                                    value: isSelected,
                                    onChanged: (value) {
                                      setState(() {
                                        if (value == true) {
                                          selectedProductIds.add(product.id!);
                                        } else {
                                          selectedProductIds.remove(product.id);
                                        }
                                      });
                                    },
                                  ),
                                ],
                              )),
                        );
                      },
                    ),
                  ),
                ),
                if (selectedProductIds.isNotEmpty)
                  Align(
                      alignment: Alignment.bottomCenter,
                      child: Container(
                          margin: const EdgeInsetsDirectional.symmetric(
                              horizontal: appContentHorizontalPadding,
                              vertical: appContentHorizontalPadding / 2),
                          child: CustomRoundedButton(
                            widthPercentage: 1.0,
                            buttonTitle: updateKey,
                            showBorder: false,
                            onTap: _showDeliveryModal,
                          ))),
              ],
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
    );
  }

  Widget buildProductImage(Product product) {
    double imageSize = 80;
    return CustomImageWidget(
      url: product.image ?? '',
      width: imageSize,
      height: imageSize,
      borderRadius: 4,
      boxFit: BoxFit.cover,
    );
  }

  void _showDeliveryModal() {
    Utils.openModalBottomSheet(
            context,
            MultiBlocProvider(
              providers: [
                BlocProvider(
                  create: (context) => ZoneListCubit(),
                ),
                BlocProvider(
                  create: (context) => UpdateProductDeliverabilityCubit(),
                ),
              ],
              child: DeliveryModal(
                selectedProductIds: selectedProductIds,
                deliverableTypes: deliverableTypes,
                controllers: controllers,
                refreshAPI: getProducts,
                isComboProductScreen: widget.isComboProductScreen,
              ),
            ),
            staticContent: true)
        .then((v) {
      controllers[selectZonesKey]!.text = "";
    });
  }
}
