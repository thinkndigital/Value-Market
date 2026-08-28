import 'dart:async';

import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/models/productVariant.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../blocs/getProductByTypeCubit.dart';
import '../../../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../../../commons/models/product.dart';
import '../../../../../../utils/designConfig.dart';
import '../../../../../core/localization/labelKeys.dart';
import '../../../../../utils/utils.dart';
import '../../../../../commons/widgets/customTextContainer.dart';

class ProductSelectionDialog extends StatefulWidget {
  final Function onProductSelect;
  final Map<String, ProductVariant> selectedProduct;
  final GetProductByTypeCubit productListCubit;
  final String? type;
  const ProductSelectionDialog(
      {super.key,
      required this.type,
      required this.productListCubit,
      required this.onProductSelect,
      required this.selectedProduct});

  @override
  State<StatefulWidget> createState() {
    return ProductSelectionDialogState();
  }
}

class ProductSelectionDialogState extends State<ProductSelectionDialog> {
  Map<String, ProductVariant> tempselectedproduct = {};
  final TextEditingController _searchQuery = TextEditingController();
  String _searchText = "";
  bool isSearching = false, isloadmore = true;
  final scrollController = ScrollController();
  List<Product> loadedBrandlist = [];
  int currOffset = 0;
  Map<String, String>? apiParameter;
  @override
  void initState() {
    super.initState();
    tempselectedproduct.addAll(widget.selectedProduct);
    apiParameter = {};
    isSearching = false;

    setupScrollController(context);
  }

  loadPage({bool isSetInitialPage = false}) {
    Map<String, String> parameter = {
      ApiURL.storeIdApiKey:
          context.read<StoresCubit>().getDefaultStore().id.toString(),
      "type": widget.type!
    };
    if (apiParameter != null) {
      parameter.addAll(apiParameter!);
    }
    widget.productListCubit
        .getProductList(context, parameter, isSetInitial: isSetInitialPage);
  }

  setupScrollController(context) {
    scrollController.addListener(() {
      if (scrollController.position.atEdge) {
        if (scrollController.position.pixels != 0) {
          loadPage();
        }
      }
    });
  }

  searchBrand() {
    if (_searchQuery.text.trim().isEmpty) {
      setState(() {
        isSearching = false;
        _searchText = "";
      });
    } else {
      setState(() {
        isSearching = true;
        _searchText = _searchQuery.text;
      });
    }

    if (_searchText.trim().isEmpty) {
      setAllList();
      return;
    }
    apiParameter!["search"] = _searchText;
    loadPage(isSetInitialPage: true);
  }

  setAllList() {
    if (apiParameter!.containsKey("search")) {
      apiParameter!.remove("search");
    }
    widget.productListCubit
        .setOldList(currOffset, loadedBrandlist, isloadmore, widget.type!);
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      onPopInvokedWithResult: (didPop, result) {
        setAllList();
      },
      child: SingleChildScrollView(
        child: SizedBox(
          width: double.maxFinite,
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(
              controller: _searchQuery,
              decoration: InputDecoration(
                  enabledBorder: DesignConfig.setUnderlineInputBorder(grey),
                  focusedBorder: DesignConfig.setUnderlineInputBorder(grey),
                  border: DesignConfig.setUnderlineInputBorder(grey),
                  prefixIcon: Icon(Icons.search, color: grey),
                  hintText: context
                      .read<SettingsAndLanguagesCubit>()
                      .getTranslatedValue(labelKey: searchKey),
                  hintStyle: TextStyle(color: grey)),
              onChanged: (value) {
                searchBrand();
              },
            ),
            ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(context).size.height * 0.6,
                ),
                child:
                    BlocBuilder<GetProductByTypeCubit, GetProductByTypeState>(
                  bloc: widget.productListCubit,
                  builder: (context, state) {
                    return dropdownListWidget(state);
                  },
                )),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                TextButton(
                    onPressed: () {
                      Navigator.of(context).pop();
                    },
                    child: const CustomTextContainer(textKey: cancelKey)),
                TextButton(
                    onPressed: () {
                      widget.onProductSelect(tempselectedproduct);
                    },
                    child: const CustomTextContainer(textKey: applyKey))
              ],
            )
          ]),
        ),
      ),
    );
  }

  dropdownListWidget(GetProductByTypeState state) {
    if (state is GetProductByTypeListProgress && state.isFirstFetch) {
      return Utils.loadingIndicator();
    } else if (state is GetProductByTypeListFailure) {
      return Utils.msgWithTryAgain(
          context, state.errorMessage, () => loadPage(isSetInitialPage: true));
    }
    List<Product> brandlist = [];

    bool isLoading = false, isLoadMore = false;
    int offset = 0;
    if (state is GetProductByTypeListProgress) {
      brandlist = state.oldBrandList;
      offset = state.currOffset;
      isLoading = true;
    } else if (state is GetProductByTypeListSuccess) {
      brandlist = state.productList;
      offset = state.currOffset;
      isLoadMore = state.isLoadmore;
    }
    if (_searchText.trim().isEmpty && brandlist.isNotEmpty) {
      currOffset = offset;
      loadedBrandlist = [];
      loadedBrandlist = brandlist;
      isloadmore = isLoadMore;
    }
    return ListView.builder(
      controller: scrollController,
      shrinkWrap: true,
      itemCount: brandlist.length + (isLoading ? 1 : 0),
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemBuilder: (context, index) {
        if (index < brandlist.length) {
          Product product = brandlist[index];
          return Column(
            children: List.generate(
              product.variants!.length,
              (index) {
                ProductVariant productVariant = product.variants![index];
                return Wrap(children: [
                  itemWidget(productVariant),
                  DesignConfig.defaultHeightSizedBox,
                ]);
              },
            ),
          );
        } else {
          Timer(const Duration(milliseconds: 30), () {
            scrollController.jumpTo(scrollController.position.maxScrollExtent);
          });

          return Utils.loadingIndicator();
        }
      },
    );
  }

  itemWidget(ProductVariant productVariant) {
    String productName = productVariant.productName ?? "";
    if (productVariant.variantValues!.isNotEmpty) {
      productName = "$productName - ${productVariant.variantValues}";
    }
    String pdi = widget.type == digitalProductType
        ? productVariant.productId!.toString()
        : productVariant.id!.toString();
    return GestureDetector(
      onTap: () {
        if (tempselectedproduct.containsKey(pdi)) {
          tempselectedproduct.remove(pdi);
        } else {
          tempselectedproduct[pdi] = productVariant;
        }
        setState(() {});
      },
      child: Container(
        margin: const EdgeInsets.all(4),
        padding: const EdgeInsets.all(4),
        decoration: (tempselectedproduct.containsKey(pdi))
            ? BoxDecoration(
                borderRadius: BorderRadius.circular(4),
                border:
                    Border.all(color: Theme.of(context).colorScheme.primary))
            : null,
        child: Row(
          mainAxisSize: MainAxisSize.max,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            CustomImageWidget(
              url: productVariant.productImage ?? '',
              width: 48,
              height: 48,
              borderRadius: 4,
            ),
            DesignConfig.smallWidthSizedBox,
            Expanded(
              flex: 1,
              child: Text(productName,
                  style: Theme.of(context).textTheme.bodyMedium!),
            ),
          ],
        ),
      ),
    );
  }
}
