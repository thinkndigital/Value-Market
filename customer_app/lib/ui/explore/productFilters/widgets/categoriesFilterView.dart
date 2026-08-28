import 'package:value_market_customer/commons/blocs/storesCubit.dart';
import 'package:value_market_customer/ui/explore/productFilters/widgets/subcategoriesFilterView.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/commons/widgets/errorContainer.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../categoty/cubits/categoryCubit.dart';

final GlobalKey<NavigatorState> categoryNavigatorKey = GlobalKey();

const String subCategoryRouteName = 'subcategory';

class CategoriesFilterView extends StatelessWidget {
  final Function(int) onTapCategory;
  final bool Function(int) isCategorySelected;

  const CategoriesFilterView(
      {super.key,
      required this.onTapCategory,
      required this.isCategorySelected});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<CategoryCubit, CategoryState>(
      builder: (context, state) {
        if (state is CategoryFetchSuccess) {
          return Navigator(
            key: categoryNavigatorKey,
            onGenerateRoute: (settings) {
              if (settings.name == subCategoryRouteName) {
                return CupertinoPageRoute(
                    builder: (context) =>
                        Subcategoriesfilterview.getRouteInstance(settings));
              }
              return PageRouteBuilder(pageBuilder: (context, _, __) {
                return ListView.builder(
                  itemCount: state.categories.length,
                  itemBuilder: (context, index) {
                    final category = state.categories[index];
                    final hasSubcategories =
                        category.children?.isNotEmpty ?? false;

                    final isSelected = isCategorySelected.call(category.id);

                    return InkWell(
                      onTap: () {
                        if (hasSubcategories) {
                          categoryNavigatorKey.currentState?.pushNamed(
                              subCategoryRouteName,
                              arguments: Subcategoriesfilterview.buildArguments(
                                  category: category,
                                  isCategorySelected: isCategorySelected,
                                  onTapCategory: onTapCategory));
                        } else {
                          onTapCategory.call(category.id);
                        }
                      },
                      child: Container(
                        padding: const EdgeInsetsDirectional.all(10.0),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: CustomTextContainer(
                                textKey: category.name,
                                style: Theme.of(context)
                                    .textTheme
                                    .bodyMedium
                                    ?.copyWith(
                                      color: isSelected
                                          ? Theme.of(context)
                                              .colorScheme
                                              .primary
                                          : null,
                                    ),
                              ),
                            ),
                            isSelected
                                ? Icon(
                                    Icons.check,
                                    color:
                                        Theme.of(context).colorScheme.primary,
                                    size: 20,
                                  )
                                : const SizedBox(),
                            hasSubcategories
                                ? const Icon(
                                    Icons.arrow_forward_ios,
                                    size: 20,
                                  )
                                : const SizedBox()
                          ],
                        ),
                      ),
                    );
                  },
                );
              });
            },
          );
        }

        if (state is CategoryFetchFailure) {
          return ErrorContainer(
            errorMessage: state.errorMessage,
            onTapRetry: () {
              context.read<CategoryCubit>().fetchCategories(
                  storeId: context.read<StoresCubit>().getDefaultStore().id!);
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
