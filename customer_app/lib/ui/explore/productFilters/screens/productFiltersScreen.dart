import 'package:value_market_customer/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:value_market_customer/ui/home/brand/brandsCubit.dart';
import 'package:value_market_customer/ui/categoty/cubits/categoryCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/commons/blocs/storesCubit.dart';
import 'package:value_market_customer/ui/categoty/models/category.dart';
import 'package:value_market_customer/commons/product/models/filterAttribute.dart';
import 'package:value_market_customer/commons/product/models/productMinMaxPrice.dart';
import 'package:value_market_customer/ui/explore/productFilters/models/selectedFilterAttribute.dart';
import 'package:value_market_customer/ui/explore/productFilters/widgets/brandsFilterView.dart';
import 'package:value_market_customer/ui/explore/productFilters/widgets/categoriesFilterView.dart';
import 'package:value_market_customer/ui/explore/productFilters/widgets/priceFilterView.dart';
import 'package:value_market_customer/ui/explore/productFilters/widgets/selectedFilterView.dart';
import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customTextButton.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

typedef ProductFiltersScreenResult = ({
  List<SelectedFilterAttribute> filterAttributes,
  String minPrice,
  String maxPrice
});

class ProductFiltersScreen extends StatefulWidget {
  final List<FilterAttribute> filterAttributes;
  final double minPrice;
  final double maxPrice;
  final List<SelectedFilterAttribute> selectedFilterAttributes;
  final String selctedMinPrice;
  final String selctedMaxPrice;
  final int totalProducts;
  final Category?
      category; // category and brandId param is to used to decide whether to show category or brand filter
  final String? brandId;
  final String?
      categoryIds; //categoryIds and  brandIds param is used to pass in API
  final String? brandIds;

  const ProductFiltersScreen({
    super.key,
    required this.filterAttributes,
    required this.minPrice,
    required this.maxPrice,
    required this.selectedFilterAttributes,
    required this.selctedMinPrice,
    required this.selctedMaxPrice,
    required this.totalProducts,
    this.category,
    this.brandId,
    this.categoryIds,
    this.brandIds,
  });

  static Widget getRouteInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => CategoryCubit(),
        ),
        BlocProvider(create: (context) => BrandsCubit())
      ],
      child: ProductFiltersScreen(
        selctedMaxPrice: arguments['selctedMaxPrice'] as String,
        selctedMinPrice: arguments['selctedMinPrice'] as String,
        maxPrice: arguments['maxPrice'] as double,
        minPrice: arguments['minPrice'] as double,
        selectedFilterAttributes: arguments['selectedFilterAttributes']
            as List<SelectedFilterAttribute>,
        filterAttributes:
            arguments['filterAttributes'] as List<FilterAttribute>,
        totalProducts: arguments['totalProducts'] as int,
        category: arguments['category'] as Category?,
        brandId: arguments['brandId'] as String?,
        categoryIds: arguments['categoryIds'] as String?,
        brandIds: arguments['brandIds'] as String?,
      ),
    );
  }

  static Map<String, dynamic> buildArguments({
    required List<FilterAttribute> filterAttributes,
    required minPrice,
    required double maxPrice,
    required List<SelectedFilterAttribute> selectedFilterAttributes,
    required String selctedMinPrice,
    required String selctedMaxPrice,
    required int totalProducts,
    Category? category,
    String? brandId,
    String? categoryIds,
    String? brandIds,
  }) {
    return {
      'filterAttributes': filterAttributes,
      'minPrice': minPrice,
      'maxPrice': maxPrice,
      'selectedFilterAttributes': selectedFilterAttributes,
      'selctedMinPrice': selctedMinPrice,
      'selctedMaxPrice': selctedMaxPrice,
      'totalProducts': totalProducts,
      'category': category,
      'brandId': brandId,
      'categoryIds': categoryIds,
      'brandIds': brandIds
    };
  }

  @override
  State<ProductFiltersScreen> createState() => _ProductFiltersScreenState();
}

class _ProductFiltersScreenState extends State<ProductFiltersScreen> {
  ///[Category, Brand, Price, Rating and Discount is fixed]

  late SelectedFilterAttribute selectedFilterAttribute;
  List<SelectedFilterAttribute> filterAttributes = [];
  List<ProductMinMaxPrice> priceRanges = [];
  late final TextEditingController minPriceTextEditingController =
      TextEditingController(
    text: widget.minPrice.toStringAsFixed(2),
  );
  late final TextEditingController maxPriceTextEditingController =
      TextEditingController(
    text: widget.maxPrice.toStringAsFixed(2),
  );

  bool hasMadeAnyChanges = false;

  @override
  void initState() {
    super.initState();

    ///[Setup filter attributes]
    if (widget.selectedFilterAttributes.isNotEmpty) {
      filterAttributes =
          List<SelectedFilterAttribute>.from(widget.selectedFilterAttributes);
      selectedFilterAttribute = filterAttributes.first;
    } else {
      setupUpFilterAttributes();
      selectedFilterAttribute = filterAttributes.first;
    }

    ///[Setup price ranges]
    priceRanges = Utils.calculatePriceRanges(
        minPrice: widget.minPrice, maxPrice: widget.maxPrice);

    ///[Fetch cateogries and brands from the store if  explore screen is not for specific category or brand ]
    if (widget.category == null) {
      context.read<CategoryCubit>().fetchCategories(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          categoryIds: widget.categoryIds);
    }
    if (widget.brandId == null) {
      context.read<BrandsCubit>().getBrands(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          brandIds: widget.brandIds);
    }
  }

  @override
  void dispose() {
    minPriceTextEditingController.dispose();
    maxPriceTextEditingController.dispose();
    super.dispose();
  }

  ///[clear all filter attributes]
  void clearAllSelectedFilterAttributes() {
    filterAttributes.clear();
    setupUpFilterAttributes();
    setState(() {});
    Get.back(result: (
      filterAttributes: List<SelectedFilterAttribute>.from(filterAttributes),
      minPrice: '',
      maxPrice: '',
    ));
  }

  ///[clear selected filter attribute]
  void clearSelectedFilterAttribute(String attributeName) {
    final index = filterAttributes.indexWhere(
      (filterAttribute) => filterAttribute.attributeName == attributeName,
    );

    filterAttributes[index] = filterAttributes[index].copyWith(selectedIds: []);

    setState(() {});
  }

  ///[To check if the filter attribute is selected or not]
  bool isFilterAttributeIdSelected(String filterAttributeName, int id) {
    final SelectedFilterAttribute selectedFilterAttribute =
        filterAttributes.firstWhere(
            (filterAttribute) =>
                filterAttribute.attributeName == filterAttributeName,
            orElse: () => SelectedFilterAttribute(
                attributeName: "", selectedIds: [], isPredefined: true));

    return selectedFilterAttribute.isIdSelected(id);
  }

  ///[Update the selected filter attribute id]
  void updateSelectedFilterAttributeId(String filterAttributeName, int id,
      [bool? singleSelection]) {
    ///[Check if any changes have been made, based on this we will enable the apply button]
    if (!hasMadeAnyChanges) {
      hasMadeAnyChanges = true;
      setState(() {});
    }

    final index = filterAttributes.indexWhere(
      (filterAttribute) => filterAttribute.attributeName == filterAttributeName,
    );

    SelectedFilterAttribute selectedFilterAttribute = filterAttributes[index];

    List<int> selectedIds = List<int>.from(selectedFilterAttribute.selectedIds);

    if (selectedIds.contains(id)) {
      selectedIds.remove(id);
    } else {
      if (singleSelection == true) {
        selectedIds = [];
      }
      selectedIds.add(id);
    }

    filterAttributes[index] =
        selectedFilterAttribute.copyWith(selectedIds: selectedIds);

    setState(() {});
  }

  void setupUpFilterAttributes() {
    if (widget.category == null) {
      filterAttributes.add(SelectedFilterAttribute(
        attributeName: categoryKey,
        selectedIds: [],
        isPredefined: true,
      ));
    }
    if (widget.brandId == null) {
      filterAttributes.add(SelectedFilterAttribute(
        attributeName: brandKey,
        selectedIds: [],
        isPredefined: true,
      ));
    }
    filterAttributes.add(SelectedFilterAttribute(
        attributeName: priceKey,
        selectedIds: [],
        isPredefined: true,
        attributeValues: []));
    filterAttributes.add(SelectedFilterAttribute(
      attributeName: ratingsKey,
      selectedIds: [],
      isPredefined: true,
      isSingleSelection: true,
    ));
    filterAttributes.add(SelectedFilterAttribute(
      attributeName: discountKey,
      selectedIds: [],
      isPredefined: true,
      isSingleSelection: true,
    ));

    for (var attribute in widget.filterAttributes) {
      filterAttributes.add(SelectedFilterAttribute(
        attributeName: attribute.attributeName!,
        selectedIds: [],
        isSingleSelection: false,
        isPredefined: false,
        attributeValues: attribute.attributeValues,
        attributeValuesId: attribute.attributeValuesId,
      ));
    }
  }

  Widget _buildFilterDetailsContainer({
    required SelectedFilterAttribute filterAttribute,
  }) {
    final isSelected = filterAttribute == selectedFilterAttribute;

    return InkWell(
      onTap: () {
        setState(() {
          selectedFilterAttribute = filterAttribute;
        });
      },
      child: Container(
        height: 50,
        decoration: BoxDecoration(
          color: isSelected
              ? Theme.of(context).appBarTheme.backgroundColor
              : Colors.transparent,
        ),
        child: Stack(
          children: [
            isSelected
                ? Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: Container(
                      width: 4.0,
                      decoration: BoxDecoration(
                          borderRadius: const BorderRadiusDirectional.only(
                            bottomEnd: Radius.circular(5.0),
                            topEnd: Radius.circular(5.0),
                          ),
                          color: Theme.of(context).colorScheme.primary),
                    ),
                  )
                : const SizedBox(),
            Center(
              child: Padding(
                padding: const EdgeInsetsDirectional.symmetric(
                  horizontal: 15,
                ),
                child: Row(children: [
                  Expanded(
                    child: CustomTextContainer(
                        textKey: filterAttribute.attributeName,
                        style: Theme.of(context).textTheme.bodyLarge),
                  ),
                  filterAttribute.selectedIds.isEmpty
                      ? const SizedBox()
                      : CustomTextContainer(
                          textKey:
                              filterAttribute.selectedIds.length.toString(),
                          style: Theme.of(context).textTheme.bodySmall),
                ]),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSelectedFilterView() {
    if (selectedFilterAttribute.isPredefined) {
      if (selectedFilterAttribute.attributeName == categoryKey) {
        return CategoriesFilterView(onTapCategory: (id) {
          updateSelectedFilterAttributeId(categoryKey, id);
        }, isCategorySelected: (id) {
          return isFilterAttributeIdSelected(categoryKey, id);
        });
      }

      if (selectedFilterAttribute.attributeName == brandKey) {
        return BrandsFilterView(
          isBrandSelected: (id) {
            return isFilterAttributeIdSelected(brandKey, id);
          },
          onTapBrand: (id) {
            updateSelectedFilterAttributeId(brandKey, id);
          },
        );
      }

      if (selectedFilterAttribute.attributeName == priceKey) {
        return PriceFilterView(
          minPriceController: minPriceTextEditingController,
          maxPriceController: maxPriceTextEditingController,
          productMinMaxFilterPrices: priceRanges,
          isPriceRangeSelected: (id) {
            return isFilterAttributeIdSelected(priceKey, id);
          },
          onChangedMinMaxPrice: (minPrice, maxPrice) {
            if (minPrice != null && maxPrice != null) {
              updateSelectedFilterAttributeId(priceKey, 0, true);
            }
          },
          onTapPriceRange: (id) {
            ///[Allow single selection for price range]

            updateSelectedFilterAttributeId(priceKey, id, true);
          },
        );
      }
    }

    return SelectedFilterView(
        onTapFilterAttribute: updateSelectedFilterAttributeId,
        isFilterAttributeSelected: isFilterAttributeIdSelected,
        selectedFilterAttribute: selectedFilterAttribute);
  }

  Widget _buildClearAndApplyButton() {
    return Align(
      alignment: Alignment.bottomCenter,
      child: Container(
        width: MediaQuery.of(context).size.width,
        height: 60,
        decoration: BoxDecoration(
          boxShadow: [
            BoxShadow(
                color: Colors.black.withValues(alpha: 0.25),
                blurRadius: 4,
                offset: const Offset(0, -4),
                spreadRadius: 0),
          ],
          color: Theme.of(context).colorScheme.primaryContainer,
        ),
        child: Stack(
          children: [
            SizedBox(
              height: 60,
              child: Row(
                children: [
                  Expanded(
                    child: GestureDetector(
                      onTap: () => {Get.back()},
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          CustomTextContainer(
                            textKey: closeKey,
                            style: Theme.of(context).textTheme.bodyLarge,
                          ),
                        ],
                      ),
                    ),
                  ),
                  Expanded(
                    child: GestureDetector(
                      onTap: () {
                        if (!hasMadeAnyChanges) {
                          return;
                        }

                        ///[If text field min price is less than allowed min price then show snack bar]
                        if ((double.tryParse(minPriceTextEditingController.text
                                    .trim()) ??
                                0.0) <
                            widget.minPrice) {
                          Utils.showSnackBar(
                              message:
                                  "${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: minPriceMsgKey)}${widget.minPrice}",
                              context: context);
                          return;
                        }

                        ///[If text field max price is greater than allowed max price then show snack bar]
                        if ((double.tryParse(maxPriceTextEditingController.text
                                    .trim()) ??
                                0.0) >
                            widget.maxPrice) {
                          Utils.showSnackBar(
                              message:
                                  "${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: maxPriceMsgKey)}${widget.maxPrice}",
                              context: context);
                          return;
                        }

                        ///[If all the validations are passed then go back]
                        Get.back(result: (
                          filterAttributes: List<SelectedFilterAttribute>.from(
                              filterAttributes),
                          minPrice: minPriceTextEditingController.text.trim(),
                          maxPrice: maxPriceTextEditingController.text.trim(),
                        ));
                      },
                      child: Opacity(
                        opacity: hasMadeAnyChanges ? 1.0 : 0.5,
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            CustomTextContainer(
                              textKey: applyKey,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyLarge
                                  ?.copyWith(
                                    color:
                                        Theme.of(context).colorScheme.primary,
                                  ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Center(
              child: Container(
                width: 1,
                height: 30,
                color: Theme.of(context)
                    .colorScheme
                    .secondary
                    .withValues(alpha: 0.26),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        appBar: CustomAppbar(
          titleKey: '',
          leadingWidget: Align(
            alignment: Alignment.centerLeft,
            child: Text.rich(
              TextSpan(
                children: [
                  TextSpan(
                    text: context
                        .read<SettingsAndLanguagesCubit>()
                        .getTranslatedValue(labelKey: filterKey),
                    style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w400,
                      height: 0.06,
                    ),
                  ),
                  const TextSpan(
                    text: ' ',
                  ),
                  TextSpan(
                    text:
                        '(${widget.totalProducts} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: productsKey)})',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w400,
                      height: 0.09,
                    ),
                  ),
                ],
              ),
            ),
          ),
          trailingWidget: CustomTextButton(
              textStyle: Theme.of(context)
                  .textTheme
                  .titleSmall
                  ?.copyWith(color: Theme.of(context).colorScheme.primary),
              buttonTextKey: clearAllKey,
              onTapButton: () {
                clearAllSelectedFilterAttributes();
              }),
        ),
        body: SafeAreaWithBottomPadding(
          child: Stack(
            children: [
              LayoutBuilder(builder: (context, boxConstraints) {
                return Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: boxConstraints.maxWidth * 0.35,
                      child: SingleChildScrollView(
                        child: Column(
                          children: filterAttributes
                              .map((filterAttribute) =>
                                  _buildFilterDetailsContainer(
                                      filterAttribute: filterAttribute))
                              .toList(),
                        ),
                      ),
                    ),
                    SizedBox(
                      height: boxConstraints.maxHeight,
                      width: boxConstraints.maxWidth * 0.65,
                      child: Container(
                        padding: const EdgeInsetsDirectional.only(
                            start: 10.0, top: 10.0),
                        color: Theme.of(context).appBarTheme.backgroundColor,
                        child: _buildSelectedFilterView(),
                      ),
                    ),
                  ],
                );
              }),
              _buildClearAndApplyButton()
            ],
          ),
        ));
  }
}
