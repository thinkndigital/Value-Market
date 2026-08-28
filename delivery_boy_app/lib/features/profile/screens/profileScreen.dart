import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/features/auth/blocs/authCubit.dart';
import 'package:eshoppro_deliveryboy/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshoppro_deliveryboy/commons/blocs/userDetailsCubit.dart';
import 'package:eshoppro_deliveryboy/commons/models/language.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customAppbar.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customLabelContainer.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customTextContainer.dart';
import 'package:eshoppro_deliveryboy/utils/designConfig.dart';
import 'package:eshoppro_deliveryboy/core/localization/labelKeys.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/svg.dart';
import 'package:get/get.dart';
import 'package:in_app_review/in_app_review.dart';

import '../../../core/routes/routes.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customRoundedButton.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({Key? key}) : super(key: key);

  @override
  _ProfileScreenState createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late Size size;
  late Color textColor;
  final _inAppReview = InAppReview.instance;
  Language? _selectedLanguage;
  bool _languageInProgress = false;
  @override
  void initState() {
    super.initState();
    _selectedLanguage =
        (context.read<SettingsAndLanguagesCubit>().getCurrentAppLanguage());
  }

  @override
  Widget build(BuildContext context) {
    size = MediaQuery.of(context).size;
    textColor = Theme.of(context).colorScheme.secondary.withValues(alpha: 0.9);
    return Scaffold(
      appBar: const CustomAppbar(
        titleKey: myProfileKey,
        showBackButton: false,
      ),
      body: buildBody(),
    );
  }

  buildBody() {
    return BlocBuilder<SettingsAndLanguagesCubit, SettingsAndLanguagesState>(
      builder: (context, state) {
        return Stack(
          clipBehavior: Clip.hardEdge,
          children: [
            Column(
              children: [
                buildProfileContainer(),
                Expanded(
                  child: ListView(
                    children: [
                      buildListContainer(
                        [
                          buildLabel(settingsKey),
                          if (context
                                  .read<SettingsAndLanguagesCubit>()
                                  .getLanguages()
                                  .length >
                              1)
                            buildListTile(Icons.translate, changeLanguageKey,
                                openLanguageBottomSheet),
                          buildListTile(
                              Icons.settings_outlined,
                              settingsKey,
                              () => Utils.navigateToScreen(
                                  context, Routes.settingScreen)),
                        ],
                      ),
                      buildListContainer(
                        [
                          buildLabel(supportAndInfoKey),
                          buildListTile(
                              Icons.perm_phone_msg_outlined,
                              contactUsKey,
                              () => navigatoToPolicyScreen(contactUsKey)),
                          buildListTile(Icons.info_outline, aboutUsKey,
                              () => navigatoToPolicyScreen(aboutUsKey)),
                          buildListTile(
                              Icons.policy_outlined,
                              termsAndPolicyKey,
                              () => Utils.navigateToScreen(
                                  context, Routes.termsAndPolicyScreen)),
                        ],
                      ),
                      buildListContainer(
                        [
                          buildLabel(moreKey),
                          buildListTile(
                              Icons.star_outline, rateUsKey, openRateUsDialog),
                          buildListTile(
                              Icons.logout, logoutKey, openLogoutDialog),
                        ],
                      )
                    ],
                  ),
                ),
              ],
            ),
            if (_languageInProgress)
              Container(
                color: Colors.black.withValues(alpha: 0.4),
                child: CustomCircularProgressIndicator(
                    indicatorColor: Theme.of(context).colorScheme.primary),
              ),
          ],
        );
      },
    );
  }

  Widget buildProfileContainer() {
    return BlocBuilder<UserDetailsCubit, UserDetailsState>(
        builder: (context, state) {
      if (state is UserDetailsFetchSuccess) {
        return Container(
          height: size.height * 0.13,
          padding: const EdgeInsetsDirectional.symmetric(
              horizontal: appContentHorizontalPadding),
          color: Theme.of(context).colorScheme.primary,
          child: Stack(
            children: [
              Row(
                children: <Widget>[
                  Utils.buildProfilePicture(
                      context, 70, state.userDetails.image ?? "",
                      selectedFile: null,
                      assetImage: false,
                      outerBorderColor: Colors.transparent),
                  DesignConfig.defaultWidthSizedBox,
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        CustomTextContainer(
                          textKey: state.userDetails.username ?? '',
                          style: Theme.of(context)
                              .textTheme
                              .titleMedium!
                              .copyWith(
                                  color:
                                      Theme.of(context).colorScheme.onPrimary),
                        ),
                        CustomTextContainer(
                            textKey: state.userDetails.email != null &&
                                    state.userDetails.email!.isNotEmpty
                                ? state.userDetails.email ?? ''
                                : state.userDetails.mobile ?? '',
                            style: Theme.of(context)
                                .textTheme
                                .bodyMedium!
                                .copyWith(
                                  color:
                                      Theme.of(context).colorScheme.onPrimary,
                                ))
                      ],
                    ),
                  )
                ],
              ),
              PositionedDirectional(
                end: 0,
                child: IconButton(
                    onPressed: () {
                      Utils.navigateToScreen(context, Routes.signupScreen,
                          arguments: true);
                    },
                    icon: const Icon(
                      Icons.edit_square,
                      color: Colors.white,
                    )),
              )
            ],
          ),
        );
      }
      return SizedBox();
    });
  }

  Widget buildCotentBox(String image, String title, String route) {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, route),
      child: Container(
        padding: const EdgeInsets.all(8),
        margin: const EdgeInsets.all(8),
        decoration: ShapeDecoration(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.start,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Container(
                width: 42,
                height: 42,
                padding: const EdgeInsets.all(9),
                decoration: ShapeDecoration(
                  color: Theme.of(context)
                      .colorScheme
                      .primary
                      .withValues(alpha: 0.1),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8)),
                ),
                child: SvgPicture.asset(Utils.getImagePath(image))),
            const SizedBox(height: 8),
            CustomTextContainer(
              textKey: title,
              style: Theme.of(context).textTheme.titleSmall,
            )
          ],
        ),
      ),
    );
  }

  buildListContainer(List<Widget> children) {
    return Container(
      color: Colors.white,
      width: size.width,
      padding: const EdgeInsets.symmetric(
        horizontal: appContentHorizontalPadding,
      ),
      margin: const EdgeInsetsDirectional.only(bottom: 8),
      child: Column(
          crossAxisAlignment: CrossAxisAlignment.start, children: children),
    );
  }

  buildLabel(String title) {
    return Padding(
      padding: const EdgeInsets.only(top: 16, bottom: 24),
      child: CustomTextContainer(
        textKey: title,
        style: Theme.of(context).textTheme.titleMedium!,
      ),
    );
  }

  buildListTile(IconData icon, String title, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: EdgeInsetsDirectional.only(bottom: appContentVerticalSpace),
        decoration:
            BoxDecoration(border: Border.all(color: Colors.transparent)),
        child: Row(
          children: <Widget>[
            Container(
              width: 36,
              height: 36,
              padding: const EdgeInsets.all(7),
              decoration: ShapeDecoration(
                color: Theme.of(context)
                    .colorScheme
                    .primary
                    .withValues(alpha: 0.1),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8)),
              ),
              child: Icon(
                icon,
                size: 22,
                color: Theme.of(context).colorScheme.primary,
              ),
            ),
            const SizedBox(
              width: 8,
            ),
            CustomTextContainer(
              textKey: title,
              style: Theme.of(context)
                  .textTheme
                  .titleMedium!
                  .copyWith(color: textColor),
            ),
            const Spacer(),
            Icon(
              Icons.arrow_forward_ios,
              color: textColor,
            ),
          ],
        ),
      ),
    );
  }

  void openRateUsDialog() async {
    if (await _inAppReview.isAvailable()) {
      _inAppReview.requestReview();
    } else {
      Utils.showSnackBar(message: defaultErrorMessageKey, context: context);
    }
  }

  void openLanguageBottomSheet() {
    Utils.openModalBottomSheet(
      context,
      BlocBuilder<SettingsAndLanguagesCubit, SettingsAndLanguagesState>(
        bloc: context.read<SettingsAndLanguagesCubit>(),
        builder: (context, state) {
          if (state is SettingsAndLanguagesFetchSuccess) {
            return Container(
                height: MediaQuery.of(context).size.height,
                padding: const EdgeInsets.symmetric(
                  horizontal: appContentHorizontalPadding,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CustomTextContainer(
                      textKey: appLanguageKey,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    DesignConfig.smallHeightSizedBox,
                    Expanded(
                      child: ListView.separated(
                        itemCount: state.languages.length,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemBuilder: (_, index) {
                          Language language = state.languages[index];
                          return RadioListTile(
                            contentPadding: EdgeInsets.zero,
                            visualDensity: const VisualDensity(
                                horizontal: -4, vertical: -2),
                            title: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                if (language.nativeLanguage != null)
                                  CustomLabelContainer(
                                    textKey: language.nativeLanguage
                                            .toString()
                                            .capitalizeFirst ??
                                        '',
                                    isFieldValueMandatory: false,
                                  ),
                                CustomLabelContainer(
                                  textKey: language.language
                                          .toString()
                                          .capitalizeFirst ??
                                      '',
                                  isFieldValueMandatory: false,
                                ),
                              ],
                            ),
                            value: language.code,
                            groupValue: _selectedLanguage!.code!,
                            onChanged: (value) async {
                              setState(() {
                                _selectedLanguage = language;
                                _languageInProgress = true;
                              });
                              try {
                                Navigator.of(context).pop();
                                await context
                                    .read<SettingsAndLanguagesCubit>()
                                    .changeLanguage(language);
                              } finally {
                                if (mounted) {
                                  setState(() {
                                    _languageInProgress = false;
                                  });
                                }
                              }
                            },
                          );
                        },
                        separatorBuilder: (BuildContext context, int index) {
                          return const SizedBox(height: 2);
                        },
                      ),
                    ),
                  ],
                ));
          }
          if (state is SettingsAndLanguagesFetchFailure) {
            return const Center(
                child: CustomTextContainer(textKey: dataNotAvailableKey));
          }
          return CustomCircularProgressIndicator(
              indicatorColor: Theme.of(context).colorScheme.primary);
        },
      ),
    );
  }

  void openLogoutDialog() {
    showDialog(
      context: context,
      builder: (BuildContext bldcontext) {
        double width = MediaQuery.of(context).size.width;
        return AlertDialog(
            backgroundColor: Colors.transparent,
            contentPadding: EdgeInsets.zero,
            elevation: 0.0,
            insetPadding: const EdgeInsets.symmetric(
              horizontal: 20,
            ),
            content: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 16),
                    width: width,
                    decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primaryContainer,
                        borderRadius:
                            const BorderRadius.all(Radius.circular(8))),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.info_outline,
                          color: Theme.of(context).colorScheme.primary,
                          size: 30,
                        ),
                        DesignConfig.defaultHeightSizedBox,
                        CustomTextContainer(
                          textKey: logoutKey,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(
                          height: 4.0,
                        ),
                        CustomTextContainer(
                          textKey: areYouSureYouWantToLogoutKey,
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        DesignConfig.defaultHeightSizedBox,
                        Row(
                          children: <Widget>[
                            Expanded(
                              child: CustomRoundedButton(
                                widthPercentage: 0.2,
                                buttonTitle: noKey,
                                showBorder: true,
                                backgroundColor:
                                    Theme.of(context).colorScheme.onPrimary,
                                borderColor: Theme.of(context)
                                    .inputDecorationTheme
                                    .iconColor,
                                style: Theme.of(context)
                                    .textTheme
                                    .bodyMedium!
                                    .copyWith(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .secondary,
                                    ),
                                onTap: () => Utils.popNavigation(context),
                              ),
                            ),
                            const SizedBox(
                              width: 24,
                            ),
                            Expanded(
                              child: BlocConsumer<AuthCubit, AuthState>(
                                listener: (context, state) {
                                  if (state is Unauthenticated) {
                                    Utils.navigateToScreen(
                                        context, Routes.loginScreen,
                                        replaceAll: true);
                                  }
                                },
                                builder: (context, state) {
                                  if (state is AuthInitial) {
                                    return const CircularProgressIndicator();
                                  }
                                  return CustomRoundedButton(
                                    widthPercentage: 0.2,
                                    buttonTitle: logoutKey,
                                    showBorder: false,
                                    onTap: () {
                                      if (state is Authenticated) {
                                        context.read<AuthCubit>().signOut();
                                      } else {
                                        Navigator.of(context).pop();
                                        Utils.showSnackBar(
                                            message: pleaseLoginKey,
                                            context: context);
                                      }
                                    },
                                  );
                                },
                              ),
                            )
                          ],
                        )
                      ],
                    )),
              ],
            ));
      },
    );
  }

  void navigatoToPolicyScreen(String policy) {
    final settings = context.read<SettingsAndLanguagesCubit>().getSettings();

    Utils.navigateToScreen(context, Routes.policyScreen, arguments: {
      'title': policy,
      'content': policy == aboutUsKey ? settings.aboutUs : settings.contactUs
    });
  }
}
