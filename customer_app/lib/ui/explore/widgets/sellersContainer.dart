import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/commons/seller/blocs/sellersCubit.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/favoriteButton.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class SellersContainer extends StatelessWidget {
  final List<Seller> sellers;
  final List<int>? sellerIds;

  const SellersContainer({super.key, required this.sellers, this.sellerIds});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
            height: sellerIds == null
                ? MediaQuery.of(context).padding.top +
                    appContentHorizontalPadding +
                    130
                : 0),
        Expanded(
          child: Container(
              color: Theme.of(context).colorScheme.primaryContainer,
              height: MediaQuery.of(context).size.height,
              width: MediaQuery.of(context).size.width,
              padding: const EdgeInsetsDirectional.only(
                start: appContentHorizontalPadding,
                end: appContentHorizontalPadding,
              ),
              child: NotificationListener<ScrollUpdateNotification>(
                onNotification: (notification) {
                  if (notification.metrics.pixels >=
                      notification.metrics.maxScrollExtent) {
                    if (context.read<SellersCubit>().hasMore()) {
                      context.read<SellersCubit>().loadMore(
                          storeId:
                              context.read<StoresCubit>().getDefaultStore().id!,
                          sellerIds: sellerIds);
                    }
                  }
                  return true;
                },
                child: GridView.builder(
                    padding: EdgeInsetsDirectional.only(
                      top: appContentHorizontalPadding,
                      bottom: appContentHorizontalPadding,
                    ),
                    itemCount: sellers.length,
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            crossAxisSpacing: appContentHorizontalPadding,
                            childAspectRatio: 0.7,
                            mainAxisSpacing: appContentHorizontalPadding),
                    itemBuilder: (context, index) {
                      final seller = sellers[index];
                      return GestureDetector(
                        onTap: () => Utils.navigateToScreen(
                          context,
                          Routes.sellerDetailScreen,
                          arguments: {
                            'seller': seller,
                          },
                        ),
                        child: Container(
                          decoration: BoxDecoration(
                            color:
                                Theme.of(context).colorScheme.primaryContainer,
                            border: Border.all(
                                color: Theme.of(context)
                                    .colorScheme
                                    .secondary
                                    .withValues(alpha: 0.1),
                                width: 0.5),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          padding: const EdgeInsetsDirectional.all(8),
                          child:
                              LayoutBuilder(builder: (context, boxConstraints) {
                            return Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                CustomImageWidget(
                                  url: (seller.storeLogo ?? ""),
                                  borderRadius: 4,
                                  width: boxConstraints.maxWidth,
                                  height: boxConstraints.maxHeight * (0.525),
                                  child: Stack(
                                    children: [
                                      Positioned.directional(
                                        textDirection:
                                            Directionality.of(context),
                                        top: 7.5,
                                        end: 7.5,
                                        child: FavoriteButton(
                                          isSeller: true,
                                          sellerId: seller.sellerId!,
                                          seller: seller,
                                        ),
                                      ),
                                      double.parse(
                                                  seller.sellerRating ?? "0") ==
                                              0
                                          ? const SizedBox()
                                          : Align(
                                              alignment: AlignmentDirectional
                                                  .bottomEnd,
                                              child: Container(
                                                margin:
                                                    const EdgeInsetsDirectional
                                                        .only(
                                                        bottom: 7.5, end: 7.5),
                                                padding: const EdgeInsetsDirectional
                                                    .symmetric(
                                                    horizontal: 5.0,
                                                    vertical:
                                                        appContentHorizontalPadding *
                                                            (0.125)),
                                                decoration: BoxDecoration(
                                                  color: Theme.of(context)
                                                      .colorScheme
                                                      .primaryContainer,
                                                  borderRadius:
                                                      BorderRadius.circular(4),
                                                  border: Border.all(
                                                      color: Theme.of(context)
                                                          .colorScheme
                                                          .secondary
                                                          .withValues(
                                                              alpha: 0.75),
                                                      width: 0.5),
                                                ),
                                                child: Row(
                                                    mainAxisSize:
                                                        MainAxisSize.min,
                                                    children: [
                                                      Icon(Icons.star,
                                                          size: 12.5,
                                                          color:
                                                              Theme.of(context)
                                                                  .colorScheme
                                                                  .primary),
                                                      const SizedBox(
                                                        width: 2.5,
                                                      ),
                                                      CustomTextContainer(
                                                        textKey: seller
                                                                .sellerRating ??
                                                            "0",
                                                        style: Theme.of(context)
                                                            .textTheme
                                                            .labelSmall,
                                                      ),
                                                    ]),
                                              ),
                                            )
                                    ],
                                  ),
                                ),
                                Expanded(
                                  child: Padding(
                                    padding:
                                        const EdgeInsetsDirectional.all(8.0),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        CustomTextContainer(
                                          textKey: seller.storeName ?? "",
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: Theme.of(context)
                                              .textTheme
                                              .titleMedium,
                                        ),
                                        CustomTextContainer(
                                          textKey:
                                              "${seller.totalProducts ?? 0} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: productsKey)}",
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodyMedium,
                                        ),
                                        const Spacer(),
                                        CustomRoundedButton(
                                          height: 36.0,
                                          widthPercentage: 1.0,
                                          buttonTitle: viewStoreKey,
                                          showBorder: false,
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodyLarge
                                              ?.copyWith(
                                                  fontWeight: FontWeight.w600,
                                                  color: Theme.of(context)
                                                      .colorScheme
                                                      .onPrimary),
                                        )
                                      ],
                                    ),
                                  ),
                                ),
                              ],
                            );
                          }),
                        ),
                      );
                    }),
              )),
        ),
      ],
    );
  }
}
