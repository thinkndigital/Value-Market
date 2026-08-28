import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/commons/product/blocs/productsCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/product/widgets/productCard.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class SimilarProductContainer extends StatefulWidget {
  final Product product;
  const SimilarProductContainer({Key? key, required this.product})
      : super(key: key);

  @override
  _SimilarProductContainerState createState() =>
      _SimilarProductContainerState();
}

class _SimilarProductContainerState extends State<SimilarProductContainer> {
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      context.read<ProductsCubit>().getProducts(
          storeId: widget.product.storeId ??
              context.read<StoresCubit>().getDefaultStore().id!,
          categoryIds: widget.product.type == comboProductType
              ? null
              : widget.product.categoryId.toString(),
          productId: widget.product.type == comboProductType
              ? widget.product.id
              : null,
          apiUrl: widget.product.type == comboProductType
              ? ApiURL.getSimilarComboProducts
              : ApiURL.getSimilarProducts);
    });
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<ProductsCubit, ProductsState>(
      listener: (context, state) {
        if (state is ProductsFetchSuccess) {
          state.products
              .removeWhere((element) => element.id == widget.product.id);
        }
      },
      builder: (context, state) {
        if (state is ProductsFetchSuccess && state.products.isNotEmpty) {
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
                        textKey: similarProductsKey,
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
                          (index) =>
                              state.products[index].id != widget.product.id
                                  ? Padding(
                                      padding: const EdgeInsetsDirectional.only(
                                          end: appContentHorizontalPadding),
                                      child: ProductCard(
                                        product: state.products[index],
                                        backgroundColor: Theme.of(context)
                                            .colorScheme
                                            .primaryContainer,
                                      ),
                                    )
                                  : SizedBox(),
                        ),
                      ),
                    ),
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
