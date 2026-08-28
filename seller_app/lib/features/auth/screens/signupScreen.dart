import 'package:dio/dio.dart' as api;
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/features/auth/blocs/signUpCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/categoryListCubit.dart';
import 'package:eshopplus_seller/commons/blocs/zoneListCubit.dart';
import 'package:eshopplus_seller/commons/blocs/allStoreCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/userDetailsCubit.dart';
import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/commons/widgets/circleButton.dart';
import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customRoundedButton.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import '../../../utils/utils.dart';
import '../widgets/SignupPage1.dart';
import '../widgets/SignupPage2.dart';

class SignupScreen extends StatefulWidget {
  final bool isEditProfileScreen;
  const SignupScreen({Key? key, required this.isEditProfileScreen})
      : super(key: key);
  //we  have taken new instance of stores cubit here so that id doesnt affect the seller store state in app
  static Widget getRouteInstance() {
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => ZoneListCubit(),
        ),
        BlocProvider(
          create: (context) => CategoryListCubit(),
        ),
      ],
      child: SignupScreen(
        isEditProfileScreen: Get.arguments,
      ),
    );
  }

  @override
  _SignupScreenState createState() => _SignupScreenState();
}

//we are using same screen for registration and edit profile...so if 'isEditProfileScreen' is set to true then this is edit Profile screen
class _SignupScreenState extends State<SignupScreen> {
  //we will use list of controllers with associated taxt field label as its name..so that we dont have to take individual controllers here
  int _step = 1;
  Map<String, TextEditingController> controllers = {};
  Map<String, dynamic> files = {};
  Map<String, FocusNode> focusNodes = {};
  int? selectedStoreId;
  UserDetails? user;
  StoreData? currentStore;
  late Size size;
  final _formKey = GlobalKey<FormState>();

  Map<String, String> selectedZipcodeCity = {};

  //if we add or remove any field from form then also add it or remove it respectively from 'apiformFields' list
  final Map apiformFields = {
    nameKey: "name",
    emailKey: "email",
    mobileNumberKey: "mobile",
    passwordKey: "password",
    confirmPasswordKey: "confirm_password",
    addressKey: "address",
    selectStoreKey: "store_id",
    storeNameKey: "store_name",
    storeUrlKey: "store_url",
    storeDescKey: "description",
    bankNameKey: "bank_name",
    bankCodeKey: "bank_code",
    accountNameKey: "account_name",
    accountNumberKey: "account_number",
    taxNameKey: "tax_name",
    taxNumberKey: "tax_number",
    panNumberKey: "pan_number",
    latitudeKey: "latitude",
    longitudeKey: "longitude",
    selectZipCodeKey: "zipcode",
    selectCityKey: "city",
    deliverableTypeKey: "deliverable_type",
    selectZonesKey: "deliverable_zones",
    selectCategoryKey: "requested_categories"
  };

  Map<String, dynamic> apiParams = {};
  @override
  void initState() {
    super.initState();

    apiformFields.forEach(
      (key, value) {
        controllers[key] = TextEditingController();
        focusNodes[key] = FocusNode();
      },
    );

    controllers[deliverableTypeKey]!.text = sellerDeliverableTypes.keys.first;
    files['recent_doc'] = <dynamic>[];
    files[otherDocumentsKey] = <dynamic>[];
    files[selectCategoryKey] = <String>[];
    // Initialize text editing controllers
    Future.delayed(Duration.zero, () {
      //seller cant choose none option for deliverability...so remove it

      if (widget.isEditProfileScreen == true) {
        user =
            (context.read<UserDetailsCubit>().state as UserDetailsFetchSuccess)
                .userDetails;
        if (user != null) {
          currentStore =
              context.read<UserDetailsCubit>().getDefaultStoreOfUser(context);
          if (currentStore != null) {
            selectedStoreId = currentStore!.storeId;
            controllers[nameKey] = TextEditingController(text: user!.username);
            controllers[emailKey] = TextEditingController(text: user!.email);
            controllers[mobileNumberKey] =
                TextEditingController(text: user!.mobile);

            controllers[addressKey] =
                TextEditingController(text: user!.address);

            controllers[storeNameKey] =
                TextEditingController(text: currentStore!.storeName);
            controllers[storeUrlKey] =
                TextEditingController(text: currentStore!.storeUrl);
            controllers[storeDescKey] =
                TextEditingController(text: currentStore!.storeDescription);
            controllers[bankNameKey] =
                TextEditingController(text: currentStore!.bankName);
            controllers[bankCodeKey] =
                TextEditingController(text: currentStore!.bankCode);
            controllers[accountNameKey] =
                TextEditingController(text: currentStore!.accountName);
            controllers[accountNumberKey] =
                TextEditingController(text: currentStore!.accountNumber);
            controllers[taxNameKey] =
                TextEditingController(text: currentStore!.taxName);
            controllers[taxNumberKey] =
                TextEditingController(text: currentStore!.taxNumber);
            controllers[panNumberKey] =
                TextEditingController(text: user!.sellerData!.first.panNumber);
            controllers[latitudeKey] =
                TextEditingController(text: currentStore!.latitude);
            controllers[longitudeKey] =
                TextEditingController(text: currentStore!.longitude);
            controllers[selectCityKey] =
                TextEditingController(text: currentStore!.city);
            controllers[selectZipCodeKey] =
                TextEditingController(text: currentStore!.zipcode);
            selectedZipcodeCity[selectZipCodeKey] = currentStore!.zipcodeId!;
            selectedZipcodeCity[selectCityKey] = currentStore!.cityId!;
            files[otherDocumentsKey] = currentStore!.otherDocuments ?? [];
            files[selectCategoryKey] = currentStore!.categoryIds!.split(",");
            controllers[deliverableTypeKey]!.text =
                currentStore!.deliverableType!.toString();
            if (isSelectZipCode(controllers[deliverableTypeKey]!.text)) {
              Map<String, String> ids = {};
              List<String> zonelist = currentStore!.zones!.split(",");
              List<String> zoneids = currentStore!.deliverableZones!.split(",");
              for (int i = 0; i < zonelist.length; i++) {
                ids[zoneids[i]] = zonelist[i];
              }
              files[selectZonesKey] = ids;
              controllers[selectZonesKey]!.text = ids.values.join(", ");
            }
            setState(() {});
          }
        }
      }
      context.read<AllStoresCubit>().fetchAllStores(context);
      context.read<ZoneListCubit>().getZoneList(
        context,
        {},
      );
    });
  }

  @override
  void dispose() {
    // Dispose all text editing controllers
    controllers.forEach((key, controller) {
      controller.dispose();
    });
    focusNodes.forEach((key, focusNode) {
      focusNode.dispose();
    });

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    size = MediaQuery.of(context).size;
    return GestureDetector(
      onTap: () => FocusScope.of(context).unfocus(),
      child: Scaffold(
        appBar: CustomAppbar(
          titleKey: !widget.isEditProfileScreen
              ? sellerRegistrationKey
              : editProfileKey,
          showBackButton: true,
          elevation: 1.5,
          trailingWidget: !widget.isEditProfileScreen
              ? Text(
                  'Step $_step of 2',
                  style: Theme.of(context)
                      .textTheme
                      .titleSmall!
                      .copyWith(color: Theme.of(context).colorScheme.primary),
                )
              : null,
        ),

        //in edit profile sscreen ,all fields are in only one page so we have to assign them in one column .
        //and in registration form, there are two different pages
        body: BlocConsumer<AllStoresCubit, AllStoresState>(
          listener: (context, state) {},
          builder: (context, state) {
            if (state is AllStoresFetchSuccess) {
              return BlocConsumer<SignUpCubit, SignUpState>(
                listener: (context, state) {
                  if (state is SignUpProgress) {
                    Utils.showLoader(context);
                  } else {
                    Utils.hideLoader(context);
                  }
                  if (state is SignUpSuccess) {
                    Utils.showSnackBar(
                        message: state.message,
                        msgDuration: const Duration(seconds: 2));
                    Navigator.of(context).pop();

                    if (widget.isEditProfileScreen) {
                      Navigator.of(context).pop();
                      context.read<UserDetailsCubit>().emitUserSuccessState(
                          state.userDetails.toJson(),
                          (context.read<UserDetailsCubit>().state
                                  as UserDetailsFetchSuccess)
                              .token);
                    }
                  } else if (state is SignUpFailure) {
                    Utils.showSnackBar(message: state.errorMessage);
                  }
                },
                builder: (context, state) {
                  return Form(
                      key: _formKey,
                      autovalidateMode: AutovalidateMode.onUserInteraction,
                      child: (widget.isEditProfileScreen)
                          ? SingleChildScrollView(
                              child: Column(
                                children: <Widget>[
                                  buildStoreImage(),
                                  getSignupPageWidget(true),
                                  getSignupPageWidget(false)
                                ],
                              ),
                            )
                          : getSignupPageWidget(_step == 1));
                },
              );
            }
            if (state is AllStoresFetchFailure) {
              return ErrorScreen(
                text: state.errorMessage,
                onPressed: () {
                  context.read<AllStoresCubit>().fetchAllStores(context);
                },
              );
            }
            return Center(
              child: CustomCircularProgressIndicator(
                indicatorColor: Theme.of(context).colorScheme.primary,
              ),
            );
          },
        ),
        bottomNavigationBar: buildFooter(context),
      ),
    );
  }

  getSignupPageWidget(bool page1) {
    if (page1) {
      return SignupPage1(
          controllers: controllers,
          files: files,
          focusNodes: focusNodes,
          isEditProfileScreen: widget.isEditProfileScreen);
    } else {
      return SignupPage2(
        controllers: controllers,
        files: files,
        focusNodes: focusNodes,
        selectedStore: selectedStoreId,
        isEditProfileScreen: widget.isEditProfileScreen,
        callback: updateSelectedStore,
        selectedZipcodeCity: selectedZipcodeCity,
        allStoresCubit: context.read<AllStoresCubit>(),
      );
    }
  }

  updateSelectedStore(int id) {
    selectedStoreId = id;
    if (mounted) setState(() {});
  }

  buildFooter(BuildContext context) {
    return CustomBottomButtonContainer(
        child: (widget.isEditProfileScreen)
            ? CustomRoundedButton(
                widthPercentage: 1,
                buttonTitle: updateProfileKey,
                showBorder: false,
                onTap: () {
                  signupPage2Validation();
                },
              )
            : _step == 1
                ? CustomRoundedButton(
                    widthPercentage: 1,
                    buttonTitle: nextKey,
                    showBorder: false,
                    onTap: () {
                      signupPage1Validation();
                    },
                  )
                : Row(
                    children: <Widget>[
                      Expanded(
                        child: CustomRoundedButton(
                          widthPercentage: 0.4,
                          buttonTitle: backKey,
                          showBorder: true,
                          backgroundColor:
                              Theme.of(context).colorScheme.onPrimary,
                          borderColor: Theme.of(context).hintColor,
                          style: Theme.of(context)
                              .textTheme
                              .bodyMedium!
                              .copyWith(
                                  color:
                                      Theme.of(context).colorScheme.secondary),
                          onTap: () {
                            setState(() {
                              _step = 1;
                            });
                          },
                        ),
                      ),
                      const SizedBox(
                        width: 16,
                      ),
                      Expanded(
                        child: CustomRoundedButton(
                          widthPercentage: 0.4,
                          buttonTitle: registerKey,
                          showBorder: false,
                          onTap: () {
                            signupPage2Validation();
                          },
                        ),
                      )
                    ],
                  ));
  }

  signupPage2Validation() {
    if (_formKey.currentState!.validate()) {
      if (!(widget.isEditProfileScreen)) {
        if (isFileSelectedOrNot(addressProofKey, selectAddressProofKey) &&
            isFileSelectedOrNot(storeLogoKey, selectStoreLogoKey) &&
            isFileSelectedOrNot(storeThumbnailKey, selectStoreThumbnailKey)) {
          registrationProcess();
        }
      } else {
        submitForm();
      }
    } else {
      Utils.showSnackBar(message: pleaseEnterRequiredFieldsKey);
    }
  }

  submitForm() {
    Utils.openAlertDialog(context,
        onTapNo: registrationProcess, noLabel: 'Update Seller', onTapYes: () {
      Navigator.of(context).pop();
      Utils.navigateToScreen(context, Routes.deliverabiltyScreen);
    }, message: changeProductDeliverabilityNoteKey);
  }

  registrationProcess() async {
    if (isDemoApp) {
      Utils.showSnackBar(message: demoModeOnKey);
      return;
    }

    apiParams.clear();

    controllers.forEach((key, controller) {
      apiParams[apiformFields[key]] = controller.text;
    });

    if (widget.isEditProfileScreen == false) {
      apiParams["store_id"] = selectedStoreId.toString();
      apiParams["address_proof"] =
          await api.MultipartFile.fromFile(files[addressProofKey]!.path);
      apiParams["national_identity_card"] = await api.MultipartFile.fromFile(
          files[nationalIdentityCardKey]!.path);
      apiParams["store_logo"] =
          await api.MultipartFile.fromFile(files[storeLogoKey]!.path);
      apiParams["authorized_signature"] =
          await api.MultipartFile.fromFile(files[authorizedSignatureKey]!.path);
      apiParams["store_thumbnail"] =
          await api.MultipartFile.fromFile(files[storeThumbnailKey]!.path);
    } else {
      apiParams["id"] = user!.id;
      apiParams["store_id"] = context.read<StoresCubit>().getDefaultStore().id;
      if (files[storeLogoKey] != null) {
        apiParams["store_logo"] =
            await api.MultipartFile.fromFile(files[storeLogoKey]!.path);
      }
      if (files[storeThumbnailKey] != null) {
        apiParams["store_thumbnail"] =
            await api.MultipartFile.fromFile(files[storeThumbnailKey]!.path);
      }
    }
    files.forEach(
      (key, value) {
        if (key == otherImagesKey) {
          apiParams[apiformFields[otherImagesKey]!] = value.keys.join(",");
        } else if (key == selectZonesKey) {
          if ((files[selectZonesKey] ?? {} as Map<String, String>).isNotEmpty) {
            apiParams[apiformFields[selectZonesKey]!] =
                (files[selectZonesKey]).keys.join(",");
          }
        } else if (key == selectCategoryKey) {
          if ((files[selectCategoryKey] ?? {} as Map<String, String>)
              .isNotEmpty) {
            apiParams[apiformFields[selectCategoryKey]!] =
                (files[selectCategoryKey]).join(",");
          }
        }
      },
    );
    if (files['recent_doc'] != null && files['recent_doc'].isNotEmpty) {
      for (int i = 0; i < files['recent_doc'].length; i++) {
        apiParams['other_documents[$i]'] =
            await api.MultipartFile.fromFile(files['recent_doc'][i].path);
      }
    }
    selectedZipcodeCity.forEach(
      (key, value) {
        apiParams[apiformFields[key]] = value;
      },
    );
    context.read<SignUpCubit>().signUpUser(
        params: apiParams, isEditProfileScreen: widget.isEditProfileScreen);
  }

  signupPage1Validation() {
    if (_formKey.currentState!.validate()) {
      if (!(widget.isEditProfileScreen)) {
        if (isFileSelectedOrNot(
                authorizedSignatureKey, selectAuthSignatureKey) &&
            isFileSelectedOrNot(nationalIdentityCardKey, selectNationalIdKey)) {
          goToStep2();
        }
      } else {
        goToStep2();
      }
    } else {
      Utils.showSnackBar(message: pleaseEnterRequiredFieldsKey);
    }
  }

  goToStep2() {
    setState(() {
      _step = 2;
    });
  }

  isFileSelectedOrNot(String filekey, String errmsgkey) {
    if (!files.containsKey(filekey) || files[filekey] == null) {
      Utils.showSnackBar(message: errmsgkey);
      return false;
    }
    return true;
  }

  buildStoreImage() {
    if (currentStore != null) {
      return Container(
          height: size.height * 0.3,
          width: size.width,
          decoration: BoxDecoration(
              image: DecorationImage(
                  opacity: 0.8,
                  image: files[storeThumbnailKey] != null
                      ? FileImage(files[storeThumbnailKey]!) as ImageProvider
                      : NetworkImage(
                          currentStore!.storeThumbnail!,
                        ),
                  fit: BoxFit.cover)),
          child: Stack(children: [
            Center(
              child: Stack(
                children: [
                  Container(
                    height: 120,
                    width: 120,
                    decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        image: DecorationImage(
                            image: files[storeLogoKey] != null
                                ? FileImage(files[storeLogoKey]!)
                                    as ImageProvider
                                : NetworkImage(
                                    currentStore!.logo!,
                                  ),
                            fit: BoxFit.cover)),
                  ),
                  Positioned(
                      bottom: 0,
                      right: 5,
                      child: CircleButton(
                          heightAndWidth: 28,
                          backgroundColor:
                              Theme.of(context).colorScheme.primary,
                          child: Icon(
                            Icons.add,
                            size: 24,
                            color:
                                Theme.of(context).colorScheme.primaryContainer,
                          ),
                          onTap: () =>
                              Utils.openFileExplorer(fileType: FileType.image)
                                  .then((value) {
                                if (value != null) {
                                  setState(() {
                                    files[storeLogoKey] = value.first;
                                  });
                                }
                              }))),
                ],
              ),
            ),
            Positioned(
                right: 16,
                top: 16,
                child: CircleButton(
                    heightAndWidth: 32,
                    backgroundColor:
                        Theme.of(context).colorScheme.primaryContainer,
                    boxShadow: DesignConfig.appShadow,
                    child: Icon(
                      Icons.camera_alt_outlined,
                      size: 18,
                      color: Theme.of(context)
                          .colorScheme
                          .secondary
                          .withValues(alpha: 0.8),
                    ),
                    onTap: () =>
                        Utils.openFileExplorer(fileType: FileType.image)
                            .then((value) {
                          if (value != null) {
                            setState(() {
                              files[storeThumbnailKey] = value.first;
                            });
                          }
                        }))),
            Container(),
          ]));
    }
    return const SizedBox.shrink();
  }
}
