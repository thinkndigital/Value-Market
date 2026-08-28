import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/core/theme/colors.dart';
import 'package:value_market_seller/features/home/blocs/topSellingProductCubit.dart';
import 'package:value_market_seller/features/profile/addProduct/blocs/categoryListCubit.dart';
import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/blocs/storesCubit.dart';

import 'package:value_market_seller/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_seller/commons/widgets/customImageWidget.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/commons/widgets/errorScreen.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/features/profile/addProduct/models/category.dart';
import 'package:value_market_seller/features/profile/addProduct/widgets/helper/categorySelectionDialog.dart';

import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class TopSellingProductSection extends StatefulWidget {
  const TopSellingProductSection({Key? key}) : super(key: key);

  @override
  _TopSellingProductSectionState createState() =>
      _TopSellingProductSectionState();
}

class _TopSellingProductSectionState extends State<TopSellingProductSection>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;
  late int storeId;
  Category? _selectedCategory;
  @override
  void initState() {
    super.initState();

    storeId = context.read<StoresCubit>().getDefaultStore().id!;
    Future.delayed(Duration.zero, () {
      context.read<CategoryListCubit>().getCategoryList(context, {
        ApiURL.storeIdApiKey: storeId.toString(),
      });
    });
  }

  fetchTopSellingProducts() {
    context.read<TopSellingProductCubit>().getTopSellingProducts(params: {
      ApiURL.storeIdApiKey: storeId.toString(),
      ApiURL.categoryIdApiKey: _selectedCategory!.id,
    });
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return BlocListener<CategoryListCubit, CategoryListState>(
        listener: (context, state) {
      if (state is CategoryListFetchSuccess) {
        _selectedCategory = state.categoryList.first;
        setState(() {});
        fetchTopSellingProducts();
      }
    }, child: BlocBuilder<CategoryListCubit, CategoryListState>(
      builder: (context, state) {
        if (state is CategoryListFetchSuccess) {
          return Column(
            children: [
              CustomDefaultContainer(
                child: Column(
                  children: <Widget>[
                    Row(
                      mainAxisSize: MainAxisSize.max,
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        CustomTextContainer(
                          textKey: topSellingProductsKey,
                          style: Theme.of(context).textTheme.bodyLarge,
                        ),
                        const SizedBox(width: 25),
                        Flexible(
                          child: GestureDetector(
                            onTap: () {
                              showDialog(
                                context: context,
                                barrierDismissible: false,
                                builder: (BuildContext mcontext) {
                                  return AlertDialog(
                                    surfaceTintColor: white,
                                    insetPadding: const EdgeInsets.all(
                                        appContentHorizontalPadding),
                                    backgroundColor: white,
                                    contentPadding: const EdgeInsets.symmetric(
                                        horizontal: 8, vertical: 5),
                                    shape: DesignConfig.setRoundedBorder(
                                        Theme.of(context)
                                            .colorScheme
                                            .primaryContainer,
                                        10,
                                        false),
                                    content: CategorySelectionDialog(
                                        categorylist: state.categoryList,
                                        selectedId:
                                            _selectedCategory!.id.toString(),
                                        onCategorySelect:
                                            (List<Category> category) {
                                          if (_selectedCategory!.id
                                                  .toString() !=
                                              category.first.id.toString()) {
                                            _selectedCategory = category.first;
                                            setState(() {});
                                          }
                                          fetchTopSellingProducts();
                                          Navigator.pop(context);
                                        }),
                                  );
                                },
                              );
                            },
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 2),
                              decoration: BoxDecoration(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .primaryContainer,
                                  borderRadius:
                                      BorderRadius.circular(borderRadius),
                                  border: Border.all(
                                      color: Theme.of(context)
                                          .inputDecorationTheme
                                          .iconColor!)),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: <Widget>[
                                  Flexible(
                                    child: CustomTextContainer(
                                      textKey: _selectedCategory!.name,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall!
                                          .copyWith(
                                              color: Theme.of(context)
                                                  .colorScheme
                                                  .secondary
                                                  .withValues(alpha: 0.8)),
                                    ),
                                  ),
                                  Icon(
                                    Icons.keyboard_arrow_down,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .secondary
                                        .withValues(alpha: 0.8),
                                  )
                                ],
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                    DesignConfig.defaultHeightSizedBox,
                    BlocBuilder<TopSellingProductCubit, TopSellingProductState>(
                      builder: (context, state) {
                        if (state is TopSellingProductSuccess) {
                          return SizedBox(
                            height: 260,
                            child: ListView.separated(
                                scrollDirection: Axis.horizontal,
                                separatorBuilder: (context, index) =>
                                    DesignConfig.defaultWidthSizedBox,
                                itemCount: state.topSellingProducts.length,
                                itemBuilder: (context, index) {
                                  final product =
                                      state.topSellingProducts[index];
                                  return Container(
                                    width: 180,
                                    child: Column(
                                      children: <Widget>[
                                        CustomImageWidget(
                                          url: product.image ?? '',
                                          width: 180,
                                          height: 200,
                                          borderRadius: 8,
                                        ),
                                        const SizedBox(height: 4),
                                        Padding(
                                          padding: EdgeInsets.symmetric(
                                              horizontal: 4),
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: <Widget>[
                                              CustomTextContainer(
                                                  textKey: product.name ?? '',
                                                  maxLines: 2,
                                                  overflow:
                                                      TextOverflow.ellipsis,
                                                  style: Theme.of(context)
                                                      .textTheme
                                                      .bodySmall!),
                                              const SizedBox(height: 2),
                                              Row(
                                                children: [
                                                  Utils.setSvgImage(
                                                      'sold_count'),
                                                  CustomTextContainer(
                                                    textKey:
                                                        '${product.totalSold.toString()} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: soldKey)}',
                                                    style: Theme.of(context)
                                                        .textTheme
                                                        .bodySmall!
                                                        .copyWith(
                                                            color: Theme.of(
                                                                    context)
                                                                .colorScheme
                                                                .primary),
                                                  ),
                                                ],
                                              ),
                                            ],
                                          ),
                                        )
                                      ],
                                    ),
                                  );
                                }),
                          );
                        }
                        if (state is TopSellingProductFailure) {
                          return ErrorScreen(
                              text: state.errorMessage,
                              onPressed: fetchTopSellingProducts);
                        }
                        return const SizedBox();
                      },
                    )
                  ],
                ),
              ),
            ],
          );
        }

        return const SizedBox.shrink();
      },
    ));
  }
}
