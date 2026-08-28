import 'package:value_market_seller/commons/models/product.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../blocs/attributeListCubit.dart';
import '../../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../../commons/blocs/storesCubit.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import '../../../../core/localization/labelKeys.dart';
import '../screens/addProductScreen.dart';
import 'attributeTab.dart';
import 'generalInfoTab.dart';
import 'variationTab.dart';

class ProductPage3 extends StatefulWidget {
  Map<String, TextEditingController> controllers;
  Map<String, Map<String, dynamic>> selectedAttributes;
  Map<String, FocusNode> focusNodes;
  TabController tabController;
  final Product? product;
  ProductPage3({
    super.key,
    required this.controllers,
    required this.focusNodes,
    required this.selectedAttributes,
    required this.tabController,
    this.product,
  });

  @override
  State<ProductPage3> createState() => _ProductPage3State();
}

class _ProductPage3State extends State<ProductPage3>
    with TickerProviderStateMixin {
  TabController? _tabController;
  int _currentIndex = 0;
  bool _isSwipeBlocked = false;
  @override
  void initState() {
    super.initState();
    tabControllerSetup();
    getData();
  }

  tabControllerSetup() {
    _tabController = TabController(length: getTabLength(), vsync: this);
    _tabController!.addListener(() {
      if (_tabController!.indexIsChanging) {
        return;
      }
      _handleTabChange(context, _tabController!.index);
    });
    Future.delayed(const Duration(milliseconds: 100), () {
      _handleTabChange(context, _currentIndex);
    });
  }

  @override
  void dispose() {
    _tabController!.dispose();
    super.dispose();
  }

  getData() {
    if (context.read<AttributeListCubit>().state
        is! AttributeListFetchSuccess) {
      context.read<AttributeListCubit>().getAttributeList(context, {
        ApiURL.storeIdApiKey:
            context.read<StoresCubit>().getDefaultStore().id.toString()
      });
    }
  }

  getTabLength() {
    return widget.controllers[productTypeKey]!.text == variableProductType
        ? 3
        : 2;
  }

  _handleTabChange(BuildContext context, int index,
      {bool onlyCheckValidation = false, bool isChangeTabAllow = false}) {
    if (!(addProductFormKey!.currentState!.validate())) {
      _tabController!.index = _currentIndex;
      setState(() {
        _isSwipeBlocked = true;
      });
    } else if (!onlyCheckValidation) {
      if (isChangeTabAllow) _tabController!.animateTo(index);
      setState(() {
        _currentIndex = index;
        _isSwipeBlocked = false;
      });
    }

    return _isSwipeBlocked;
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onHorizontalDragEnd: (details) {
        if (_isSwipeBlocked) {
          _handleTabChange(context, _currentIndex + 1, isChangeTabAllow: true);
        }
      },
      child: DefaultTabController(
        length: getTabLength(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Container(
              height: 40,
              margin: const EdgeInsets.all(
                appContentHorizontalPadding,
              ),
              decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(8),
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.1)),
              child: TabBar(
                controller: _tabController,
                onTap: (value) {
                  _handleTabChange(context, value, onlyCheckValidation: true);
                },
                dividerColor: Colors.transparent,
                indicator: BoxDecoration(
                  borderRadius: BorderRadius.circular(borderRadius),
                  color: Theme.of(context).colorScheme.primary,
                ),
                indicatorSize: TabBarIndicatorSize.tab,
                unselectedLabelColor: Theme.of(context).colorScheme.secondary,
                labelColor: Theme.of(context).colorScheme.onPrimary,
                tabs: <Widget>[
                  buildTabLabel(generalInfoKey),
                  buildTabLabel(attributesKey),
                  if (widget.controllers[productTypeKey]!.text ==
                      variableProductType)
                    buildTabLabel(variationsKey),
                ],
              ),
            ),
            Container(
              height: appContentVerticalSpace,
              color: Theme.of(context).scaffoldBackgroundColor,
            ),
            Flexible(child: tabbarWidget()),
          ],
        ),
      ),
    );
  }

  tabbarWidget() {
    return TabBarView(
      controller: _tabController,
      physics: _isSwipeBlocked ? const NeverScrollableScrollPhysics() : null,
      children: <Widget>[
        GeneralInfoTab(
            controllers: widget.controllers,
            focusNodes: widget.focusNodes,
            product: widget.product,
            refreshPage: () {
              tabControllerSetup();
              setState(() {});
            }),
        AttributeTab(
            controllers: widget.controllers,
            focusNodes: widget.focusNodes,
            selectedAttributes: widget.selectedAttributes),
        if (widget.controllers[productTypeKey]!.text == variableProductType)
          VariationTab(
            controllers: widget.controllers,
            selectedAttributes: widget.selectedAttributes,
            product: widget.product,
          )
      ],
    );
  }

  Tab buildTabLabel(String title) {
    return Tab(
      text: context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: title),
      height: 40,
    );
  }
}
