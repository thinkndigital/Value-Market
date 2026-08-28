import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/ui/cart/cubits/getUserCart.dart';
import 'package:eshop_plus/ui/profile/promoCode/blocs/promoCodeCubit.dart';
import 'package:eshop_plus/ui/profile/promoCode/blocs/validatePromoCodeCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/ui/profile/promoCode/widgets/showPromocodeDialog.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customTextButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextFieldContainer.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart' as intl;

import '../../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../models/promoCode.dart';
import '../../../../commons/widgets/dottedLineRectPainter.dart';

class PromoCodeScreen extends StatefulWidget {
  final bool fromCartScreen;

  const PromoCodeScreen({Key? key, this.fromCartScreen = false})
      : super(key: key);
  static Widget getRouteInstance() => MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (context) => PromoCodeCubit(),
          ),
        ],
        child: PromoCodeScreen(
          fromCartScreen: Get.arguments?['fromCartScreen'] ?? false,
        ),
      );
  @override
  _PromoCodeScreenState createState() => _PromoCodeScreenState();
}

class _PromoCodeScreenState extends State<PromoCodeScreen> {
  final couponTextController = TextEditingController();
  @override
  void initState() {
    super.initState();
    getData();
  }

  getData() {
    Future.delayed(Duration.zero, () {
      context.read<PromoCodeCubit>().getpromoCodes(
          storeId: context.read<StoresCubit>().getDefaultStore().id!);
    });
  }

  @override
  void dispose() {
    couponTextController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
        appBar: const CustomAppbar(titleKey: couponsKey),
        body: Column(
          mainAxisAlignment: MainAxisAlignment.start,
          children: [
            if (widget.fromCartScreen)
              CustomDefaultContainer(
                  child: CustomTextFieldContainer(
                hintTextKey: enterCouponCodeKey,
                textEditingController: couponTextController,
                suffixWidget: Padding(
                    padding: const EdgeInsetsDirectional.only(end: 10, top: 15),
                    child: BlocProvider(
                      create: (context) => ValidatePromoCodeCubit(),
                      child: BlocConsumer<ValidatePromoCodeCubit,
                          ValidatePromoCodeState>(listener: (context, state) {
                        if (state is ValidatePromoCodeFetchSuccess) {
                          context
                              .read<GetUserCartCubit>()
                              .setPromoCode(context, state.promoCode);
                          Navigator.of(context).pop();
                          showDialog(
                            context: context,
                            builder: (context) =>
                                ShowPromocodeDialog(promoCode: state.promoCode),
                          );
                        }
                        if (state is ValidatePromoCodeFetchFailure) {
                          Utils.showSnackBar(
                              message: state.errorMessage, context: context);
                        }
                      }, builder: (context, state) {
                        return CustomTextButton(
                            buttonTextKey: applyKey,
                            textStyle: Theme.of(context)
                                .textTheme
                                .bodyMedium!
                                .copyWith(
                                    color:
                                        Theme.of(context).colorScheme.primary),
                            onTapButton: () {
                              if (couponTextController.text.trim().isEmpty) {
                                Utils.showSnackBar(
                                    message: pleaseEnterCouponCodeKey,
                                    context: context);
                                return;
                              }
                              if (state is! ValidatePromoCodeFetchInProgress) {
                                if (context.read<GetUserCartCubit>().state
                                    is GetUserCartFetchSuccess) {
                                  context
                                      .read<ValidatePromoCodeCubit>()
                                      .validatePromoCode(params: {
                                    ApiURL.finalTotalApiKey: context
                                        .read<GetUserCartCubit>()
                                        .getCartDetail()
                                        .subTotal,
                                    ApiURL.promoCodeApiKey:
                                        couponTextController.text.trim()
                                  });
                                } else {
                                  Utils.showSnackBar(
                                      message: emptyCartErrorMessageKey,
                                      context: context);
                                }
                              }
                            });
                      }),
                    )),
              )),
            BlocBuilder<PromoCodeCubit, PromoCodeState>(
              builder: (context, state) {
                if (state is PromoCodeFetchSuccess) {
                  return Flexible(
                    child: SingleChildScrollView(
                      padding: const EdgeInsetsDirectional.symmetric(
                          vertical: 12,
                          horizontal: appContentHorizontalPadding),
                      child: Wrap(
                          spacing: appContentHorizontalPadding,
                          runSpacing: appContentHorizontalPadding,
                          direction: Axis.horizontal,
                          children:
                              List.generate(state.promoCodes.length, (index) {
                            return PromocodeCard(
                                promocode: state.promoCodes[index],
                                fromCartScreen: widget.fromCartScreen);
                          })),
                    ),
                  );
                } else if (state is PromoCodeFetchFailure) {
                  return Expanded(
                    child: ErrorScreen(
                      image: state.errorMessage == noInternetKey
                          ? "no_internet"
                          : 'no_coupon',
                      onPressed: getData,
                      text: state.errorMessage,
                    ),
                  );
                }
                return Expanded(
                  child: CustomCircularProgressIndicator(
                    indicatorColor: Theme.of(context).colorScheme.primary,
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class PromocodeCard extends StatelessWidget {
  final PromoCode promocode;
  bool fromCartScreen;
  PromocodeCard({required this.promocode, this.fromCartScreen = false});

  @override
  Widget build(BuildContext context) {
    return CouponCard(
        height: fromCartScreen ? 300 : 245,
        width: (MediaQuery.of(context).size.width / 2) -
            (appContentHorizontalPadding * 1.5),
        curvePosition: fromCartScreen ? 180 : 180,
        curveRadius: 30,
        borderRadius: 10,
        border: BorderSide(
            width: 2,
            color: Theme.of(context)
                .inputDecorationTheme
                .iconColor! // Border color
            ),
        backgroundColor: Colors.white,
        firstChild: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.start,
            children: [
              CustomImageWidget(
                url: promocode.image ?? '',
                height: 60,
                width: double.infinity,
                borderRadius: 5,
                boxFit: BoxFit.fitHeight,
              ),
              SizedBox(height: 4),
              CustomTextContainer(
                  textKey: promocode.title ?? "",
                  maxLines: 1,
                  style: Theme.of(context).textTheme.titleMedium!.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .secondary
                          .withValues(alpha: 0.8))),
              SizedBox(height: 2),
              CustomTextContainer(
                  textKey: promocode.message ?? "",
                  maxLines: 2,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .secondary
                          .withValues(alpha: 0.8))),
              SizedBox(height: 8),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 5),
                child: Text.rich(
                  textDirection: Directionality.of(context),
                  overflow: TextOverflow.visible,
                  maxLines: 2,
                  TextSpan(
                    style: Theme.of(context).textTheme.labelSmall!.copyWith(
                        color: Theme.of(context)
                            .colorScheme
                            .secondary
                            .withValues(alpha: 0.67)),
                    children: [
                      TextSpan(
                        text: context
                            .read<SettingsAndLanguagesCubit>()
                            .getTranslatedValue(labelKey: expiresOnKey),
                      ),
                      const TextSpan(
                        text: ' : ',
                      ),
                      TextSpan(
                        text: formatDate(promocode.endDate.toString()),
                      ),
                    ],
                  ),
                  textAlign: TextAlign.center,
                ),
              ),
            ],
          ),
        ),
        secondChild: Container(
          padding: const EdgeInsets.symmetric(
              vertical: 8, horizontal: appContentHorizontalPadding),
          width: double.maxFinite,
          decoration: BoxDecoration(
            border: Border(
              top: BorderSide(
                  color: Theme.of(context).inputDecorationTheme.iconColor!),
            ),
          ),
          child: Column(
            children: [
              if (promocode.promoCode != null)
                GestureDetector(
                  onTap: () => Clipboard.setData(
                    ClipboardData(
                      text: promocode.promoCode!,
                    ),
                  ),
                  child: CustomPaint(
                      painter: DottedLineRectPainter(
                        strokeWidth: 1.0,
                        radius: 3.0,
                        dashWidth: 4.0,
                        dashSpace: 2.0,
                        color: Theme.of(context)
                            .colorScheme
                            .secondary
                            .withValues(alpha: 0.8),
                      ),
                      child: Padding(
                        padding: const EdgeInsetsDirectional.symmetric(
                            horizontal: appContentHorizontalPadding,
                            vertical: 4),
                        child: CustomTextContainer(
                          textKey: promocode.promoCode ?? '',
                          maxLines: 1,
                          overflow: TextOverflow.fade,
                          style: Theme.of(context)
                              .textTheme
                              .titleMedium!
                              .copyWith(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .secondary
                                      .withValues(alpha: 0.8)),
                        ),
                      )),
                ),
              fromCartScreen
                  ? BlocProvider(
                      create: (context) => ValidatePromoCodeCubit(),
                      child: BlocConsumer<ValidatePromoCodeCubit,
                          ValidatePromoCodeState>(listener: (context, state) {
                        if (state is ValidatePromoCodeFetchSuccess) {
                          FocusScope.of(context).unfocus();
                          context
                              .read<GetUserCartCubit>()
                              .setPromoCode(context, state.promoCode);

                          Navigator.of(context).pop();
                          showDialog(
                            context: context,
                            builder: (context) =>
                                ShowPromocodeDialog(promoCode: state.promoCode),
                          );
                        }
                        if (state is ValidatePromoCodeFetchFailure) {
                          FocusScope.of(context).unfocus();
                          Utils.showSnackBar(
                              message: state.errorMessage, context: context);
                        }
                      }, builder: (context, state) {
                        return Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: CustomRoundedButton(
                              widthPercentage: 0.5,
                              buttonTitle: redeemKey,
                              showBorder: false,
                              backgroundColor: context
                                              .read<GetUserCartCubit>()
                                              .getCartDetail()
                                              .promoCode !=
                                          null &&
                                      context
                                              .read<GetUserCartCubit>()
                                              .getCartDetail()
                                              .promoCode!
                                              .id ==
                                          promocode.id
                                  ? Colors.grey
                                  : Theme.of(context).colorScheme.primary,
                              child: state is ValidatePromoCodeFetchInProgress
                                  ? const CustomCircularProgressIndicator()
                                  : null,
                              onTap: () {
                                if (context
                                            .read<GetUserCartCubit>()
                                            .getCartDetail()
                                            .promoCode !=
                                        null &&
                                    context
                                            .read<GetUserCartCubit>()
                                            .getCartDetail()
                                            .promoCode!
                                            .id ==
                                        promocode.id) return;
                                if (state
                                    is! ValidatePromoCodeFetchInProgress) {
                                  if (context.read<GetUserCartCubit>().state
                                      is GetUserCartFetchSuccess) {
                                    context
                                        .read<ValidatePromoCodeCubit>()
                                        .validatePromoCode(params: {
                                      ApiURL.finalTotalApiKey: context
                                          .read<GetUserCartCubit>()
                                          .getCartDetail()
                                          .subTotal,
                                      ApiURL.promoCodeApiKey:
                                          promocode.promoCode
                                    });
                                  } else {
                                    Utils.showSnackBar(
                                        message: emptyCartErrorMessageKey,
                                        context: context);
                                  }
                                }
                              }),
                        );
                      }),
                    )
                  : SizedBox.shrink(),
            ],
          ),
        ));
  }

  String formatDate(String dateString) {
    // Define the input format
    intl.DateFormat inputFormat = intl.DateFormat('yyyy-MM-dd');
    // Parse the date string into a DateTime object
    DateTime date = inputFormat.parse(dateString);
    // Get day and add suffix
    String day = date.day.toString();
    String dayWithSuffix = day + Utils.getDayOfMonthSuffix(date.day);

    // Format month
    String month = intl.DateFormat.MMMM().format(date);

    // Combine day and month
    return '$dayWithSuffix $month';
  }
}

/// Provides a coupon card widget
class CouponCard extends StatelessWidget {
  /// Creates a vertical coupon card widget that takes two children
  /// with the properties that defines the shape of the card.
  const CouponCard({
    Key? key,
    required this.firstChild,
    required this.secondChild,
    this.width,
    this.height = 150,
    this.borderRadius = 8,
    this.curveRadius = 20,
    this.curvePosition = 100,
    this.curveAxis = Axis.horizontal,
    this.clockwise = false,
    this.backgroundColor,
    this.decoration,
    this.shadow,
    this.border,
  }) : super(key: key);

  /// The small child or first.
  final Widget firstChild;

  /// The big child or second.
  final Widget secondChild;

  final double? width;

  final double height;

  /// Border radius value.
  final double borderRadius;

  /// The curve radius value, which specifies its size.
  final double curveRadius;

  /// The curve position, which specifies the curve position depending
  /// on the its child's size.
  final double curvePosition;

  /// The direction of the curve, whether it's set vertically or
  /// horizontally.
  final Axis curveAxis;

  /// If `false` (by default), then the border radius will be drawn
  /// inside the child, otherwise outside.
  final bool clockwise;

  /// The background color value.
  ///
  /// Ignored if `decoration` property is used.
  final Color? backgroundColor;

  /// The decoration of the entire widget
  ///
  /// Note: `boxShadow` property in the `BoxDecoration` won't do an effect,
  /// and you should use the `shadow` property of `CouponCard` itself instead.
  final Decoration? decoration;

  final Shadow? shadow;

  final BorderSide? border;

  @override
  Widget build(BuildContext context) {
    final List<Widget> children = [
      if (curveAxis == Axis.horizontal)
        SizedBox(
          width: double.maxFinite,
          height: curvePosition + (curveRadius / 2),
          child: firstChild,
        )
      else
        SizedBox(
          width: curvePosition + (curveRadius / 2),
          height: double.maxFinite,
          child: firstChild,
        ),
      Expanded(child: secondChild),
    ];

    final clipper = CouponClipper(
      borderRadius: borderRadius,
      curveRadius: curveRadius,
      curvePosition: curvePosition,
      curveAxis: curveAxis,
      direction: Directionality.of(context),
      clockwise: clockwise,
    );

    return CustomPaint(
      painter: CouponDecorationPainter(
        shadow: shadow,
        border: border,
        clipper: clipper,
      ),
      child: ClipPath(
        clipper: clipper,
        child: Container(
          width: width,
          height: height,
          decoration: decoration ?? BoxDecoration(color: backgroundColor),
          child: curveAxis == Axis.horizontal
              ? Column(children: children)
              : Row(children: children),
        ),
      ),
    );
  }
}

/// Clips a widget to the form of a coupon card shape
class CouponClipper extends CustomClipper<Path> {
  const CouponClipper({
    this.borderRadius = 8,
    this.curveRadius = 20,
    this.curvePosition = 100,
    this.curveAxis = Axis.horizontal,
    this.direction = TextDirection.ltr,
    this.clockwise = false,
  }) : assert(
          curvePosition > borderRadius,
          "'curvePosition' should be greater than the 'borderRadius'",
        );

  /// Border radius value.
  final double borderRadius;

  /// The curve radius value, which specifies its size.
  final double curveRadius;

  /// The curve position, which specifies the curve position depending
  /// on the its child's size.
  final double curvePosition;

  /// The direction of the curve, whether it's set vertically or
  /// horizontally.
  final Axis curveAxis;

  /// If `curveAxis` set to `Axis.vertical`, then direction is useful
  /// for languages like (Ar, Fa, ...).
  final TextDirection direction;

  /// If `false` (by default), then the border radius will be drawn
  /// inside the child, otherwise outside.
  final bool clockwise;

  @override
  Path getClip(Size size) {
    double directionalCurvePosition = curvePosition;

    if (curveAxis == Axis.vertical && direction == TextDirection.rtl) {
      directionalCurvePosition = size.width - curvePosition - curveRadius;
    }

    final Radius arcRadius = Radius.circular(borderRadius);

    // border radius arc points
    final Offset bottomLeftArc = Offset(borderRadius, size.height);
    final Offset bottomRightArc =
        Offset(size.width, size.height - borderRadius);
    final Offset topRightArc = Offset(size.width - borderRadius, 0);
    final Offset topLeftArc = Offset(0, borderRadius);

    final Path path = Path();

    // starting point
    path.moveTo(0, borderRadius - 2);

    // left curve
    if (curveAxis == Axis.horizontal) {
      path.lineTo(0, directionalCurvePosition);
      path.quadraticBezierTo(
        curveRadius / 1.5,
        directionalCurvePosition + (curveRadius / 2),
        0,
        directionalCurvePosition + curveRadius,
      );
    }

    // left line, bottom left arc
    path.lineTo(0, size.height - borderRadius);
    path.arcToPoint(bottomLeftArc, radius: arcRadius, clockwise: clockwise);

    // bottom cuve
    if (curveAxis == Axis.vertical) {
      path.lineTo(directionalCurvePosition, size.height);

      path.quadraticBezierTo(
        directionalCurvePosition + (curveRadius / 2),
        size.height - (curveRadius / 1.5),
        directionalCurvePosition + curveRadius,
        size.height,
      );
    }

    // bottom line, bottom right arc
    path.lineTo(size.width - borderRadius, size.height);
    path.arcToPoint(bottomRightArc, radius: arcRadius, clockwise: clockwise);

    // right curve
    if (curveAxis == Axis.horizontal) {
      path.lineTo(size.width, directionalCurvePosition + curveRadius);
      path.quadraticBezierTo(
        size.width - (curveRadius / 1.5),
        directionalCurvePosition + (curveRadius / 2),
        size.width,
        directionalCurvePosition,
      );
    }

    // right line, top right arc
    path.lineTo(size.width, borderRadius);
    path.arcToPoint(topRightArc, radius: arcRadius, clockwise: clockwise);

    // top curve
    if (curveAxis == Axis.vertical) {
      path.lineTo(directionalCurvePosition + curveRadius, 0);
      path.quadraticBezierTo(
        (directionalCurvePosition - (curveRadius / 2)) + curveRadius,
        curveRadius / 1.5,
        directionalCurvePosition - curveRadius + curveRadius,
        0,
      );
    }

    // top line, top left arc
    path.lineTo(borderRadius, 0);
    path.arcToPoint(topLeftArc, radius: arcRadius, clockwise: clockwise);

    return path;
  }

  @override
  bool shouldReclip(CustomClipper<Path> oldClipper) => oldClipper != this;
}

class CouponDecorationPainter extends CustomPainter {
  final Shadow? shadow;
  final BorderSide? border;
  final CustomClipper<Path> clipper;

  CouponDecorationPainter({
    this.shadow,
    this.border,
    required this.clipper,
  });

  @override
  void paint(Canvas canvas, Size size) {
    // Draw shadow if provided
    if (shadow != null) {
      final shadowPaint = Paint()
        ..color = shadow!.color
        ..maskFilter = MaskFilter.blur(BlurStyle.normal, shadow!.blurRadius);
      final shadowPath = clipper.getClip(size);
      canvas.drawPath(shadowPath, shadowPaint);
    }

    // Draw border if provided
    if (border != null) {
      final borderPaint = Paint()
        ..color = border!.color
        ..style = PaintingStyle.stroke
        ..strokeWidth = border!.width;
      final borderPath = clipper.getClip(size);
      canvas.drawPath(borderPath, borderPaint);
    }

    // Draw dashed line
    final dashPaint = Paint()
      ..color = Colors.grey // Color of the dashed line
      ..strokeWidth = 1.5
      ..style = PaintingStyle.stroke;

    const dashWidth = 5.0;
    const dashSpace = 3.0;
    double startX = 0.0;

    while (startX < size.width) {
      canvas.drawLine(
        Offset(startX, size.height / 2), // Horizontal line (centered)
        Offset(startX + dashWidth, size.height / 2),
        dashPaint,
      );
      startX += dashWidth + dashSpace;
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) {
    return true;
  }
}
