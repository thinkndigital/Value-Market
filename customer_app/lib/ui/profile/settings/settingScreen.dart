import 'dart:math' as math;
import 'package:country_code_picker/country_code_picker.dart';
import 'package:eshop_plus/core/configs/appConfig.dart';
import 'package:eshop_plus/ui/auth/cubits/resetPasswordCubit.dart';
import 'package:eshop_plus/commons/blocs/updateUserCubit.dart';
import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customCuperinoSwitch.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextFieldContainer.dart';
import 'package:eshop_plus/commons/widgets/filterContainerForBottomSheet.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/validator.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/routes/routes.dart';
import '../../../utils/utils.dart';

class SettingScreen extends StatefulWidget {
  const SettingScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => UpdateUserCubit(),
        child: const SettingScreen(),
      );
  @override
  _SettingScreenState createState() => _SettingScreenState();
}

class _SettingScreenState extends State<SettingScreen>
    with SingleTickerProviderStateMixin {
  bool _isExpand = false;
  late AnimationController _expandController;
  late Animation<double> _animation, _roatationAnimation;
  late Color fontColor;
  final TextEditingController _mobileController = TextEditingController();
  final GlobalKey<FormState> formKey = GlobalKey<FormState>();
  final List<String> settingItems = [
    (pushNotificationsKey),
    (promotionalEmailsKey),
    (promotionalSMSKey),
  ];

  bool value = false;
  @override
  void initState() {
    super.initState();
    prepareAnimations();
    setRotation(45);
  }

  ///Setting up the animation
  void prepareAnimations() {
    _expandController =
        AnimationController(vsync: this, duration: const Duration(seconds: 1));
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
          curve: Curves.fastOutSlowIn,
        )));
  }

  @override
  void dispose() {
    _expandController.dispose();
    _mobileController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    fontColor = Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8);
    return Scaffold(
      appBar: const CustomAppbar(
        titleKey: settingsKey,
        showBackButton: true,
      ),
      body: buildContent(),
    );
  }

  SingleChildScrollView buildContent() {
    return SingleChildScrollView(
      padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
      child: Column(
        children: <Widget>[
          if (context.read<UserDetailsCubit>().getUserType() == phoneLoginType)
            buildListTile(
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    buildTitle(changePasswordKey),
                    buildIcon(),
                  ],
                ),
                () => openDialog(context)),
          buildListTile(
              Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      buildTitle(notificationPrefKey),
                      AnimatedBuilder(
                          animation: _roatationAnimation,
                          child: buildIcon(),
                          builder: (context, child) {
                            return Transform.rotate(
                                angle: _roatationAnimation.value, child: child);
                          }),
                    ],
                  ),
                  buildNotificationPrefList()
                ],
              ), () {
            _isExpand = !_isExpand;

            if (_isExpand) {
              _expandController.forward();
            } else {
              _expandController.reverse();
            }

            setState(() {});
          }),
          buildListTile(
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  buildTitle(deleteAccountKey),
                  buildIcon(),
                ],
              ),
              () =>
                  Utils.navigateToScreen(context, Routes.deleteAccountScreen)),
        ],
      ),
    );
  }

  Widget buildListTile(Widget child, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
          width: double.infinity,
          margin: const EdgeInsetsDirectional.only(bottom: 8),
          padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
          decoration: ShapeDecoration(
            color: Colors.white,
            shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(borderRadius)),
          ),
          child: child),
    );
  }

  buildTitle(String title) {
    return CustomTextContainer(
      textKey: title,
      style: Theme.of(context).textTheme.titleMedium!.copyWith(
            color: fontColor,
          ),
    );
  }

  buildIcon() {
    return Icon(
      Icons.arrow_forward_ios,
      color: fontColor,
    );
  }

  buildNotificationPrefList() {
    return BlocBuilder<UserDetailsCubit, UserDetailsState>(
      builder: (context, state) {
        return SizeTransition(
          axisAlignment: 1.0,
          sizeFactor: _animation,
          child: Padding(
            padding: const EdgeInsetsDirectional.symmetric(vertical: 8),
            child: Column(
              children: <Widget>[
                _buildSettingItem(
                  0,
                  context.read<UserDetailsCubit>().isNotificationOn(),
                )
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildSettingItem(int index, bool value) {
    return BlocConsumer<UpdateUserCubit, UpdateUserState>(
      listener: (context, state) {
        if (state is UpdateUserFetchSuccess) {
          context.read<UserDetailsCubit>().emitUserSuccessState(
              state.userDetails.toJson(),
              (context.read<UserDetailsCubit>().state
                      as UserDetailsFetchSuccess)
                  .token);

          Utils.showSnackBar(message: state.successMessage, context: context);
        }
        if (state is UpdateUserFetchFailure) {
          Utils.showSnackBar(message: state.errorMessage, context: context);
        }
      },
      builder: (context, state) {
        return Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: <Widget>[
            CustomTextContainer(
              textKey: settingItems[index],
              style: Theme.of(context)
                  .textTheme
                  .bodyMedium!
                  .copyWith(color: fontColor),
            ),
            CustomCuperinoSwitch(
              value: value,
              onChanged: (value) {
                //see this values in settingItems list
                switch (index) {
                  case 0:
                    if (!value) {
                      Utils.openAlertDialog(context, onTapYes: () {
                        Navigator.of(context).pop();
                        context.read<UpdateUserCubit>().updateUser(
                            params: {ApiURL.isNotificationOnApiKey: 0});
                      }, message: offNotificationWarningKey);
                    } else {
                      context.read<UpdateUserCubit>().updateUser(
                          params: {ApiURL.isNotificationOnApiKey: 1});
                    }
                    break;
                }

                setState(() {});
              },
            )
          ],
        );
      },
    );
  }

  openDialog(BuildContext context) {
    if (Utils.isDemoUser(context)) {
      return Utils.showSnackBar(
          message: 'Modification not allowed in demo app', context: context);
    }
    final TextEditingController _mobileController = TextEditingController();

    final GlobalKey<FormState> formKey = GlobalKey<FormState>();
    Utils.openModalBottomSheet(context,
            StatefulBuilder(builder: (context, StateSetter setState) {
      return BlocProvider(
        create: (context) => ResetPasswordCubit(),
        child: BlocConsumer<ResetPasswordCubit, ResetPasswordState>(
          listener: (context, state) async {
            if (state is ResetPasswordSuccess) {
              Navigator.of(context).pop();
              Utils.showSnackBar(
                  message: state.successMessage, context: context);
            } else if (state is ResetPasswordFailure) {
              Navigator.of(context).pop();
              Utils.showSnackBar(message: state.errorMessage, context: context);
            }
          },
          builder: (context, state) {
            return FilterContainerForBottomSheet(
                title: resetPasswordKey,
                borderedButtonTitle: cancelKey,
                primaryButtonTitle: sendKey,
                borderedButtonOnTap: () => Navigator.of(context).pop(),
                primaryChild: state is ResetPasswordInProgress
                    ? const CustomCircularProgressIndicator()
                    : null,
                primaryButtonOnTap: () {
                  if (state is ResetPasswordInProgress) {
                    return;
                  }
                  if (formKey.currentState!.validate()) {
                    {
                      context.read<ResetPasswordCubit>().resetPassword(params: {
                        ApiURL.mobileNoApiKey: _mobileController.text.trim(),
                      });
                    }
                  }
                },
                content: Form(
                  key: formKey,
                  child: Padding(
                    padding:
                        const EdgeInsetsDirectional.only(top: 16, bottom: 16),
                    child: Column(
                      children: [
                        const CustomTextContainer(
                            textKey: weWillSendVerificationCodeToKey),
                        DesignConfig.defaultHeightSizedBox,
                        Row(
                          children: <Widget>[
                            Expanded(
                                flex: 1,
                                child: Container(
                                  padding: const EdgeInsetsDirectional.only(
                                      end: 0.0, bottom: 0.0, top: 0, start: 0),
                                  margin:
                                      const EdgeInsetsDirectional.only(end: 5),
                                  decoration: ShapeDecoration(
                                    shape: RoundedRectangleBorder(
                                      side: BorderSide(
                                          width: 1,
                                          color: Theme.of(context)
                                              .colorScheme
                                              .secondary
                                              .withValues(alpha: 0.3)),
                                      borderRadius:
                                          BorderRadius.circular(borderRadius),
                                    ),
                                  ),
                                  child: CountryCodePicker(
                                    onChanged: (countryCode) {},
                                    flagWidth: 25,
                                    padding: const EdgeInsetsDirectional.all(0),
                                    // Initial selection and favorite can be one of code ('IT') OR dial_code('+39')
                                    initialSelection: initialCountryCode,
                                    // favorite: const ['+91', 'IN'],
                                    showFlagDialog: true,
                                    comparator: (a, b) =>
                                        b.name!.compareTo(a.name!),
                                    //Get the country information relevant to the initial selection
                                    onInit: (code) {},
                                    alignLeft: true,
                                  ),
                                )),
                            Expanded(
                              flex: 2,
                              child: CustomTextFieldContainer(
                                hintTextKey: mobileNumberKey,
                                textInputAction: TextInputAction.done,
                                keyboardType: TextInputType.phone,
                                textEditingController: _mobileController,
                                inputFormatters: [
                                  FilteringTextInputFormatter
                                      .digitsOnly, // Allow only digits
                                  LengthLimitingTextInputFormatter(
                                      15), // Limit to 15 digits
                                ],
                                labelKey: '',
                                validator: (v) =>
                                    Validator.validatePhoneNumber(v, context),
                                suffixWidget: IconButton(
                                  icon: Icon(
                                    Icons.close_rounded,
                                    color:
                                        Theme.of(context).colorScheme.secondary,
                                  ),
                                  onPressed: () => _mobileController.clear(),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ));
          },
        ),
      );
    }), staticContent: true)
        .then((value) {});
  }
}
