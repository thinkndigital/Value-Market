import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';

import 'package:eshopplus_seller/features/mainScreen.dart';
import 'package:eshopplus_seller/features/product/widgets/productsTab.dart';
import 'package:eshopplus_seller/features/product/widgets/sortProductBottomsheet.dart';
import 'package:eshopplus_seller/features/profile/stockManagement/screens/stockManagementScreen.dart';
import 'package:eshopplus_seller/commons/widgets/customLabelContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTabbar.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:get/route_manager.dart';

import '../../../commons/widgets/customAppbar.dart';
import '../../../commons/widgets/customBottomButtonContainer.dart';
import '../../../commons/widgets/customRoundedButton.dart';
import '../../../commons/widgets/customTextFieldContainer.dart';

class ProductScreen extends StatefulWidget {
  final isStockManagementScreen;
  const ProductScreen({Key? key, this.isStockManagementScreen = false})
      : super(key: key);
  static Widget getRouteInstance() => ProductScreen(
        isStockManagementScreen: Get.arguments,
      );
  @override
  _ProductScreenState createState() => _ProductScreenState();
}

class _ProductScreenState extends State<ProductScreen> {
  final _searchController = TextEditingController();
  bool _isSearchMode = false;
  FocusNode _searchFocusNode = FocusNode();
  int _selectedTabIndex = 0;
  List<String> _tabs = [productsKey, comboKey];
  var prevVal;
  String _selectedSortBy = allKey,
      _selectedFlag = allKey,
      _selectedType = allKey;
  GlobalKey _productsKey = GlobalKey(),
      _comboProductsKey = GlobalKey(),
      _regularStockMgmtKey = GlobalKey(),
      _comboStockMgmtKey = GlobalKey();
  final List<String> _selectedRadio = [
    allKey,
    allKey,
    topRatedKey
  ]; //0 for status filter, 1 for type filter , 2 for sorting
  final List<String> _tabTitles = const [productsKey, comboProductsKey];
  int _currentPage = 0;
  @override
  void initState() {
    super.initState();
  }

  ({String? flag, String? type}) buildFilterParams() {
    String? flag;
    String? type;

    ///[Sort by will be set here before fetching the products]
    if (_selectedFlag == soldOutKey) {
      flag = "sold";
    } else if (_selectedFlag == lowInStockKey) {
      flag = "low";
    } else if (_selectedFlag == allKey) {
      flag = null;
    }
    if (_selectedType == allKey) {
      type = null;
    } else if (_selectedType == simpleKey) {
      type = simpleProductType;
    } else if (_selectedType == variableKey) {
      type = variableProductType;
    } else if (_selectedType == physicalKey) {
      type = physicalProductType;
    } else if (_selectedType == digitalKey) {
      type = digitalProductType;
    }
    return (
      flag: flag,
      type: type,
    );
  }

  ({String? orderBy, String? sortBy, int? topRatedProduct})
      buildSortByParams() {
    String? orderBy;
    String? sortBy;
    int? topRatedProduct;

    ///[Sort by will be set here before fetching the products]
    if (_selectedSortBy == topRatedProductKey) {
      topRatedProduct = 1;
    } else if (_selectedSortBy == newestFirstKey) {
      orderBy = "desc";
      sortBy = 'p.id';
    } else if (_selectedSortBy == oldestFirstKey) {
      orderBy = "asc";
      sortBy = 'p.id';
    } else if (_selectedSortBy == priceLowToHighKey) {
      orderBy = "asc";
      sortBy = "pv.price";
    } else if (_selectedSortBy == priceHighToLowKey) {
      orderBy = "desc";
      sortBy = "pv.price";
    } else if (_selectedSortBy == allKey) {
      sortBy = null;
      orderBy = null;
      topRatedProduct = null;
    }
    return (
      orderBy: orderBy,
      sortBy: sortBy,
      topRatedProduct: topRatedProduct,
    );
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: widget.isStockManagementScreen == true
          ? CustomAppbar(
              titleKey: stockManagementKey,
              showBackButton: _isSearchMode ? false : true,
              leadingWidget: _isSearchMode ? buildSearchField() : null,
              trailingWidget: IconButton(
                padding: EdgeInsets.zero,
                icon: Icon(_isSearchMode ? Icons.close : Icons.search_outlined),
                onPressed: _toggleSearchMode,
              ),
            )
          : const CustomAppbar(
              titleKey: productsKey,
              showBackButton: false,
            ),
      bottomNavigationBar: widget.isStockManagementScreen == true
          ? buildSortAndFilterContainer()
          : null,
      body: widget.isStockManagementScreen == true
          ? Stack(
              children: [
                _selectedTabIndex == 0
                    ? BlocProvider(
                        create: (context) => ProductsCubit(),
                        child: StockManagementScreen(
                            key: _regularStockMgmtKey,
                            sortByParams: buildSortByParams(),
                            filterParams: buildFilterParams(),
                            searchText: _searchController.text.trim(),
                            isComboProductScreen: false),
                      )
                    : BlocProvider(
                        create: (context) => ProductsCubit(),
                        child: StockManagementScreen(
                            key: _comboStockMgmtKey,
                            sortByParams: buildSortByParams(),
                            filterParams: buildFilterParams(),
                            searchText: _searchController.text.trim(),
                            isComboProductScreen: true),
                      ),
                buildTabBarWithChangeProductsStyleButton(),
              ],
            )
          : GestureDetector(
              onTap: () {
                FocusScope.of(context).unfocus();
              },
              child: Column(
                children: <Widget>[
                  buildSearchBar(),
                  const SizedBox(
                    height: 8,
                  ),
                  buildTabBar()
                ],
              ),
            ),
    );
  }

  buildSearchBar() {
    return Container(
      color: Theme.of(context).colorScheme.primaryContainer,
      padding: const EdgeInsets.all(appContentHorizontalPadding),
      child: Row(
        children: <Widget>[
          Expanded(
            flex: 4,
            child: CustomTextFieldContainer(
              hintTextKey: searchAllProductsKey,
              textEditingController: _searchController,
              labelKey: '',
              textInputAction: TextInputAction.done,
              prefixWidget: Icon(
                Icons.search,
                color: Theme.of(context).inputDecorationTheme.iconColor,
              ),
              suffixWidget: IconButton(
                  onPressed: () {
                    if (_searchController.text.trim().isNotEmpty) {
                      FocusScope.of(context).unfocus();
                      _searchController.clear();
                      callAPI();
                    }
                  },
                  icon: const Icon(Icons.close)),
              onChangeFun: (value) {
                callAPI();
              },
            ),
          ),
          buildFilterContainer(
              Icons.import_export_outlined,
              () => Utils.openModalBottomSheet(context, buildSortList(),
                  staticContent: true)),
          buildFilterContainer(
              Icons.filter_list,
              () => Utils.openModalBottomSheet(context, buildFilterList(),
                  staticContent: true)),
        ],
      ),
    );
  }

  Column buildSortList() {
    return Column(
      children: <Widget>[
        SortProductBottomSheet(
          onSortBySelected: changeSortBy,
          selectedSortBy: _selectedSortBy,
        ),
        CustomBottomButtonContainer(
            child: Row(
          children: <Widget>[
            Expanded(
              child: CustomRoundedButton(
                  widthPercentage: 0.4,
                  buttonTitle: clearFiltersKey,
                  showBorder: true,
                  backgroundColor: Theme.of(context).colorScheme.onPrimary,
                  borderColor: Theme.of(context).hintColor,
                  style: const TextStyle().copyWith(
                    color: Theme.of(context).colorScheme.secondary,
                  ),
                  onTap: () {
                    _selectedSortBy = allKey;

                    callAPI();

                    Navigator.of(context).pop();
                  }),
            ),
            const SizedBox(
              width: 16,
            ),
            Expanded(
              child: CustomRoundedButton(
                widthPercentage: 0.4,
                buttonTitle: applyKey,
                showBorder: false,
                onTap: () {
                  callAPI();

                  Navigator.of(context).pop();
                },
              ),
            )
          ],
        ))
      ],
    );
  }

  buildFilterContainer(IconData icon, VoidCallback onTap) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          margin: const EdgeInsetsDirectional.only(start: 8),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
                width: 1,
                color: Theme.of(context).inputDecorationTheme.iconColor!),
          ),
          child: Icon(
            icon,
            size: 24,
            color:
                Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8),
          ),
        ),
      ),
    );
  }

  buildTabBar() {
    return Expanded(
      child: Column(
        children: <Widget>[
          CustomTabbar(
              currentPage: _currentPage,
              tabTitles: _tabTitles,
              onTapTitle: (int index) {
                setState(() {
                  _currentPage = index;
                });
              }),
          Expanded(
              child: GestureDetector(
            /// we have used manual tabbar, so need to add gesture detector when user swipe to change tabs
            onHorizontalDragEnd: (dragEndDetails) {
              // Swiping in right direction.
              if (dragEndDetails.primaryVelocity! > 0) {
                if (_currentPage > 0) {
                  setState(() {
                    _currentPage--;
                  });
                }
              }

              // Swiping in left direction.
              if (dragEndDetails.primaryVelocity! < 0) {
                if (_currentPage < _tabTitles.length - 1) {
                  setState(() {
                    _currentPage++;
                  });
                }
              }
            },
            child: IndexedStack(
              alignment: AlignmentDirectional.topStart,
              index: _currentPage,
              children: [
                BlocProvider(
                  create: (context) => regularProductsCubit ?? ProductsCubit(),
                  child: ProductsTab(
                    key: _productsKey,
                    sortByParams: buildSortByParams(),
                    filterParams: buildFilterParams(),
                    searchText: _searchController.text.trim(),
                    isComboProductScreen: false,
                  ),
                ),
                BlocProvider(
                  create: (context) => comboProductsCubit ?? ProductsCubit(),
                  child: ProductsTab(
                    key: _comboProductsKey,
                    sortByParams: buildSortByParams(),
                    filterParams: buildFilterParams(),
                    searchText: _searchController.text.trim(),
                    isComboProductScreen: true,
                  ),
                ),
              ],
            ),
          ))
        ],
      ),
    );
  }

  Tab buildTabLabel(String title) {
    return Tab(
        child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: <Widget>[
        Text(
          context
              .read<SettingsAndLanguagesCubit>()
              .getTranslatedValue(labelKey: title),
        ),
        Container(
          color: Theme.of(context).inputDecorationTheme.iconColor!,
          height: 10,
          width: 2,
        )
      ],
    ));
  }

  Widget buildFilterList() {
    return StatefulBuilder(
        builder: (BuildContext buildcontext, StateSetter setState) {
      return SingleChildScrollView(
        child: Column(
          children: <Widget>[
            Padding(
              padding:
                  const EdgeInsetsDirectional.all(appContentHorizontalPadding),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  CustomTextContainer(
                    textKey: statusKey,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  buildRadioListTile(0, allKey, setState),
                  buildRadioListTile(0, soldOutKey, setState),
                  buildRadioListTile(0, lowInStockKey, setState),
                ],
              ),
            ),
            const Divider(),
            DesignConfig.defaultHeightSizedBox,
            Padding(
              padding: EdgeInsetsDirectional.symmetric(
                  horizontal: appContentVerticalSpace),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  CustomTextContainer(
                    textKey: typeKey,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  buildRadioListTile(1, allKey, setState),
                  // buildRadioListTile(1, simpleKey, setState),
                  buildRadioListTile(1, physicalKey, setState),
                  buildRadioListTile(1, digitalKey, setState),
                ],
              ),
            ),
            buildFilterButtons(),
          ],
        ),
      );
    });
  }

  CustomBottomButtonContainer buildFilterButtons() {
    return CustomBottomButtonContainer(
      child: Row(
        children: [
          Expanded(
            child: CustomRoundedButton(
                widthPercentage: 0.4,
                buttonTitle: clearFiltersKey,
                showBorder: true,
                backgroundColor: Theme.of(context).colorScheme.onPrimary,
                borderColor: Theme.of(context).hintColor,
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium!
                    .copyWith(color: Theme.of(context).colorScheme.secondary),
                onTap: () {
                  _selectedFlag = allKey;
                  _selectedType = allKey;

                  callAPI();

                  Navigator.of(context).pop();
                }),
          ),
          const SizedBox(
            width: 16,
          ),
          Expanded(
            child: CustomRoundedButton(
              widthPercentage: 0.4,
              buttonTitle: applyKey,
              showBorder: false,
              onTap: () {
                callAPI();

                Navigator.of(context).pop();
              },
            ),
          )
        ],
      ),
    );
  }

  searchChange(String val) {
    if (val.trim() != prevVal) {
      Future.delayed(Duration.zero, () {
        prevVal = val.trim();
        Future.delayed(const Duration(seconds: 2), () {
          refreshStockManagement();
        });
      });
    }
  }

  buildSearchField() {
    return Utils.buildSearchField(
        context: context,
        controller: _searchController,
        focusNode: _searchFocusNode,
        onChanged: (value) {
          refreshStockManagement();
        });
  }

  void _toggleSearchMode() {
    setState(() {
      _isSearchMode = !_isSearchMode;
      if (!_isSearchMode) {
        setState(() {
          _searchController.clear();
          refreshStockManagement();
        });
      } else {
        FocusScope.of(context).requestFocus(_searchFocusNode);
      }
    });
  }

  refreshStockManagement() {
    setState(() {
      _regularStockMgmtKey = GlobalKey();
      _comboStockMgmtKey = GlobalKey();
    });
  }

  callAPI() {
    setState(() {
      _productsKey = GlobalKey();
      _comboProductsKey = GlobalKey();
      if (widget.isStockManagementScreen == true) {
        refreshStockManagement();
      }
    });
  }

  buildRadioListTile(int index, String key, StateSetter setState) {
    return RadioListTile(
      contentPadding: EdgeInsets.zero,
      visualDensity: const VisualDensity(horizontal: -4, vertical: -2),
      title: CustomLabelContainer(
        textKey: key,
        isFieldValueMandatory: false,
      ),

      value: key, // Assign a value of 1 to this option
      groupValue: _selectedRadio[
          index], // Use _selectedValue to track the selected option
      onChanged: (value) {
        setState(() {
          if (index == 0) {
            _selectedFlag = value!;
          } else {
            _selectedType = value!;
          }
          _selectedRadio[index] =
              value; // Update _selectedValue when option 1 is selected
        });
      },
    );
  }

  ///[To change the sort by]
  void changeSortBy(String sortBy) {
    _selectedSortBy = sortBy;
  }

  void changeFiltertBy(String flag, String type) {
    _selectedFlag = flag;
    _selectedType = type;
  }

  buildSortAndFilterContainer() {
    return Container(
      width: MediaQuery.of(context).size.width,
      height: 70,
      padding: EdgeInsetsDirectional.only(
          bottom: MediaQuery.of(context).padding.bottom),
      decoration: BoxDecoration(
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.25),
            blurRadius: 10,
            offset: const Offset(0, 0),
          ),
        ],
        color: Theme.of(context).colorScheme.primaryContainer,
      ),
      child: Stack(
        children: [
          SizedBox(
            height: 70,
            child: Row(
              children: [
                Expanded(
                  child: GestureDetector(
                    onTap: () => Utils.openModalBottomSheet(
                        context, buildSortList(),
                        staticContent: true),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.import_export),
                        const SizedBox(
                          width: 5.0,
                        ),
                        CustomTextContainer(
                          textKey: sortKey,
                          style: Theme.of(context).textTheme.bodyLarge,
                        ),
                      ],
                    ),
                  ),
                ),
                Expanded(
                  child: GestureDetector(
                    onTap: () => Utils.openModalBottomSheet(
                        context, buildFilterList(),
                        staticContent: true),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.filter_list),
                        const SizedBox(
                          width: 5.0,
                        ),
                        CustomTextContainer(
                          textKey: filterKey,
                          style: Theme.of(context).textTheme.bodyLarge,
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          Center(
            child: Container(
              width: 1,
              height: 20,
              color: Theme.of(context)
                  .colorScheme
                  .secondary
                  .withValues(alpha: 0.26),
            ),
          ),
        ],
      ),
    );
  }

  Widget buildTabBarWithChangeProductsStyleButton() {
    return Column(
      children: [
        DesignConfig.smallHeightSizedBox,
        Align(
          alignment: Alignment.topCenter,
          child: Container(
            color: Theme.of(context).colorScheme.primaryContainer,
            margin: const EdgeInsetsDirectional.only(
              bottom: appContentHorizontalPadding,
            ),
            child: CustomTabbar(
              currentPage: _selectedTabIndex,
              textStyle: Theme.of(context).textTheme.bodyLarge,
              tabTitles: _tabs,
              padding: 2,
              onTapTitle: (index) {
                _selectedTabIndex = index;
                setState(() {});
              },
            ),
          ),
        ),
      ],
    );
  }
}
