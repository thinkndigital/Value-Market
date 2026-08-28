import 'dart:math' as math;
import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshop_plus/ui/profile/faq/blocs/faqCubit.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../commons/blocs/storesCubit.dart';
import '../models/faq.dart';
import '../../../../commons/widgets/customTextButton.dart';
import '../../../../commons/widgets/customTextContainer.dart';

class FaqScreen extends StatefulWidget {
  const FaqScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => FAQCubit(),
        child: const FaqScreen(),
      );
  @override
  _FaqScreenState createState() => _FaqScreenState();
}

class _FaqScreenState extends State<FaqScreen> {
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      getFaqs();
    });
  }

  getFaqs() {
    context.read<FAQCubit>().getFAQ(params: {
      ApiURL.storeIdApiKey: context.read<StoresCubit>().getDefaultStore().id!,
    }, api: ApiURL.getFaqs);
  }

  void loadMoreFaqs() {
    context.read<FAQCubit>().loadMore(params: {
      ApiURL.storeIdApiKey: context.read<StoresCubit>().getDefaultStore().id!,
    }, api: ApiURL.getFaqs);
  }

  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
        appBar: const CustomAppbar(titleKey: faqKey),
        backgroundColor: Theme.of(context).colorScheme.primaryContainer,
        body: BlocBuilder<FAQCubit, FAQState>(
          builder: (context, state) {
            if (state is FAQFetchSuccess) {
              return NotificationListener<ScrollUpdateNotification>(
                onNotification: (notification) {
                  if (notification.metrics.pixels >=
                      notification.metrics.maxScrollExtent) {
                    if (context.read<FAQCubit>().hasMore()) {
                      loadMoreFaqs();
                    }
                  }
                  return true;
                },
                child: ListView.separated(
                  separatorBuilder: (context, index) =>
                      DesignConfig.defaultHeightSizedBox,
                  padding: const EdgeInsetsDirectional.all(
                      appContentHorizontalPadding),
                  itemCount: state.faqs.length,
                  itemBuilder: (context, index) {
                    if (context.read<FAQCubit>().hasMore()) {
                      if (index == state.faqs.length - 1) {
                        if (context.read<FAQCubit>().fetchMoreError()) {
                          return Center(
                            child: CustomTextButton(
                                buttonTextKey: retryKey,
                                onTapButton: () {
                                  loadMoreFaqs();
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
                    return AnimatedContainer(faq: state.faqs[index]);
                  },
                ),
              );
            }
            if (state is FAQFetchFailure) {
              return ErrorScreen(
                  onPressed: getFaqs,
                  text: state.errorMessage,
                  child: state is FAQFetchInProgress
                      ? CustomCircularProgressIndicator(
                          indicatorColor: Theme.of(context).colorScheme.primary,
                        )
                      : null);
            }
            return Center(
              child: CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.primary),
            );
          },
        ),
      ),
    );
  }
}

class AnimatedContainer extends StatefulWidget {
  final FAQ faq;

  AnimatedContainer({required this.faq});

  @override
  _AnimatedContainerState createState() => _AnimatedContainerState();
}

class _AnimatedContainerState extends State<AnimatedContainer>
    with SingleTickerProviderStateMixin {
  bool _isExpand = false;
  late AnimationController _expandController;
  late Animation<double> _animation;
  int? maxLines = 2;
  late final Animation<double> _roatationAnimation;
  late final Animation<double> _fadeAnimation;
  @override
  void initState() {
    super.initState();
    prepareAnimations();
    setRotation(90);
  }

  ///Setting up the animation
  void prepareAnimations() {
    _expandController =
        AnimationController(vsync: this, duration: const Duration(seconds: 1))
          ..addListener(() {
            setState(() {});
          });
    _fadeAnimation = Tween(begin: 1.0, end: 2.0).animate(CurvedAnimation(
        parent: _expandController,
        curve: const Interval(
          0.4,
          0.7,
          curve: Curves.fastOutSlowIn,
        )));
    _animation = CurvedAnimation(
      parent: _expandController,
      curve: const Interval(
        0.0,
        0.4,
        curve: Curves.fastOutSlowIn,
      ),
    );
  }

  setRotation(int degrees) {
    final angle = degrees * math.pi / 90;
    _roatationAnimation = Tween(begin: 0.0, end: angle).animate(CurvedAnimation(
        parent: _expandController,
        curve: const Interval(
          0,
          0.5,
          curve: Curves.easeInOut,
        )));
  }

  @override
  void dispose() {
    _expandController.dispose();

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        _isExpand = !_isExpand;

        if (_isExpand) {
          _expandController.forward();
        } else {
          _expandController.reverse();
        }
      },
      child: Container(
        padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(borderRadius),
          border: Border.all(
            color: Theme.of(context).inputDecorationTheme.iconColor!,
          ),
        ),
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Flexible(
                  child: FadeTransition(
                    opacity: _fadeAnimation,
                    child: CustomTextContainer(
                        textKey: widget.faq.question,
                        maxLines: _fadeAnimation.value == 1.0 ? 2 : null,
                        overflow: _fadeAnimation.value == 1.0
                            ? TextOverflow.ellipsis
                            : TextOverflow.visible,
                        style: Theme.of(context).textTheme.titleSmall!),
                  ),
                ),
                AnimatedBuilder(
                    animation: _roatationAnimation,
                    child: const Icon(Icons.keyboard_arrow_down, size: 24),
                    builder: (context, child) {
                      return Transform.rotate(
                          angle: _roatationAnimation.value, child: child);
                    }),
              ],
            ),
            SizeTransition(
              axisAlignment: 1.0,
              sizeFactor: _animation,
              child: Padding(
                padding: const EdgeInsetsDirectional.only(top: 8),
                child: CustomTextContainer(
                    textKey: widget.faq.answer,
                    style: Theme.of(context).textTheme.bodyMedium!),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
