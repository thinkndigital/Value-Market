import 'dart:async';

import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/ui/home/offer/offerCubit.dart';
import 'package:eshop_plus/commons/product/blocs/productsCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';

import 'package:eshop_plus/ui/home/offer/offerSlider.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/ui/explore/screens/exploreScreen.dart';
import 'package:eshop_plus/ui/explore/productDetails/productDetailsScreen.dart';
import 'package:eshop_plus/ui/home/widgets/buildHeader.dart';
import 'package:eshop_plus/ui/home/slider/sliderSection.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class OfferSection extends StatefulWidget {
  const OfferSection({Key? key}) : super(key: key);

  @override
  _OfferSectionState createState() => _OfferSectionState();
}

class _OfferSectionState extends State<OfferSection> {
  late String offerStyle;

  late Size size;

  @override
  Widget build(BuildContext context) {
    offerStyle = context
            .read<StoresCubit>()
            .getDefaultStore()
            .storeSettings!
            .offerSliderStyle ??
        'slider_style_1';
    size = MediaQuery.of(context).size;
    return BlocListener<ProductsCubit, ProductsState>(
      listener: (context, state) {
        if (state is ProductsFetchSuccess) {
          if (state.products[0].type == comboProductType) {
            Utils.navigateToScreen(context, Routes.productDetailsScreen,
                arguments: ProductDetailsScreen.buildArguments(
                    product: state.products[0], isComboProduct: true));
          } else {
            Utils.navigateToScreen(context, Routes.productDetailsScreen,
                arguments: ProductDetailsScreen.buildArguments(
                  product: state.products[0],
                ));
          }
        }
      },
      child: BlocConsumer<OfferCubit, OfferState>(
        listener: (context, state) {},
        builder: (context, state) {
          if (state is OfferFetchSuccess) {
            return Container(
                margin: const EdgeInsetsDirectional.only(
                    bottom: appContentHorizontalPadding / 2),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    DesignConfig.smallHeightSizedBox,
                    ...List.generate(
                      state.sliders.length,
                      (index) => offerStyle == 'slider_style_1' ||
                              offerStyle == 'slider_style_2'
                          ? buildStyle1And2(state.sliders[index])
                          : offerStyle == 'slider_style_3' ||
                                  offerStyle == 'slider_style_4'
                              ? buildStyle3And4(state.sliders[index])
                              : OfferSliderWidget5(
                                  offerSlider: state.sliders[index],
                                  onTap: onTapOffer,
                                ),
                    ),
                  ],
                ));
          }

          return const Center(child: SizedBox.shrink());
        },
      ),
    );
  }

  static onTapOffer(BuildContext context, OfferImages offer) {
    if (offer.type == 'offer_url' && offer.link!.isNotEmpty) {
      Utils.launchURL(offer.link.toString());
    }
    //navigate to product details screen for specific product
    if (offer.type == 'products') {
      context.read<ProductsCubit>().getProducts(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          productIds: [offer.typeId!]);
    }
    //navigate to explore screen for all products
    if (offer.type == 'all_products') {
      Utils.navigateToScreen(context, Routes.exploreScreen,
          arguments: ExploreScreen.buildArguments(title: offer.title!));
    }
    //navigate for specific category
    if (offer.type == 'categories') {
      if (offer.categoryData != null) {
        if (offer.categoryData!.children!.isEmpty) {
          Utils.navigateToScreen(context, Routes.exploreScreen,
              arguments:
                  ExploreScreen.buildArguments(category: offer.categoryData));
        } else {
          Utils.navigateToScreen(context, Routes.subCategoryScreen, arguments: {
            'category': offer.categoryData,
          });
        }
      }
    }
    //navigate to explore screen for specific combo product
    if (offer.type == 'combo_products') {
      context.read<ProductsCubit>().getProducts(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          isComboProduct: true,
          productIds: [offer.typeId!]);
    }
    //navigate to explore screen for all combo products
    if (offer.type == 'all_combo_products') {
      Utils.navigateToScreen(
        context,
        Routes.exploreScreen,
        arguments: ExploreScreen.buildArguments(
          isComboProduct: true,
          title: offer.title!,
        ),
      );
    }
    //navigate to explore screen for brand
    if (offer.type == 'brand') {
      Utils.navigateToScreen(
        context,
        Routes.exploreScreen,
        arguments: ExploreScreen.buildArguments(
          brandId: offer.typeId.toString(),
          title: offer.data![0].name!,
        ),
      );
    }
  }

  Widget buildStyle1And2(OfferSlider slider) {
    return CustomDefaultContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          offerStyle == 'slider_style_1'
              ? BuildHeader(
                  title: slider.title!,
                  subtitle: '',
                  showSeeAllButton: false,
                  onTap: () {})
              : Container(
                  child: CustomImageWidget(
                    url: slider.bannerImage!,
                    height: 100,
                    width: double.infinity,
                    borderRadius: 8,
                  ),
                ),
          DesignConfig.defaultHeightSizedBox,
          SizedBox(
            height: 150,
            child: ListView.separated(
              separatorBuilder: (context, index) =>
                  DesignConfig.defaultWidthSizedBox,
              scrollDirection: Axis.horizontal,
              itemCount: slider.offerImages?.length ?? 0,
              itemBuilder: (context, index) => GestureDetector(
                onTap: () => onTapOffer(context, slider.offerImages![index]),
                child: Container(
                    width: size.width * 0.4,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: Colors.transparent,
                      ),
                    ),
                    child: CustomImageWidget(
                      url: slider.offerImages![index].image.toString(),
                      borderRadius: 20,
                    )),
              ),
            ),
          ),
        ],
      ),
    );
  }

  buildStyle3And4(OfferSlider slider) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (offerStyle == 'slider_style_3')
          BuildHeader(
              title: slider.title!,
              subtitle: '',
              showSeeAllButton: false,
              onTap: () {}),
        OfferSliderWidget3And4(slider: slider, onTap: onTapOffer),
        DesignConfig.defaultHeightSizedBox,
      ],
    );
  }
}

class OfferSliderWidget3And4 extends StatefulWidget {
  final OfferSlider slider;
  final Function(BuildContext, OfferImages) onTap;
  const OfferSliderWidget3And4(
      {Key? key, required this.slider, required this.onTap})
      : super(key: key);

  @override
  _OfferSliderWidget3And4State createState() => _OfferSliderWidget3And4State();
}

class _OfferSliderWidget3And4State extends State<OfferSliderWidget3And4> {
  int _sliderIndex = 0;
  int slidersLength = 0;
  final PageController _pageController =
      PageController(initialPage: 0, viewportFraction: 0.85);
  late Timer _timer;
  @override
  void initState() {
    super.initState();
    slidersLength = widget.slider.offerImages!.length;
    _timer = Timer.periodic(const Duration(seconds: 2), (Timer timer) {
      if (_sliderIndex < slidersLength - 1) {
        _sliderIndex++;
      } else {
        _sliderIndex = 0;
      }
      if (_pageController.hasClients) {
        _pageController.animateToPage(
          _sliderIndex,
          duration: const Duration(seconds: 1),
          curve: Curves.easeIn,
        );
      }
    });
  }

  @override
  void dispose() {
    super.dispose();
    _timer.cancel();
    _pageController.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          height: 150,
          child: PageView.builder(
              controller: _pageController,
              itemCount: widget.slider.offerImages?.length ?? 0,
              onPageChanged: (index) {
                setState(() {
                  _sliderIndex = index;
                });
              },
              itemBuilder: (context, index) => GestureDetector(
                    onTap: () => widget.onTap(
                        context, widget.slider.offerImages![index]),
                    child: Container(
                      margin:
                          const EdgeInsetsDirectional.symmetric(horizontal: 8),
                      child: CustomImageWidget(
                        url: widget.slider.offerImages![index].image.toString(),
                        borderRadius: 20,
                        width: MediaQuery.of(context).size.width * 0.8,
                      ),
                    ),
                  )),
        ),
        DesignConfig.smallHeightSizedBox,
        Pageindicator(
            index: _sliderIndex, length: widget.slider.offerImages!.length),
      ],
    );
  }
}

class OfferSliderWidget5 extends StatelessWidget {
  final OfferSlider offerSlider;
  final Function(BuildContext, OfferImages) onTap;
  OfferSliderWidget5({required this.offerSlider, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
          margin: const EdgeInsets.all(appContentHorizontalPadding),
          child: CustomImageWidget(
            url: offerSlider.bannerImage!,
            height: 100,
            width: double.infinity,
            borderRadius: 8,
          ),
        ),
        LayoutBuilder(builder: (context, boxConstraint) {
          return Wrap(
              direction: Axis.horizontal,
              alignment: WrapAlignment.start,
              runAlignment: WrapAlignment.start,
              spacing: appContentHorizontalPadding,
              runSpacing: appContentHorizontalPadding,
              children:
                  List.generate(offerSlider.offerImages?.length ?? 0, (index) {
                OfferImages offerImage = offerSlider.offerImages![index];
                return GestureDetector(
                  onTap: () => onTap(context, offerImage),
                  child: Container(
                    width: MediaQuery.of(context).size.width * 0.5 -
                        appContentHorizontalPadding * 2,
                    decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primaryContainer,
                        borderRadius: BorderRadius.circular(8)),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                            height: 100,
                            decoration: BoxDecoration(
                                borderRadius: const BorderRadius.only(
                                    topLeft: Radius.circular(8),
                                    topRight: Radius.circular(8)),
                                image: DecorationImage(
                                    image: NetworkImage(offerImage.image!),
                                    fit: BoxFit.cover))),
                        Row(
                          children: <Widget>[
                            Container(
                                decoration: BoxDecoration(
                                    color:
                                        Theme.of(context).colorScheme.primary,
                                    borderRadius: BorderRadius.circular(100)),
                                width: 5,
                                height: 40),
                            Expanded(
                              child: SizedBox(
                                height: 48,
                                child: Padding(
                                  padding:
                                      const EdgeInsets.symmetric(horizontal: 8),
                                  child: Align(
                                    alignment: Alignment.centerLeft,
                                    child: CustomTextContainer(
                                      textKey: offerImage.title!,
                                      maxLines: 2,
                                      overflow: TextOverflow.visible,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyLarge
                                          ?.copyWith(
                                              color: Theme.of(context)
                                                  .colorScheme
                                                  .secondary
                                                  .withValues(alpha: 0.67)),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              }));
        })
      ],
    );
  }
}
