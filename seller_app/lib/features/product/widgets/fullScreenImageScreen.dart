import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/features/product/models/productRating.dart';
import 'package:value_market_seller/features/product/widgets/ratingContainer.dart';
import 'package:value_market_seller/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_seller/commons/widgets/customImageWidget.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';

import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

class FullScreenImage extends StatefulWidget {
  final String imageUrl;
  final List<RatingData>? reviews;
  final int index;
  final List<String> imageUrls;
  final String? title;

  FullScreenImage({
    required this.imageUrl,
    this.reviews,
    required this.index,
    required this.imageUrls,
    this.title = '',
  });
  static Widget getRouteInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return FullScreenImage(
      imageUrl: arguments['imageUrl'] as String,
      reviews: arguments['reviews'] as List<RatingData>?,
      index: arguments['index'] as int,
      imageUrls: arguments['imageUrls'] as List<String>,
      title: arguments['title'] as String?,
    );
  }

  @override
  State<FullScreenImage> createState() => _FullScreenImageState();
}

class _FullScreenImageState extends State<FullScreenImage> {
  late int _selectedIndex;
  late PageController _pageController;

  @override
  void initState() {
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.light);
    super.initState();
    _selectedIndex = widget.index;
    _pageController = PageController(initialPage: _selectedIndex);
  }

  @override
  dispose() {
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.dark);
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).colorScheme.secondary,
      appBar: AppBar(
          backgroundColor: Colors.transparent,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded),
            padding: const EdgeInsetsDirectional.all(0),
            color: Theme.of(context).colorScheme.onSecondary,
            onPressed: () {
              Utils.popNavigation(context);
            },
          ),
          titleSpacing: 0,
          title: CustomTextContainer(
              textKey: widget.title ?? '',
              style: Theme.of(context)
                  .textTheme
                  .titleLarge!
                  .copyWith(color: Theme.of(context).colorScheme.onSecondary)),
          actions: [
            Padding(
              padding: const EdgeInsets.all(8.0),
              child: Center(
                child: CustomTextContainer(
                    textKey:
                        '(${_selectedIndex + 1}/${widget.imageUrls.length})',
                    style: Theme.of(context).textTheme.bodySmall!.copyWith(
                        color: Theme.of(context).colorScheme.onSecondary)),
              ),
            ),
          ]),
      body: Column(
        children: [
          Expanded(
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                PageView.builder(
                  clipBehavior: Clip.none,
                  controller: _pageController,
                  onPageChanged: (index) {
                    setState(() {
                      _selectedIndex = index;
                    });
                  },
                  itemCount: widget.imageUrls.length,
                  itemBuilder: (context, index) {
                    return Hero(
                      tag: 'image$_selectedIndex',
                      child: CustomImageWidget(
                        url: widget.imageUrls[index],
                        borderRadius: 2,
                        boxFit: BoxFit.fitWidth,
                      ),
                    );
                  },
                ),
                if (_selectedIndex != 0)
                  Positioned(
                    left: 8,
                    top: MediaQuery.of(context).size.height / 3,
                    child: GestureDetector(
                      onTap: () {
                        setState(() {
                          _selectedIndex--;
                        });
                      },
                      child: Container(
                        height: 44,
                        width: 44,
                        decoration: BoxDecoration(
                            color: Theme.of(context)
                                .colorScheme
                                .onSecondary
                                .withValues(alpha: 0.22),
                            borderRadius: BorderRadius.circular(borderRadius)),
                        child: Icon(
                          Icons.arrow_back_ios_new,
                          color: Theme.of(context).colorScheme.onSecondary,
                          size: 24,
                        ),
                      ),
                    ),
                  ),
                if (_selectedIndex != widget.imageUrls.length - 1)
                  Positioned(
                    right: 8,
                    top: MediaQuery.of(context).size.height / 3,
                    child: GestureDetector(
                      onTap: () => setState(() {
                        _selectedIndex++;
                      }),
                      child: Container(
                        height: 44,
                        width: 44,
                        decoration: BoxDecoration(
                            color: Theme.of(context)
                                .colorScheme
                                .onSecondary
                                .withValues(alpha: 0.22),
                            borderRadius: BorderRadius.circular(borderRadius)),
                        child: Icon(
                          Icons.arrow_forward_ios,
                          color: Theme.of(context).colorScheme.onSecondary,
                          size: 24,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          if (widget.reviews != null) ...[
            const SizedBox(
              height: 4,
            ),
            CustomDefaultContainer(
              child: ReviewWidget(
                review: widget.reviews![_selectedIndex],
                showImages: false,
              ),
            )
          ]
        ],
      ),
    );
  }
}
