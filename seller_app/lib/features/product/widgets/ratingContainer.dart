import 'package:eshopplus_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:eshopplus_seller/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';
import 'package:eshopplus_seller/features/product/blocs/getProductRatingCubit.dart';
import 'package:eshopplus_seller/commons/models/product.dart';
import 'package:eshopplus_seller/features/product/models/productRating.dart';

import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customDefaultContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class RatingContainer extends StatefulWidget {
  final Product product;
  final bool isComboProduct;

  @override
  RatingContainer({
    super.key,
    required this.product,
    required this.isComboProduct,
  });

  @override
  RatingContainerState createState() => RatingContainerState();
}

class RatingContainerState extends State<RatingContainer>
    with SingleTickerProviderStateMixin {
  late AnimationController _animationController;
  late Animation<double> _animation;
  late String rating;
  late int totalRatings;
  late int totalReviews;
  late Map<int, String> ratingsBreakdown;
  int _selectedStar = 0; // Default selected star rating

  @override
  void initState() {
    super.initState();
    if (widget.product.noOfRatings != null && widget.product.noOfRatings! > 0) {
      getRatingData();
    }
    _animationController = AnimationController(
      duration: const Duration(seconds: 2),
      vsync: this,
    );

    _animation = Tween<double>(begin: 0, end: 1).animate(_animationController);
  }

  getRatingData() {
    Future.delayed(Duration.zero, () {
      {
        context.read<ProductRatingCubit>().getProductRating(
            params: {
              ApiURL.productIdApiKey: widget.product.id,
              ApiURL.offsetApiKey: 0,
              if (_selectedStar != 0) ApiURL.ratingApiKey: _selectedStar
            },
            apiUrl: widget.isComboProduct
                ? ApiURL.getComboProductRating
                : ApiURL.getProductRating);
      }
    });
  }

  loadMoreRatings() {
    context.read<ProductRatingCubit>().loadMore(
        params: {
          ApiURL.productIdApiKey: widget.product.id,
          if (_selectedStar != 0) ApiURL.ratingApiKey: _selectedStar
        },
        apiUrl: widget.isComboProduct
            ? ApiURL.getComboProductRating
            : ApiURL.getProductRating);
  }

  void onScroll() {
    // This function will be called when the user scrolls to the desired portion
    _animationController.forward(); // Starts the animation
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return widget.product.noOfRatings != null && widget.product.noOfRatings! > 0
        ? buildBody()
        : ErrorScreen(text: noRatingsKey, onPressed: getRatingData);
  }

  Widget buildBody() {
    return SafeAreaWithBottomPadding(
      child: BlocConsumer<ProductRatingCubit, ProductRatingState>(
        listener: (context, state) {
          if (state is ProductRatingSuccess) {
            rating = state.productRating.productRating.toString();
            totalRatings = state.productRating.noOfRating ?? 0;
            totalReviews = state.productRating.noOfReviews ?? 0;
            ratingsBreakdown = {
              5: state.productRating.star5 ?? "0",
              4: state.productRating.star4 ?? "0",
              3: state.productRating.star3 ?? "0",
              2: state.productRating.star2 ?? "0",
              1: state.productRating.star1 ?? "0",
            };
          }
        },
        builder: (context, state) {
          if (state is ProductRatingSuccess) {
            return SingleChildScrollView(
              child: Column(
                children: [
                  DesignConfig.smallHeightSizedBox,
                  buildRatingWidget(),
                  buildReviewList(state)
                ],
              ),
            );
          }
          if (state is ProductRatingFetchInProgress) {
            return CustomCircularProgressIndicator(
              indicatorColor: Theme.of(context).colorScheme.primary,
            );
          }
          return const SizedBox();
        },
      ),
    );
  }

  CustomDefaultContainer buildRatingWidget() {
    return CustomDefaultContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Overall Rating
              Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Text(rating,
                      style: Theme.of(context).textTheme.displayMedium),
                  const SizedBox(height: 4),
                  Text('$totalRatings Ratings',
                      style: Theme.of(context).textTheme.bodySmall!.copyWith(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.67))),
                  Text('$totalReviews Reviews',
                      style: Theme.of(context).textTheme.bodySmall!.copyWith(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.67))),
                ],
              ),
              Container(
                  margin: const EdgeInsets.symmetric(horizontal: 32),
                  color: Theme.of(context).inputDecorationTheme.iconColor,
                  width: 1,
                  height: 100),
              // Ratings Breakdown
              SizedBox(
                width: MediaQuery.of(context).size.width / 2,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: List.generate(5, (index) {
                    int starCount = 5 - index;
                    int count = int.parse(ratingsBreakdown[starCount]!);
                    double percentage =
                        totalRatings > 0 ? (count / totalRatings) * 100 : 0;
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 2.0),
                      child: Row(
                        children: [
                          Text('$starCount',
                              style: Theme.of(context).textTheme.labelMedium),
                          const SizedBox(width: 4),
                          Icon(
                            Icons.star,
                            color: Theme.of(context).colorScheme.primary,
                            size: 14,
                          ),
                          DesignConfig.smallWidthSizedBox,
                          Expanded(
                            child: AnimatedBuilder(
                                animation: _animation,
                                builder: (context, child) {
                                  return LinearProgressIndicator(
                                    borderRadius: BorderRadius.circular(4),
                                    value: percentage / 100,
                                    minHeight: 4,
                                    backgroundColor: Color(0xFFB4B6B8)
                                        .withValues(alpha: 0.5),
                                    color:
                                        Theme.of(context).colorScheme.primary,
                                  );
                                }),
                          ),
                          DesignConfig.smallWidthSizedBox,
                          Text('$count',
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall!
                                  .copyWith(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .secondary
                                          .withValues(alpha: 0.67))),
                        ],
                      ),
                    );
                  }),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  buildReviewList(ProductRatingSuccess state) {
    return Column(children: <Widget>[
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (state.productRating.getAllImages().isNotEmpty)
            DesignConfig.smallHeightSizedBox,
          if (totalReviews > 0) ...[
            CustomDefaultContainer(
                child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                buildReviewTitle(),
                DesignConfig.defaultHeightSizedBox,
                NotificationListener<ScrollUpdateNotification>(
                  onNotification: (notification) {
                    if (notification.metrics.pixels ==
                        notification.metrics.maxScrollExtent) {
                      if (context.read<ProductRatingCubit>().hasMore()) {
                        loadMoreRatings();
                      }
                    }
                    return true;
                  },
                  child: ListView.separated(
                      separatorBuilder: (context, index) =>
                          DesignConfig.defaultHeightSizedBox,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: state.productRating.ratingData.length,
                      itemBuilder: (context, index) {
                        if (context.read<ProductRatingCubit>().hasMore()) {
                          if (index ==
                              state.productRating.ratingData.length - 1) {
                            if (context
                                .read<ProductRatingCubit>()
                                .fetchMoreError()) {
                              return Center(
                                child: CustomTextButton(
                                    buttonTextKey: retryKey,
                                    onTapButton: () {
                                      loadMoreRatings();
                                    }),
                              );
                            }

                            return Center(
                              child: CustomCircularProgressIndicator(
                                  indicatorColor:
                                      Theme.of(context).colorScheme.primary),
                            );
                          }
                        }
                        if (state.productRating.ratingData[index].comment !=
                            '') {
                          return ReviewWidget(
                            review: state.productRating.ratingData[index],
                            showImages: true,
                          );
                        }
                        return const SizedBox();
                      }),
                ),
              ],
            ))
          ]
        ],
      ),
    ]);
  }

  Row buildReviewTitle() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        CustomTextContainer(
          textKey: customerReviewsKey,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        GestureDetector(
          onTap: () {
            Utils.openModalBottomSheet(context, buildSortingList(),
                    staticContent: true)
                .then((value) {
              if (_selectedStar != 0) {
                getRatingData();
              } else {
                setState(() {});
              }
            });
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primaryContainer,
                borderRadius: BorderRadius.circular(borderRadius),
                border: Border.all(
                    color: Theme.of(context).inputDecorationTheme.iconColor!)),
            child: Row(
              children: <Widget>[
                CustomTextContainer(
                  textKey: allReviewsKey,
                  style: Theme.of(context).textTheme.bodySmall!.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .secondary
                          .withValues(alpha: 0.8)),
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
      ],
    );
  }

  buildSortingList() {
    return StatefulBuilder(
        builder: (BuildContext buildcontext, StateSetter setState) {
      return FilterContainerForBottomSheet(
        title: filterReviewKey,
        content: Column(
          children: <Widget>[
            for (int stars in [5, 4, 3, 2, 1])
              _buildStarFilterOption(stars, setState),
          ],
        ),
        borderedButtonTitle: clearFiltersKey,
        borderedButtonOnTap: () {
          setState(() {
            _selectedStar = 0; // Reset to no filter
          });
          Navigator.pop(context, _selectedStar);
        },
        primaryButtonTitle: applyKey,
        primaryButtonOnTap: () {
          Navigator.pop(context, _selectedStar);
        },
      );
    });
  }

  Widget _buildStarFilterOption(int stars, StateSetter setState) {
    return RadioListTile<int>(
      contentPadding: EdgeInsets.zero,
      value: stars,
      groupValue: _selectedStar,
      onChanged: (value) {
        setState(() {
          _selectedStar = value!;
        });
      },
      title: Row(
        children: [
          CustomTextContainer(
            textKey: '$stars',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(width: 2),
          Icon(
            Icons.star,
            color: Theme.of(context).colorScheme.secondary,
            size: 16,
          ),
        ],
      ),
    );
  }
}

class ReviewWidget extends StatelessWidget {
  final RatingData review;
  final bool showImages;
  const ReviewWidget({Key? key, required this.review, required this.showImages})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          children: [
            Container(
              padding: const EdgeInsetsDirectional.symmetric(
                  horizontal: 8, vertical: 2),
              decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(4),
                  color: successStatusColor),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  CustomTextContainer(
                      textKey: review.rating.toString(),
                      style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                          color: Theme.of(context).colorScheme.onPrimary)),
                  Icon(
                    Icons.star,
                    color: Theme.of(context).colorScheme.onPrimary,
                    size: 18,
                  ),
                ],
              ),
            ),
            DesignConfig.smallWidthSizedBox,
            CustomTextContainer(
                textKey: review.title ?? "",
                maxLines: 1,
                style: Theme.of(context).textTheme.bodyMedium),
          ],
        ),
        const SizedBox(height: 10),
        CustomTextContainer(
            textKey: review.comment ?? '',
            style: Theme.of(context).textTheme.bodyMedium),
        if (showImages &&
            review.images != null &&
            review.images!.isNotEmpty) ...[
          DesignConfig.smallHeightSizedBox,
          SizedBox(
            height: 50,
            child: ListView.separated(
                separatorBuilder: (context, index) =>
                    DesignConfig.smallWidthSizedBox,
                scrollDirection: Axis.horizontal,
                itemCount: review.images!.length,
                itemBuilder: (context, index) {
                  return GestureDetector(
                    onTap: () => Utils.navigateToScreen(
                        context, Routes.fullScreenImageScreen,
                        arguments: {
                          'imageUrl': review.images![index],
                          'index': index,
                          'imageUrls': review.images,
                        }),
                    child: CustomImageWidget(
                      url: review.images![index],
                      width: 40,
                      height: 50,
                    ),
                  );
                }),
          ),
        ],
        DesignConfig.smallHeightSizedBox,
        Text.rich(
          style: Theme.of(context).textTheme.bodySmall!.copyWith(
              color: Theme.of(context)
                  .colorScheme
                  .secondary
                  .withValues(alpha: 0.80)),
          TextSpan(
            children: [
              TextSpan(text: review.userName),
              const TextSpan(
                text: ' | ',
              ),
              TextSpan(
                text: review.createdAt,
              ),
            ],
          ),
        ),
      ],
    );
  }
}
