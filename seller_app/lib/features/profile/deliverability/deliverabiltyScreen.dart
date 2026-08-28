import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/safeAreaWithBottomPadding.dart';

import 'package:eshopplus_seller/features/profile/deliverability/productListScreen.dart';
import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customTabbar.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:get/route_manager.dart';

class DeliverabiltyScreen extends StatefulWidget {
  final isStockManagementScreen;
  const DeliverabiltyScreen({Key? key, this.isStockManagementScreen = false})
      : super(key: key);
  static Widget getRouteInstance() => DeliverabiltyScreen(
        isStockManagementScreen: Get.arguments,
      );
  @override
  _DeliverabiltyScreenState createState() => _DeliverabiltyScreenState();
}

class _DeliverabiltyScreenState extends State<DeliverabiltyScreen> {
  final _searchController = TextEditingController();
  bool _isSearchMode = false;
  FocusNode _searchFocusNode = FocusNode();
  int _selectedTabIndex = 0;
  List<String> _tabs = [productsKey, comboKey];
  var prevVal;

  GlobalKey _productsKey = GlobalKey(), _comboProductsKey = GlobalKey();

  @override
  void initState() {
    super.initState();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        appBar: CustomAppbar(
          titleKey: manageProductDeliverabilityKey,
          showBackButton: _isSearchMode ? false : true,
          leadingWidget: _isSearchMode ? buildSearchField() : null,
          trailingWidget: IconButton(
            padding: EdgeInsets.zero,
            icon: Icon(_isSearchMode ? Icons.close : Icons.search_outlined),
            onPressed: _toggleSearchMode,
          ),
        ),
        body: SafeAreaWithBottomPadding(
          child: Column(
            children: [
              buildTabBarWithChangeProductsStyleButton(),
              Expanded(
                child: IndexedStack(
                  index: _selectedTabIndex,
                  children: [
                    BlocProvider(
                      create: (context) => ProductsCubit(),
                      child: ProductListScreen(
                          key: _productsKey,
                          searchText: _searchController.text.trim(),
                          isComboProductScreen: false),
                    ),
                    BlocProvider(
                      create: (context) => ProductsCubit(),
                      child: ProductListScreen(
                          key: _comboProductsKey,
                          searchText: _searchController.text.trim(),
                          isComboProductScreen: true),
                    ),
                  ],
                ),
              )
            ],
          ),
        ));
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

  searchChange(String val) {
    if (val.trim() != prevVal) {
      Future.delayed(Duration.zero, () {
        prevVal = val.trim();
      });
    }
  }

  buildSearchField() {
    return Utils.buildSearchField(
      context: context,
      controller: _searchController,
      focusNode: _searchFocusNode,
      onChanged: (v) {
        callAPI();
      },
    );
  }

  void _toggleSearchMode() {
    setState(() {
      _isSearchMode = !_isSearchMode;
      if (!_isSearchMode) {
        setState(() {
          _searchController.clear();
        });
      } else {
        FocusScope.of(context).requestFocus(_searchFocusNode);
      }
    });
  }

  callAPI() {
    setState(() {
      _productsKey = GlobalKey();
      _comboProductsKey = GlobalKey();
    });
  }

  Widget buildTabBarWithChangeProductsStyleButton() {
    return Column(
      children: [
        DesignConfig.smallHeightSizedBox,
        Align(
          alignment: Alignment.topCenter,
          child: Container(
            color: Theme.of(context).colorScheme.primaryContainer,
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
