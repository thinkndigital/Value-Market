import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/home/brand/brandsCubit.dart';
import 'package:value_market_customer/commons/blocs/storesCubit.dart';
import 'package:value_market_customer/ui/explore/screens/exploreScreen.dart';
import 'package:value_market_customer/ui/home/widgets/buildHeader.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'brand.dart';
import '../../../utils/utils.dart';

class BrandSection extends StatelessWidget {
  BrandSection({Key? key}) : super(key: key);
  double extraWidthAndHeight = 0;
  @override
  Widget build(BuildContext context) {
    Size size = MediaQuery.of(context).size;
    extraWidthAndHeight = context
                .read<StoresCubit>()
                .getDefaultStore()
                .storeSettings!
                .brandStyle ==
            'brands_style_1'
        ? 0
        : 10;
    return BlocConsumer<BrandsCubit, BrandsState>(
      listener: (context, state) {},
      builder: (context, state) {
        if (state is BrandsFetchSuccess) {
          return Container(
            color: Theme.of(context).colorScheme.primaryContainer,
            padding: const EdgeInsetsDirectional.symmetric(
                vertical: appContentHorizontalPadding),
            margin: const EdgeInsetsDirectional.only(
                bottom: appContentHorizontalPadding / 2),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                BuildHeader(
                  title: brandsKey,
                  onTap: () {},
                  showSeeAllButton: false,
                ),
                DesignConfig.defaultHeightSizedBox,
                SizedBox(
                  height: (context
                              .read<StoresCubit>()
                              .getDefaultStore()
                              .storeSettings!
                              .brandStyle ==
                          'brands_style_1')
                      ? 150 + extraWidthAndHeight
                      : 100 + extraWidthAndHeight,
                  child: ListView.separated(
                      separatorBuilder: (context, index) =>
                          DesignConfig.defaultWidthSizedBox,
                      padding: const EdgeInsetsDirectional.symmetric(
                          horizontal: appContentHorizontalPadding),
                      clipBehavior: Clip.none,
                      scrollDirection: Axis.horizontal,
                      shrinkWrap: true,
                      itemCount: state.brands.length,
                      itemBuilder: (context, index) =>
                          buildBrands(state.brands[index], size, context)),
                ),
              ],
            ),
          );
        }
        return const Center(child: SizedBox.shrink());
      },
    );
  }

//here Brands_card_style set in every store. 'style_1' is for rectangle and 'style_2' is for square. 'style_3' is for circle
//Brands_style is whether to display Brands name or not
  buildBrands(Brand brand, Size size, BuildContext context) {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, Routes.exploreScreen,
          arguments: ExploreScreen.buildArguments(
              brandId: brand.id.toString(), title: brand.name)),
      child: SizedBox(
        width: 75 + extraWidthAndHeight,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            CustomImageWidget(
              url: brand.image!,
              width: 75 + extraWidthAndHeight,
              height: 100 + extraWidthAndHeight,
              borderRadius: borderRadius,
              boxFit: BoxFit.fitHeight,
            ),
            if (context
                    .read<StoresCubit>()
                    .getDefaultStore()
                    .storeSettings!
                    .brandStyle ==
                'brands_style_1') ...[
             DesignConfig.smallHeightSizedBox,
              Expanded(
                child: CustomTextContainer(
                  textKey: brand.name ?? "",
                  style: Theme.of(context).textTheme.bodyMedium,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                ),
              )
            ],
          ],
        ),
      ),
    );
  }
}
