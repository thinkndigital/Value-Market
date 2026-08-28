import 'package:eshop_plus/ui/categoty/models/category.dart';
import 'package:eshop_plus/ui/explore/productFilters/widgets/categoriesFilterView.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';

class Subcategoriesfilterview extends StatelessWidget {
  final Category category;
  final Function(int) onTapCategory;
  final bool Function(int) isCategorySelected;

  const Subcategoriesfilterview(
      {super.key,
      required this.category,
      required this.onTapCategory,
      required this.isCategorySelected});

  static Widget getRouteInstance(RouteSettings settings) {
    final arguments = settings.arguments as Map<String, dynamic>;
    return Subcategoriesfilterview(
      category: arguments['category'] as Category,
      isCategorySelected: arguments['isCategorySelected'] as bool Function(int),
      onTapCategory: arguments['onTapCategory'] as Function(int),
    );
  }

  static Map<String, dynamic> buildArguments(
      {required Category category,
      required bool Function(int) isCategorySelected,
      required Function(int) onTapCategory}) {
    return {
      'category': category,
      'isCategorySelected': isCategorySelected,
      'onTapCategory': onTapCategory,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: MediaQuery.of(context).size.width,
      color: Theme.of(context).colorScheme.onPrimary,
      child: SingleChildScrollView(
        child: Column(
          children: [
            Row(
              children: [
                IconButton(
                    onPressed: () {
                      categoryNavigatorKey.currentState?.pop();
                    },
                    icon: const Icon(Icons.arrow_back_ios)),
                Expanded(
                    child: CustomTextContainer(
                        textKey: category.name,
                        style: Theme.of(context).textTheme.titleSmall))
              ],
            ),
            const SizedBox(height: 10),
            ...(category.children ?? []).map((subcategory) {
              final hasSubcategories =
                  subcategory.children?.isNotEmpty ?? false;

              final isSelected = isCategorySelected.call(subcategory.id);
              return InkWell(
                onTap: () {
                  if (hasSubcategories) {
                    categoryNavigatorKey.currentState?.pushNamed(
                        subCategoryRouteName,
                        arguments: Subcategoriesfilterview.buildArguments(
                            category: subcategory,
                            isCategorySelected: isCategorySelected,
                            onTapCategory: onTapCategory));
                  } else {
                    onTapCategory.call(subcategory.id);
                  }
                },
                child: Container(
                  padding: const EdgeInsetsDirectional.all(10.0),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: CustomTextContainer(
                          textKey: subcategory.name,
                          style:
                              Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: isSelected
                                        ? Theme.of(context).colorScheme.primary
                                        : null,
                                  ),
                        ),
                      ),
                      isSelected
                          ? Icon(
                              Icons.check,
                              color: Theme.of(context).colorScheme.primary,
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
            }),
          ],
        ),
      ),
    );
  }
}
