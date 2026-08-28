import 'dart:io';

import 'package:eshoppro_deliveryboy/core/constants/appConstants.dart';
import 'package:eshoppro_deliveryboy/features/auth/blocs/zoneListCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/blocs/downloadFileCubit.dart';
import 'package:eshoppro_deliveryboy/features/auth/screens/zoneSelectionDialog.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customAppbar.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customDropDownContainer.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customRoundedButton.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customTextButton.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/showHidePasswordButton.dart';
import 'package:eshoppro_deliveryboy/utils/designConfig.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:dio/dio.dart' as api;
import 'package:open_filex/open_filex.dart';
import 'package:eshoppro_deliveryboy/core/configs/appConfig.dart';
import '../blocs/signUpCubit.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../commons/blocs/userDetailsCubit.dart';
import '../../../commons/models/userDetails.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import '../../../utils/inputValidators.dart';
import '../../../core/theme/colors.dart';
import '../../../commons/widgets/customTextFieldContainer.dart';

class SignupScreen extends StatefulWidget {
  final bool isEditProfileScreen;
  const SignupScreen({super.key, this.isEditProfileScreen = false});
  static Widget getRouteInstance() {
    return SignupScreen(
      isEditProfileScreen: Get.arguments ?? false,
    );
  }

  @override
  _SignupScreenState createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final _formKey = GlobalKey<FormState>();
  UserDetails? user;
  Map<String, TextEditingController> controllers = {};
  Map<String, String> zoneids = {};
  Map<String, File?> file = {};
  Map<String, FocusNode> focusNodes = {};
  late BuildContext dialogContext;
  bool _hideCurrentPassword = true, _hideCnfrmPassword = true;
  final Map apiformFields = {
    nameKey: "name",
    emailKey: "email",
    mobileNumberKey: "mobile",
    passwordKey: "password",
    confirmPasswordKey: "confirm_password",
    addressKey: "address",
    selectZonesKey: "serviceable_zones[]",
    bonusTypeKey: "bonus_type",
    bonusAmountKey: "bonus_amount",
    bonusPercentageKey: "bonus_percentage",
  };
  @override
  void initState() {
    super.initState();
    apiformFields.forEach((key, value) {
      controllers[key] = TextEditingController();
      focusNodes[key] = FocusNode();
    });
    controllers[bonusTypeKey]!.text = bonusType.keys.first;
    context.read<ZoneListCubit>().getZoneList(
      context,
      {},
    );
    if (widget.isEditProfileScreen) {
      user = (context.read<UserDetailsCubit>().state as UserDetailsFetchSuccess)
          .userDetails;
      if (user != null) {
        controllers[nameKey]!.text = user!.username ?? "";
        controllers[emailKey]!.text = user!.email ?? "";
        controllers[mobileNumberKey]!.text = user!.mobile ?? "";
        controllers[addressKey]!.text = user!.address ?? "";
        controllers[bonusAmountKey]!.text = user!.bonus ?? "";
        controllers[bonusPercentageKey]!.text = user!.bonus ?? "";
        if (user!.bonusType != null) {
          controllers[bonusTypeKey]!.text = user!.bonusType!;
        }

        if (user!.deliverableZoneIds!.trim().isNotEmpty) {
          Map<String, String> ids = {};
          List<String> zonelist = user!.deliverableZones!.split(",");
          List<String> mzoneids = user!.deliverableZoneIds!.split(",");

          for (int i = 0; i < mzoneids.length; i++) {
            try {
              ids[mzoneids[i]] = zonelist[i];
            } catch (e) {
              ids[mzoneids[i]] = "";
            }
          }
          zoneids = ids;
          controllers[selectZonesKey]!.text = ids.values.join(", ");
        }
        controllers[selectZonesKey]!.text = user!.deliverableZones ?? "";
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
        onTap: () => FocusScope.of(context).unfocus(),
        child: Scaffold(
          appBar: CustomAppbar(
            titleKey: widget.isEditProfileScreen ? editProfileKey : signUpKey,
            showBackButton: true,
            elevation: 1.5,
          ),
          bottomNavigationBar: buildFooter(),
          body: BlocConsumer<SignUpCubit, SignUpState>(
              listener: (context, state) {
            if (state is SignUpProgress) {
              Utils.showLoader(context);
            } else {
              Utils.hideLoader(context);
            }
            if (state is SignUpSuccess) {
              FocusScope.of(context).unfocus();
              Utils.showSnackBar(
                  message: state.message,
                  context: context,
                  duration: const Duration(seconds: 2));
              if (widget.isEditProfileScreen) {
                context.read<UserDetailsCubit>().emitUserSuccessState(
                    state.userDetails.toJson(),
                    (context.read<UserDetailsCubit>().state
                            as UserDetailsFetchSuccess)
                        .token);
              } else {
                Navigator.of(context).pop();
              }
            } else if (state is SignUpFailure) {
              FocusScope.of(context).unfocus();
              Utils.showSnackBar(message: state.errorMessage, context: context);
            }
          }, builder: (context, state) {
            return SingleChildScrollView(
              padding: const EdgeInsetsDirectional.symmetric(
                  horizontal: 12, vertical: 12),
              child: Form(
                key: _formKey,
                child: fieldWidgets(),
              ),
            );
          }),
        ));
  }

  buildFooter() {
    return CustomBottomButtonContainer(
        child: CustomRoundedButton(
      widthPercentage: 1,
      buttonTitle: widget.isEditProfileScreen ? updateKey : signUpKey,
      showBorder: false,
      onTap: () {
        if (_formKey.currentState!.validate()) {
          if (!(widget.isEditProfileScreen) &&
              (file[dlBackImageKey] == null || file[dlFrontImageKey] == null)) {
            Utils.showSnackBar(
                message: uploadLicenceWarningKey, context: context);
            return;
          }
          if (widget.isEditProfileScreen == false && file[profileKey] == null) {
            Utils.showSnackBar(
                message: uploadProfileWarningKey, context: context);
            return;
          }
          registrationProcess();
        } else {
          Utils.showSnackBar(
              message: pleaseEnterRequiredFieldsKey, context: context);
          return;
        }
      },
    ));
  }

  registrationProcess() async {
    FocusScope.of(context).unfocus();
    if (isDemoApp) {
      Utils.showSnackBar(message: demoModeOnKey, context: context);
      return;
    }
    Map<String, dynamic> apiParams = {};
    controllers.forEach((key, controller) {
      apiParams[apiformFields[key]] = controller.text.trim();
    });

// delivery boy hasa no right to change his bonus type and value
    apiParams.remove(bonusAmountKey);
    apiParams.remove(bonusTypeKey);
    apiParams.remove(bonusPercentageKey);

    apiParams["serviceable_zones[]"] = zoneids.keys.join(",");

    if (file[profileKey] != null) {
      apiParams["profile_image"] =
          await api.MultipartFile.fromFile(file[profileKey]!.path);
    }
    if (file[dlFrontImageKey] != null) {
      apiParams["front_licence_image"] =
          await api.MultipartFile.fromFile(file[dlFrontImageKey]!.path);
    }
    if (file[dlBackImageKey] != null) {
      apiParams["back_licence_image"] =
          await api.MultipartFile.fromFile(file[dlBackImageKey]!.path);
    }
    if (widget.isEditProfileScreen) {
      apiParams["id"] = user!.id;
    }
    context.read<SignUpCubit>().signUpUser(
        params: apiParams, isEditProfileScreen: widget.isEditProfileScreen);
  }

  fieldWidgets() {
    return Column(children: [
      imageWidget(),
      CustomTextFieldContainer(
          hintTextKey: nameKey,
          textEditingController: controllers[nameKey]!,
          labelKey: nameKey,
          textInputAction: TextInputAction.next,
          focusNode: focusNodes[nameKey],
          isSetValidator: true,
          errmsg: enterNameKey,
          onFieldSubmitted: (v) =>
              FocusScope.of(context).requestFocus(focusNodes[emailKey])),
      CustomTextFieldContainer(
          hintTextKey: emailKey,
          textEditingController: controllers[emailKey]!,
          labelKey: emailKey,
          readOnly: widget.isEditProfileScreen,
          textInputAction: TextInputAction.next,
          keyboardType: TextInputType.emailAddress,
          focusNode: focusNodes[emailKey],
          isSetValidator: true,
          onFieldSubmitted: (v) =>
              FocusScope.of(context).requestFocus(focusNodes[mobileNumberKey])),
      CustomTextFieldContainer(
          hintTextKey: mobileNumberKey,
          textEditingController: controllers[mobileNumberKey]!,
          labelKey: mobileNumberKey,
          readOnly: widget.isEditProfileScreen,
          keyboardType: TextInputType.phone,
          inputFormatters: [
            FilteringTextInputFormatter.digitsOnly, // Allow only digits
            LengthLimitingTextInputFormatter(15), // Limit to 15 digits
          ],
          validator: (v) => Validator.validatePhoneNumber(v, context),
          textInputAction: TextInputAction.next,
          focusNode: focusNodes[mobileNumberKey],
          isSetValidator: true,
          onFieldSubmitted: (v) => FocusScope.of(context).requestFocus(
              focusNodes[
                  !widget.isEditProfileScreen ? passwordKey : addressKey])),
      if (!widget.isEditProfileScreen)
        CustomTextFieldContainer(
            hintTextKey: passwordKey,
            textEditingController: controllers[passwordKey]!,
            labelKey: passwordKey,
            hideText: _hideCurrentPassword,
            textInputAction: TextInputAction.next,
            focusNode: focusNodes[passwordKey],
            isSetValidator: true,
            suffixWidget: ShowHidePasswordButton(
              hidePassword: _hideCurrentPassword,
              onTapButton: () {
                setState(() {
                  _hideCurrentPassword = !_hideCurrentPassword;
                });
              },
            ),
            onFieldSubmitted: (v) => FocusScope.of(context)
                .requestFocus(focusNodes[confirmPasswordKey])),
      if (!widget.isEditProfileScreen)
        CustomTextFieldContainer(
            hintTextKey: confirmPasswordKey,
            textEditingController: controllers[confirmPasswordKey]!,
            labelKey: confirmPasswordKey,
            hideText: _hideCnfrmPassword,
            textInputAction: TextInputAction.next,
            focusNode: focusNodes[confirmPasswordKey],
            isSetValidator: true,
            suffixWidget: ShowHidePasswordButton(
              hidePassword: _hideCnfrmPassword,
              onTapButton: () {
                setState(() {
                  _hideCnfrmPassword = !_hideCnfrmPassword;
                });
              },
            ),
            validator: (String? value) {
              if (value.toString().trim().isEmpty) {
                return context
                    .read<SettingsAndLanguagesCubit>()
                    .getTranslatedValue(labelKey: enterconfirmpasswordKey);
              } else if (controllers[passwordKey]!.text !=
                  controllers[confirmPasswordKey]!.text) {
                return context
                    .read<SettingsAndLanguagesCubit>()
                    .getTranslatedValue(labelKey: confirmpasswordnotmatchedKey);
              }
            },
            onFieldSubmitted: (v) =>
                FocusScope.of(context).requestFocus(focusNodes[addressKey])),
      CustomTextFieldContainer(
          hintTextKey: addressKey,
          textEditingController: controllers[addressKey]!,
          labelKey: addressKey,
          minLines: 3,
          keyboardType: TextInputType.multiline,
          textInputAction: TextInputAction.done,
          focusNode: focusNodes[addressKey],
          isSetValidator: true,
          onFieldSubmitted: (v) => focusNodes[addressKey]!.unfocus()),
      zoneSelectionWidget(),
      if (widget.isEditProfileScreen) bonusWidget(),
      licenceWidget()
    ]);
  }

  bonusWidget() {
    String bonusValKey = controllers[bonusTypeKey]!.text == percentagePerOrder
        ? bonusPercentageKey
        : bonusAmountKey;
    return IgnorePointer(
      ignoring: true,
      // ignoring: widget.isEditProfileScreen,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomDropDownContainer(
              labelKey: bonusTypeKey,
              dropDownDisplayLabels: bonusType.values.toList(),
              selectedValue: controllers[bonusTypeKey]!.text,
              isFieldValueMandatory: true,
              onChanged: (value) {
                setState(() {
                  controllers[bonusTypeKey]!.text = value.toString();
                });
              },
              values: bonusType.keys.toList()),
          CustomTextFieldContainer(
            hintTextKey: bonusValKey,
            textEditingController: controllers[bonusValKey]!,
            labelKey: bonusValKey,
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.done,
            focusNode: focusNodes[bonusValKey],
            isSetValidator: true,
            validator: (value) {
              if (value.isEmpty) {
                return 'Please enter a bonus value';
              }
              if (bonusValKey == bonusPercentageKey) {
                final numValue = double.tryParse(value);

                if (numValue == null) {
                  return 'Please enter a valid number';
                }

                if (numValue < 0 || numValue > 100) {
                  return 'Percentage must be between 0 and 100';
                }
              }
              return null; // Valid input
            },
            suffixWidget: bonusValKey == bonusPercentageKey
                ? const Icon(Icons.percent)
                : null,
          ),
        ],
      ),
    );
  }

  zoneSelectionWidget() {
    return BlocBuilder<ZoneListCubit, ZoneListState>(
      builder: (context, state) {
        if (state is ZoneListFetchSuccess) {
          return CustomTextFieldContainer(
            hintTextKey: selectZonesKey,
            textEditingController: controllers[selectZonesKey]!,
            labelKey: selectZonesKey,
            textInputAction: TextInputAction.next,
            isFieldValueMandatory: false,
            focusNode: AlwaysDisabledFocusNode(),
            isSetValidator: true,
            errmsg: selectZonesKey,
            suffixWidget: const Icon(Icons.arrow_drop_down),
            onTap: () {
              if (state.brandList.isEmpty) return;
              showDialog(
                context: context,
                barrierDismissible: false,
                builder: (BuildContext mcontext) {
                  dialogContext = mcontext;
                  return AlertDialog(
                    backgroundColor: white,
                    contentPadding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    shape: DesignConfig.setRoundedBorder(white, 10, false),
                    content: ZoneSelectionDialog(
                      selectedId: zoneids,
                      onZoneSelect: (Map<String, String> ids) {
                        zoneids.clear();
                        zoneids.addAll(ids);
                        setState(() {
                          controllers[selectZonesKey]!.text =
                              ids.values.join(", ");
                        });
                        Navigator.pop(dialogContext);
                      },
                      zoneListCubit: context.read<ZoneListCubit>(),
                    ),
                  );
                },
              );
            },
          );
        } else {
          return const SizedBox.shrink();
        }
      },
    );
  }

  licenceWidget() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Utils.buildImageUploadWidget(
            context: context,
            labelKey: dlFrontImageKey,
            file: file[dlFrontImageKey],
            subLabel: imgSizeInfoKey,
            onTapUpload: () => Utils.openFileExplorer().then((value) {
                  if (value != null) {
                    setState(() {
                      file[dlFrontImageKey] = value.first;
                    });
                  }
                }),
            onTapClose: () {
              setState(() {
                file[dlFrontImageKey] = null;
              });
            }),
        if (widget.isEditProfileScreen && user != null)
          buildOpenLink(user!.frontLicenceImage!),
        if (file[dlFrontImageKey] != null)
          Text(
            file[dlFrontImageKey]!.path.split("/").last,
            style: Theme.of(context).textTheme.titleMedium,
          ),
        Utils.buildImageUploadWidget(
            context: context,
            labelKey: dlBackImageKey,
            file: file[dlBackImageKey],
            subLabel: imgSizeInfoKey,
            onTapUpload: () => Utils.openFileExplorer().then((value) {
                  if (value != null) {
                    setState(() {
                      file[dlBackImageKey] = value.first;
                    });
                  }
                }),
            onTapClose: () {
              setState(() {
                file[dlBackImageKey] = null;
              });
            }),
        if (file[dlBackImageKey] != null)
          Text(
            file[dlBackImageKey]!.path.split("/").last,
            style: Theme.of(context).textTheme.titleMedium,
          ),
        if (widget.isEditProfileScreen && user != null)
          buildOpenLink(user!.backLicenceImage!),
      ],
    );
  }

  buildOpenLink(String fileUrl) {
    return BlocProvider(
      create: (context) => DownloadFileCubit(),
      child: BlocConsumer<DownloadFileCubit, DownloadFileState>(
        listener: (context, state) {
          if (state is DownloadFileSuccess) {
            OpenFilex.open(state.downloadedFilePath);
          }
        },
        builder: (context, state) {
          return Row(
            children: [
              CustomTextButton(
                  buttonTextKey: clickToOpenFileKey,
                  textStyle: Theme.of(context).textTheme.titleSmall!.copyWith(
                        color: Theme.of(context).colorScheme.primary,
                        decoration: TextDecoration.underline,
                      ),
                  onTapButton: () async {
                    if (user != null) {
                      String downloadFilePath =
                          await Utils.getDowanloadFilePath(
                              fileUrl.substring(fileUrl.lastIndexOf('/') + 1));

                      bool exists = await Utils.fileExists(downloadFilePath);

                      if (exists) {
                        OpenFilex.open(downloadFilePath);
                      } else {
                        context
                            .read<DownloadFileCubit>()
                            .downloadFile(fileUrl: fileUrl);
                      }
                    }
                  }),
              if (state is DownloadFileInProgress)
                SizedBox(
                  height: 30,
                  width: 75,
                  child: LayoutBuilder(builder: (context, boxConstraints) {
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Stack(
                          children: [
                            _buildProgressContainer(
                                width: boxConstraints.maxWidth,
                                color: Theme.of(context)
                                    .colorScheme
                                    .secondary
                                    .withValues(alpha: 0.5)),
                            _buildProgressContainer(
                                width: boxConstraints.maxWidth *
                                    state.uploadedPercentage *
                                    0.01,
                                color: Theme.of(context).colorScheme.primary),
                          ],
                        ),
                        DesignConfig.smallHeightSizedBox,
                        Text(
                          "${Utils.formatDouble(state.uploadedPercentage)} %",
                        )
                      ],
                    );
                  }),
                )
            ],
          );
        },
      ),
    );
  }

  Widget _buildProgressContainer(
      {required double width, required Color color}) {
    return Container(
      width: width,
      decoration:
          BoxDecoration(color: color, borderRadius: BorderRadius.circular(3.0)),
    );
  }

  imageWidget() {
    return Center(
      child: Stack(
        children: [
          ClipOval(
              child: Utils.buildProfilePicture(
                  context,
                  124,
                  widget.isEditProfileScreen && user != null
                      ? (user!.image ?? "")
                      : "",
                  selectedFile: file[profileKey],
                  assetImage: file[profileKey] != null,
                  outerBorderColor: Colors.transparent)),
          Positioned(
            bottom: 0,
            right: 10,
            child: GestureDetector(
              onTap: () => Utils.openFileExplorer(fileType: FileType.image)
                  .then((value) {
                if (value != null) {
                  setState(() {
                    file[profileKey] = value.first;
                  });
                }
              }),
              child: Container(
                decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                        width: 2,
                        color: Theme.of(context).colorScheme.onPrimary)),
                child: CircleAvatar(
                  radius: 16,
                  backgroundColor: Theme.of(context).colorScheme.primary,
                  child: Icon(
                    Icons.camera_alt_outlined,
                    size: 20,
                    color: Theme.of(context).colorScheme.onPrimary,
                  ),
                ),
              ),
            ),
          )
        ],
      ),
    );
  }
}
