import 'dart:async';

import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/home/slider/sliderCubit.dart';
import 'package:value_market_customer/ui/categoty/models/category.dart';
import 'package:value_market_customer/ui/home/slider/slider.dart';
import 'package:value_market_customer/ui/explore/screens/exploreScreen.dart';
import 'package:value_market_customer/ui/explore/productDetails/productDetailsScreen.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../utils/utils.dart';

class SliderSection extends StatefulWidget {
  const SliderSection({Key? key}) : super(key: key);

  @override
  _SliderSectionState createState() => _SliderSectionState();
}

class _SliderSectionState extends State<SliderSection> {
  int _sliderIndex = 0;
  int slidersLength = 0;
  late Timer _timer;
  final PageController _pageController =
      PageController(initialPage: 0, viewportFraction: 0.9);
  late Size size;
  @override
  void initState() {
    super.initState();
    _sliderIndex = 0;
    _timer = Timer.periodic(const Duration(seconds: 8), (Timer timer) {
      if (_sliderIndex < slidersLength - 1) {
        _sliderIndex++;
      } else {
        _sliderIndex = 0;
      }
      if (_pageController.hasClients) {
        _pageController.animateToPage(
          _sliderIndex,
          duration: const Duration(seconds: 4),
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
    size = MediaQuery.of(context).size;
    return BlocConsumer<SliderCubit, SliderState>(
      listener: (context, state) {
        if (state is SliderFetchSuccess) {
          if (state.sliders.isNotEmpty) {
            slidersLength = state.sliders.length;
          }
        }
      },
      builder: (context, state) {
        if (state is SliderFetchSuccess) {
          return Container(
            color: Theme.of(context).colorScheme.primaryContainer,
            padding: const EdgeInsetsDirectional.symmetric(
                vertical: appContentHorizontalPadding),
            margin: const EdgeInsetsDirectional.only(
                bottom: appContentHorizontalPadding / 2),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  height: MediaQuery.of(context).size.height * 0.22,
                  child: PageView.builder(
                      controller: _pageController,
                      itemCount: state.sliders.length,
                      onPageChanged: (index) {
                        setState(() {
                          _sliderIndex = index;
                        });
                      },
                      itemBuilder: (context, index) => buildSlider(
                            state.sliders[index],
                          )),
                ),
                DesignConfig.smallHeightSizedBox,
                Pageindicator(index: _sliderIndex, length: state.sliders.length)
              ],
            ),
          );
        }

        return const Center(child: SizedBox.shrink());
      },
    );
  }

  buildSlider(Sliders slider) {
    return GestureDetector(
      onTap: () {
        if (slider.type == 'slider_url' && slider.link!.isNotEmpty) {
          Utils.launchURL(slider.link.toString());
        }
        if (slider.type == 'products') {
          Utils.navigateToScreen(context, Routes.productDetailsScreen,
              arguments: ProductDetailsScreen.buildArguments(
                product: slider.itemList![0],
              ));
        }
        if (slider.type == 'combo_products') {
          Utils.navigateToScreen(context, Routes.productDetailsScreen,
              arguments: ProductDetailsScreen.buildArguments(
                  product: slider.itemList![0], isComboProduct: true));
        }
        if (slider.type == 'categories') {
          Category category = slider.itemList![0];
          if (category.children!.isEmpty) {
            Utils.navigateToScreen(context, Routes.exploreScreen,
                arguments: ExploreScreen.buildArguments(category: category));
          } else {
            Utils.navigateToScreen(context, Routes.subCategoryScreen,
                arguments: {
                  'category': category,
                });
          }
        }
      },
      child: Container(
          width: size.width * 0.95,
          margin: const EdgeInsetsDirectional.symmetric(horizontal: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: Colors.transparent,
            ),
          ),
          child: CustomImageWidget(
            url: slider.image.toString(),
            borderRadius: 8,
          )),
    );
  }
}

class Pageindicator extends StatelessWidget {
  final int length;
  final int index;
  const Pageindicator({Key? key, required this.index, required this.length})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
            margin: const EdgeInsetsDirectional.only(end: 8),
            width: 8,
            height: 8,
            decoration: BoxDecoration(
                color: index == 0
                    ? Theme.of(context).colorScheme.primary
                    : Theme.of(context)
                        .colorScheme
                        .primary
                        .withValues(alpha: 0.46),
                shape: BoxShape.circle)),
        AnimatedContainer(
          duration: const Duration(seconds: 2),
          padding:
              const EdgeInsetsDirectional.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primary,
            borderRadius: BorderRadius.circular(100),
          ),
          child: CustomTextContainer(
            textKey: '${index + 1} / $length',
            style: Theme.of(context)
                .textTheme
                .bodySmall!
                .copyWith(color: Theme.of(context).colorScheme.onPrimary),
          ),
        ),
        Container(
            margin: const EdgeInsetsDirectional.only(start: 8),
            width: 8,
            height: 8,
            decoration: BoxDecoration(
                color: index == length - 1
                    ? Theme.of(context).colorScheme.primary
                    : Theme.of(context)
                        .colorScheme
                        .primary
                        .withValues(alpha: 0.46),
                shape: BoxShape.circle)),
      ],
    );
  }
}
