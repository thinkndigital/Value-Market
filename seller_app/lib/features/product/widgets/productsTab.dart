import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/features/product/widgets/listProductsContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class ProductsTab extends StatefulWidget {
  final dynamic sortByParams;
  final dynamic filterParams;
  final String searchText;
  final bool isComboProductScreen;
  const ProductsTab(
      {Key? key,
      required this.sortByParams,
      required this.filterParams,
      required this.searchText,
      required this.isComboProductScreen})
      : super(key: key);

  @override
  _ProductsTabState createState() => _ProductsTabState();
}

class _ProductsTabState extends State<ProductsTab>
    with AutomaticKeepAliveClientMixin<ProductsTab> {
  @override
  bool get wantKeepAlive => true;
  @override
  void initState() {
    super.initState();
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
          isComboProduct: widget.isComboProductScreen,
          searchText: widget.searchText,
        );
  }

  void loadMoreProducts() {
    final sortByParams = widget.sortByParams;
    final filterParams = widget.filterParams;

    context.read<ProductsCubit>().loadMore(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          orderBy: sortByParams.orderBy,
          sortBy: sortByParams.sortBy,
          topRatedProduct: sortByParams.topRatedProduct,
          searchText: widget.searchText,
          flag: filterParams.flag,
          type: filterParams.type,
          isComboProduct: widget.isComboProductScreen,
        );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return BlocConsumer<ProductsCubit, ProductsState>(
      listener: (context, state) {
        if (state is ProductsFetchSuccess) {}
      },
      builder: (context, state) {
        if (state is ProductsFetchSuccess) {
          if (state.products.isEmpty) {
            return ErrorScreen(
              onPressed: getProducts,
              text: dataNotAvailableKey,
            );
          }
          return RefreshIndicator(
            onRefresh: () async {
              getProducts();
            },
            child: ListProductsContainer(
              loadMoreProducts: loadMoreProducts,
              products: state.products,
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
    );
  }
}
