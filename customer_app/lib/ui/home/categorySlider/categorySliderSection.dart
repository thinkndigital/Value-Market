import 'package:cached_network_image/cached_network_image.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/home/categorySlider/categorySliderCubit.dart';
import 'package:value_market_customer/ui/categoty/models/category.dart';
import 'package:value_market_customer/ui/home/categorySlider/categorySlider.dart';
import 'package:value_market_customer/ui/explore/screens/exploreScreen.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';
import 'package:value_market_customer/utils/utils.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'package:value_market_customer/core/constants/themeConstants.dart';
import '../../../utils/designConfig.dart';
import '../widgets/buildHeader.dart';

class CategorySliderSection extends StatelessWidget {
  const CategorySliderSection({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    Size size = MediaQuery.of(context).size;
    return BlocConsumer<CategorySliderCubit, CategorySliderState>(
      listener: (context, state) {},
      builder: (context, state) {
        if (state is CategorySliderFetchSuccess) {
          return ListView.separated(
              separatorBuilder: (context, index) =>
                  DesignConfig.smallHeightSizedBox,
              clipBehavior: Clip.none,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: state.categorySlider.length,
              itemBuilder: (context, index) => buildCategorySlider(
                  state.categorySlider[index], size, context));
        }
        return DesignConfig.smallHeightSizedBox;
      },
    );
  }

  buildCategorySlider(
      CategorySlider categorySlider, Size size, BuildContext context) {
    return Container(
      color: Utils.getColorFromHexValue(categorySlider.backgroundColor ?? '') ??
          Theme.of(context).colorScheme.primaryContainer,
      padding: const EdgeInsetsDirectional.symmetric(
          vertical: appContentHorizontalPadding),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          categorySlider.style == 'style_1'
              ? BuildHeader(
                  title: categorySlider.title ?? "",
                  onTap: () {},
                  showSeeAllButton: false,
                )
              : Container(
                  width: size.width,
                  height: 100,
                  margin: const EdgeInsetsDirectional.symmetric(
                      horizontal: appContentHorizontalPadding),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(8),
                    image: DecorationImage(
                        fit: BoxFit.cover,
                        image: CachedNetworkImageProvider(
                          categorySlider.bannerImage ?? "",
                          maxHeight: cachedMaxWidthAndHeight,
                          maxWidth: cachedMaxWidthAndHeight,
                        )),
                  ),
                ),
          DesignConfig.defaultHeightSizedBox,
          if (categorySlider.categoryData != null &&
              categorySlider.categoryData!.isNotEmpty)
            SizedBox(
              height: 100,
              child: ListView.separated(
                  separatorBuilder: (context, index) =>
                      DesignConfig.defaultWidthSizedBox,
                  padding: const EdgeInsetsDirectional.symmetric(
                      horizontal: appContentHorizontalPadding),
                  clipBehavior: Clip.none,
                  scrollDirection: Axis.horizontal,
                  shrinkWrap: true,
                  itemCount: categorySlider.categoryData!.length,
                  itemBuilder: (context, index) {
                    Category category = categorySlider.categoryData![index];
                    return GestureDetector(
                        onTap: () {
                          if (category.children!.isEmpty) {
                            Utils.navigateToScreen(
                                context, Routes.exploreScreen,
                                arguments: ExploreScreen.buildArguments(
                                    category: category));
                          } else {
                            Utils.navigateToScreen(
                                context, Routes.subCategoryScreen,
                                arguments: {
                                  'category': category,
                                });
                          }
                        },
                        child: CustomImageWidget(
                          url: category.image,
                          width: 75,
                          height: 100,
                          borderRadius: 8,
                        ));
                  }),
            ),
        ],
      ),
    );
  }
}
