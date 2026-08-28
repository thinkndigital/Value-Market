import 'package:value_market_customer/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/explore/cubits/getProductRatingCubit.dart';
import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class AllCustomerImagesScreen extends StatefulWidget {
  final int productId;
  final bool isComboProduct;
  const AllCustomerImagesScreen(
      {Key? key, required this.productId, required this.isComboProduct})
      : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => ProductRatingCubit(),
        child: AllCustomerImagesScreen(
          productId: Get.arguments['productId'] as int,
          isComboProduct: Get.arguments['isComboProduct'] ?? false,
        ),
      );

  @override
  State<AllCustomerImagesScreen> createState() =>
      _AllCustomerImagesScreenState();
}

class _AllCustomerImagesScreenState extends State<AllCustomerImagesScreen> {
  @override
  void initState() {
    super.initState();

    getReviews();
  }

  getReviews() {
    Future.delayed(Duration.zero, () {
      context.read<ProductRatingCubit>().getProductRating(
          params: {
            ApiURL.productIdApiKey: widget.productId,
            ApiURL.hasImagesApiKey: 1,
            ApiURL.limitApiKey: 30,
          },
          apiUrl: widget.isComboProduct
              ? ApiURL.getComboProductRating
              : ApiURL.getProductRating);
    });
  }

  void loadMoreReviews() {
    context.read<ProductRatingCubit>().loadMore(
        params: {
          ApiURL.productIdApiKey: widget.productId,
          ApiURL.hasImagesApiKey: 1,
          ApiURL.limitApiKey: 30,
        },
        apiUrl: widget.isComboProduct
            ? ApiURL.getComboProductRating
            : ApiURL.getProductRating);
  }

  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
        appBar: const CustomAppbar(titleKey: customerPhotosKey),
        body: BlocBuilder<ProductRatingCubit, ProductRatingState>(
          builder: (context, state) {
            if (state is ProductRatingSuccess) {
              return Padding(
                  padding: const EdgeInsetsDirectional.symmetric(
                      vertical: 12, horizontal: appContentHorizontalPadding),
                  child: GridView.builder(
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 3,
                        childAspectRatio: 1,
                      ),
                      itemCount: state.productRating.getAllImages().keys.length,
                      itemBuilder: (context, index) {
                        return GestureDetector(
                          onTap: () => Utils.navigateToScreen(
                              context, Routes.fullScreenImageScreen,
                              arguments: {
                                'imageUrl': state.productRating
                                    .getAllImages()
                                    .keys
                                    .toList()[index],
                                'reviews': state.productRating
                                    .getAllImages()
                                    .values
                                    .toList(),
                                'index': index,
                                'imageUrls': state.productRating
                                    .getAllImages()
                                    .keys
                                    .toList(),
                                'title': customerPhotosKey
                              }),
                          child: Hero(
                            tag: 'image$index',
                            child: CustomImageWidget(
                              url: state.productRating
                                  .getAllImages()
                                  .keys
                                  .toList()[index],
                              width: 85,
                              height: 85,
                              borderRadius: 2,
                            ),
                          ),
                        );
                      }));
            }
            if (state is ProductRatingFetchInProgress) {
              return CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.primary);
            }
            return const SizedBox();
          },
        ),
      ),
    );
  }
}
