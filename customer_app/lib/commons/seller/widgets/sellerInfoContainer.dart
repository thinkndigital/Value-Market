import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:flutter/material.dart';

import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/favoriteButton.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class SellerInfoContainer extends StatefulWidget {
  final Seller? seller;
  final int? sellerId;
  const SellerInfoContainer({Key? key, this.seller, this.sellerId})
      : super(key: key);

  @override
  _SellerInfoContainerState createState() => _SellerInfoContainerState();
}

class _SellerInfoContainerState extends State<SellerInfoContainer> {
  late Seller? seller;
  bool _isExpanded = false;
  @override
  void initState() {
    seller = widget.seller;
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return CustomDefaultContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            height: 100,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                CustomImageWidget(
                  url: seller!.storeLogo ?? "",
                  borderRadius: 50,
                  isCircularImage: true,
                ),
                DesignConfig.defaultWidthSizedBox,
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: CustomTextContainer(
                              textKey: seller!.storeName ?? "",
                              style: Theme.of(context).textTheme.titleMedium,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          FavoriteButton(
                            size: 40,
                            isSeller: true,
                            sellerId: widget.seller != null
                                ? widget.seller!.sellerId
                                : widget.sellerId,
                            seller: seller,
                            unFavoriteColor:
                                Theme.of(context).colorScheme.primary,
                          ),
                        ],
                      ),
                      DesignConfig.smallHeightSizedBox,
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Expanded(
                            child: Row(
                              children: <Widget>[
                                Column(
                                  children: [
                                    CustomTextContainer(
                                        textKey:
                                            seller!.totalProducts.toString(),
                                        style: Theme.of(context)
                                            .textTheme
                                            .titleSmall),
                                    const SizedBox(height: 2),
                                    CustomTextContainer(
                                        textKey: productsKey,
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodySmall),
                                  ],
                                ),
                                Container(
                                  margin: const EdgeInsets.symmetric(
                                      horizontal: 12),
                                  color: Theme.of(context)
                                      .inputDecorationTheme
                                      .iconColor,
                                  width: 1,
                                  height: 50,
                                ),
                                Column(
                                  children: [
                                    Row(
                                      children: [
                                        Icon(
                                          Icons.star,
                                          color: Theme.of(context)
                                              .colorScheme
                                              .primary,
                                        ),
                                        CustomTextContainer(
                                            textKey:
                                                seller!.sellerRating.toString(),
                                            style: Theme.of(context)
                                                .textTheme
                                                .titleSmall),
                                      ],
                                    ),
                                    const SizedBox(height: 2),
                                    CustomTextContainer(
                                        textKey: ratingsKey,
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodySmall),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          SizedBox(
                              width: MediaQuery.of(context).size.width * 0.05),
                          if (!context.read<UserDetailsCubit>().isGuestUser())
                            CustomRoundedButton(
                              widthPercentage: 0.2,
                              buttonTitle: chatKey,
                              horizontalPadding: 5,
                              height: 36,
                              showBorder: false,
                              onTap: () => Utils.navigateToScreen(
                                  context, Routes.chatScreen,
                                  arguments: {
                                    'id': seller!.userId,
                                  }),
                            )
                        ],
                      )
                    ],
                  ),
                ),
              ],
            ),
          ),
          if (seller!.storeDescription != null &&
              seller!.storeDescription!.isNotEmpty) ...[
            const SizedBox(
              height: 20,
            ),
            Text.rich(
              TextSpan(
                text: _isExpanded
                    ? seller!.storeDescription ?? ""
                    : seller!.storeDescription?.substring(
                            0,
                            seller!.storeDescription!.length > 100
                                ? 100
                                : seller!.storeDescription?.length) ??
                        "",
                children: [
                  if (seller!.storeDescription != null &&
                      seller!.storeDescription!.length > 100)
                    TextSpan(
                      text: _isExpanded ? " Read Less" : "... Read More",
                      style: Theme.of(context).textTheme.bodyMedium,
                      recognizer: TapGestureRecognizer()
                        ..onTap = () {
                          setState(() {
                            _isExpanded = !_isExpanded;
                          });
                        },
                    ),
                ],
              ),
              style: Theme.of(context).textTheme.bodySmall!.copyWith(
                    color: Theme.of(context)
                        .colorScheme
                        .secondary
                        .withValues(alpha: 0.8),
                  ),
            ),
          ]
        ],
      ),
    );
  }
}
