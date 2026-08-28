import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/dottedLineRectPainter.dart';
import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshop_plus/core/configs/appConfig.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:share_plus/share_plus.dart';

import '../../utils/utils.dart';
import '../../commons/widgets/customTextContainer.dart';

class ReferAndEarnScreen extends StatelessWidget {
  static Widget getRouteInstance() => const ReferAndEarnScreen();

  const ReferAndEarnScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
        appBar: const CustomAppbar(titleKey: referAndEarnKey),
        backgroundColor: Theme.of(context).colorScheme.primaryContainer,
        body: context.read<UserDetailsCubit>().getReferalCode().isEmpty
            ? const Center(
                child: CustomTextContainer(
                  textKey: noeferAndEarnKey,
                  maxLines: 3,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.black,
                  ),
                ),
              )
            : SingleChildScrollView(
                padding: const EdgeInsetsDirectional.all(
                    appContentHorizontalPadding),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    FittedBox(
                      child: Utils.setSvgImage('refer&earn'),
                    ),
                    CustomTextContainer(
                        textKey: referAndEarnKey,
                        style: Theme.of(context)
                            .textTheme
                            .headlineMedium!
                            .copyWith(
                                color: Theme.of(context).colorScheme.primary)),
                    Padding(
                      padding: const EdgeInsetsDirectional.all(
                          appContentHorizontalPadding),
                      child: CustomTextContainer(
                          textKey: referAndEarnDescKey,
                          maxLines: 3,
                          textAlign: TextAlign.center,
                          style:
                              Theme.of(context).textTheme.bodyMedium!.copyWith(
                                    color: Colors.black,
                                  )),
                    ),
                    CustomTextContainer(
                        textKey: yourReferralCodeKey,
                        style: Theme.of(context)
                            .textTheme
                            .bodyMedium!
                            .copyWith(color: Colors.black)),
                    DesignConfig.smallHeightSizedBox,
                    GestureDetector(
                      onTap: () => Clipboard.setData(
                        ClipboardData(
                          text:
                              context.read<UserDetailsCubit>().getReferalCode(),
                        ),
                      ),
                      child: CustomPaint(
                        painter: DottedLineRectPainter(
                          strokeWidth: 1.0,
                          radius: 3.0,
                          dashWidth: 4.0,
                          dashSpace: 2.0,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                        child: Container(
                          padding: const EdgeInsetsDirectional.symmetric(
                              horizontal: appContentHorizontalPadding,
                              vertical: appContentHorizontalPadding / 2),
                          decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(3),
                              color: Theme.of(context)
                                  .colorScheme
                                  .primary
                                  .withValues(alpha: 0.10)),
                          child: CustomTextContainer(
                            textKey: context
                                .read<UserDetailsCubit>()
                                .getReferalCode(),
                            style: Theme.of(context)
                                .textTheme
                                .labelLarge!
                                .copyWith(
                                    color:
                                        Theme.of(context).colorScheme.primary),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(
                      height: appContentVerticalSpace * 2,
                    ),
                    CustomRoundedButton(
                      widthPercentage: 1.0,
                      buttonTitle: shareKey,
                      showBorder: false,
                      onTap: () {
                        var str =
                            "$appName\nRefer Code:${context.read<UserDetailsCubit>().getReferalCode()}\nDownload $appName App from below link : \n${Utils.getStoreUrl(context)}";
                        SharePlus.instance.share(ShareParams(text: str));
                      },
                    )
                  ],
                ),
              ),
      ),
    );
  }
}
