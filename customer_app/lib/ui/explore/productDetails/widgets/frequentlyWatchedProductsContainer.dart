import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/commons/product/blocs/productsCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/product/widgets/productCard.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hive/hive.dart';

class FrequentlyWatchedProductsContainer extends StatefulWidget {
  final Product? product;
  const FrequentlyWatchedProductsContainer({Key? key, this.product})
      : super(key: key);

  @override
  State<FrequentlyWatchedProductsContainer> createState() =>
      _FrequentlyWatchedProductsContainerState();
}

class _FrequentlyWatchedProductsContainerState
    extends State<FrequentlyWatchedProductsContainer> {
  @override
  initState() {
    super.initState();
    List? productIds = Hive.box(productsBoxKey).values.toList();
    if (productIds.isNotEmpty) {
      Future.delayed(Duration.zero, () {
        context.read<ProductsCubit>().getProducts(
            storeId: context.read<StoresCubit>().getDefaultStore().id!,
            productIds: productIds.map((item) => item as int).toList());
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<ProductsCubit, ProductsState>(
      listener: (context, state) {
        if (state is ProductsFetchSuccess && widget.product != null) {
          state.products
              .removeWhere((element) => element.id == widget.product!.id);
        }
      },
      builder: (context, state) {
        if (state is ProductsFetchSuccess) {
          return Column(
            children: [
              DesignConfig.smallHeightSizedBox,
              Container(
                width: double.maxFinite,
                color: Theme.of(context).colorScheme.primaryContainer,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Padding(
                      padding:
                          const EdgeInsets.all(appContentHorizontalPadding),
                      child: CustomTextContainer(
                        textKey: recentlyViewedKey,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                    ),
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.symmetric(
                          horizontal: appContentHorizontalPadding),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: List.generate(
                          state.products.length,
                          (index) => Padding(
                            padding: const EdgeInsetsDirectional.only(
                                end: appContentHorizontalPadding),
                            child: ProductCard(
                              product: state.products[index],
                              backgroundColor: Theme.of(context)
                                  .colorScheme
                                  .primaryContainer,
                            ),
                          ),
                        ),
                      ),
                    ),
                    DesignConfig.defaultHeightSizedBox,
                  ],
                ),
              ),
            ],
          );
        }
        if (state is ProductsFetchInProgress) {
          return CustomCircularProgressIndicator(
            indicatorColor: Theme.of(context).colorScheme.primary,
          );
        }
        return const SizedBox.shrink();
      },
    );
  }
}
