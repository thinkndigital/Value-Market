import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';

import '../../utils/utils.dart';

class CustomTabbar extends StatefulWidget {
  final int currentPage;
  final List tabTitles;
  final Function onTapTitle;
  final TextStyle? textStyle;
  final double? padding;
  const CustomTabbar(
      {super.key,
      required this.currentPage,
      required this.tabTitles,
      required this.onTapTitle,
      this.padding,
      this.textStyle});

  @override
  State<CustomTabbar> createState() => _CustomTabbarState();
}

class _CustomTabbarState extends State<CustomTabbar> {
  @override
  Widget build(BuildContext context) {
    return Container(
      height: 45,
      width: double.maxFinite,
      alignment: Alignment.center,
      color: Theme.of(context).colorScheme.primaryContainer,
      child: ListView(
          scrollDirection: Axis.horizontal,
          shrinkWrap: true,
          children: List.generate(
            widget.tabTitles.length,
            (index) => InkWell(
                onTap: () {
                  if (widget.currentPage != index) {
                    widget.onTapTitle(index);
                  }
                },
                child: Row(
                  children: [
                    Stack(
                      alignment: AlignmentDirectional.center,
                      children: [
                        Container(
                            alignment: Alignment.center,
                            margin: EdgeInsetsDirectional.symmetric(
                                horizontal: widget.padding ??
                                    appContentHorizontalPadding),
                            child: CustomTextContainer(
                                style: widget.textStyle,
                                textKey: widget.tabTitles[index])),
                        Positioned.directional(
                          textDirection: Directionality.of(context),
                          bottom: 0,
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 500),
                            curve: Curves.fastOutSlowIn,
                            height: 2,
                            width: widget.currentPage == index
                                ? Utils.getTextWidthSize(
                                        text: widget.tabTitles[index],
                                        textStyle: Theme.of(context)
                                            .textTheme
                                            .bodyLarge!,
                                        context: context)
                                    .width
                                : 0,
                            decoration: BoxDecoration(
                                color: Theme.of(context).colorScheme.primary,
                                borderRadius: BorderRadius.circular(50)),
                          ),
                        )
                      ],
                    ),
                    if (index < widget.tabTitles.length - 1)
                      Container(
                        height: 15,
                        width: 1,
                        margin: const EdgeInsetsDirectional.symmetric(
                            horizontal: appContentHorizontalPadding),
                        color: Theme.of(context).inputDecorationTheme.iconColor,
                      )
                  ],
                )),
          )),
    );
  }
}
