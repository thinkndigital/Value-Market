import 'dart:async';
import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../../commons/blocs/storesCubit.dart';
import '../../../../../commons/models/product.dart';
import '../../../../../commons/models/store.dart';
import '../../../../../../utils/designConfig.dart';
import '../../../../../utils/utils.dart';
import '../../../../../commons/widgets/customTextContainer.dart';

class ComboProductSelectionDialog extends StatefulWidget {
  final Function onProductSelect;
  final Map<String, Product> selectedProduct;
  final ProductsCubit productListCubit;
  const ComboProductSelectionDialog(
      {super.key,
      required this.productListCubit,
      required this.onProductSelect,
      required this.selectedProduct});

  @override
  State<StatefulWidget> createState() {
    return ComboProductSelectionDialogState();
  }
}

class ComboProductSelectionDialogState
    extends State<ComboProductSelectionDialog> {
  Map<String, Product> tempselectedproduct = {};
  final TextEditingController _searchQuery = TextEditingController();
  String _searchText = "";
  bool isSearching = false, isloadmore = true;
  final scrollController = ScrollController();
  List<Product> loadedBrandlist = [];
  int total = 0;
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
    Store defaultstore = context.read<StoresCubit>().getDefaultStore();
    if (isSetInitialPage) {
      widget.productListCubit.getProducts(
          storeId: defaultstore.id!,
          isComboProduct: true,
          searchText: apiParameter!.containsKey("search")
              ? apiParameter!["search"]
              : null);
    } else if (widget.productListCubit.hasMore()) {
      widget.productListCubit.loadMore(
          storeId: defaultstore.id!,
          isComboProduct: true,
          searchText: apiParameter!.containsKey("search")
              ? apiParameter!["search"]
              : null);
    }
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
    widget.productListCubit.setOldList(loadedBrandlist);
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
                child: BlocBuilder<ProductsCubit, ProductsState>(
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

  dropdownListWidget(ProductsState state) {
    if (state is ProductsFetchInProgress) {
      return Utils.loadingIndicator();
    } else if (state is ProductsFetchFailure) {
      return Utils.msgWithTryAgain(
          context, state.errorMessage, () => loadPage(isSetInitialPage: true));
    }
    List<Product> brandlist = [];

    bool isLoading = false, isLoadMore = false;
    int mtotal = 0;
    if (state is ProductsFetchSuccess) {
      brandlist = state.products;
      isLoading = state.fetchMoreInProgress;
      isLoadMore = state.fetchMoreInProgress;
      mtotal = state.total;
    }
    if (_searchText.trim().isEmpty && brandlist.isNotEmpty) {
      loadedBrandlist = [];
      loadedBrandlist = brandlist;
      isloadmore = isLoadMore;
      total = mtotal;
    }

    return ListView.builder(
      controller: scrollController,
      shrinkWrap: true,
      itemCount: brandlist.length + (isLoading ? 1 : 0),
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemBuilder: (context, index) {
        if (index < brandlist.length) {
          return itemWidget(brandlist[index]);
        } else {
          Timer(const Duration(milliseconds: 30), () {
            scrollController.jumpTo(scrollController.position.maxScrollExtent);
          });

          return Utils.loadingIndicator();
        }
      },
    );
  }

  itemWidget(Product product) {
    return GestureDetector(
      onTap: () {
        if (tempselectedproduct.containsKey(product.id!.toString())) {
          tempselectedproduct.remove(product.id!.toString());
        } else {
          tempselectedproduct[product.id!.toString()] = product;
        }
        setState(() {});
      },
      child: Container(
        margin: const EdgeInsets.all(4),
        padding: const EdgeInsets.all(4),
        decoration: (tempselectedproduct.containsKey(product.id!.toString()))
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
              url: product.image ?? '',
              width: 48,
              height: 48,
              borderRadius: 4,
            ),
            DesignConfig.smallWidthSizedBox,
            Expanded(
              flex: 1,
              child: Text(
                product.name ?? "",
                style: Theme.of(context)
                    .textTheme
                    .titleSmall!
                    .merge(TextStyle(color: black)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
