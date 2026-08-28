import 'package:dio/dio.dart' as api;
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/categoryListCubit.dart';
import 'package:eshopplus_seller/commons/blocs/zoneListCubit.dart';
import 'package:eshopplus_seller/features/profile/addSellerStore/blocs/addStoreCubit.dart';
import 'package:eshopplus_seller/commons/blocs/allStoreCubit.dart';
import 'package:eshopplus_seller/commons/blocs/userDetailsCubit.dart';
import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/commons/repositories/storeRepository.dart';
import 'package:eshopplus_seller/features/auth/widgets/SignupPage2.dart';
import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customRoundedButton.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class AddSellerStoreScreen extends StatefulWidget {
  const AddSellerStoreScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() {
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => AddStoreCubit(),
        ),
        BlocProvider(
          create: (context) => AllStoresCubit(StoreRepository()),
        ),
        BlocProvider(create: (context) => ZoneListCubit()),
        BlocProvider(create: (context) => CategoryListCubit()),
      ],
      child: const AddSellerStoreScreen(),
    );
  }

  @override
  _AddSellerStoreScreenState createState() => _AddSellerStoreScreenState();
}

class _AddSellerStoreScreenState extends State<AddSellerStoreScreen> {
  Map<String, TextEditingController> controllers = {};
  Map<String, dynamic> files = {};
  Map<String, FocusNode> focusNodes = {};
  Map<String, dynamic> apiParams = {};
  Map<String, String> selectedZipcodeCity = {};
  int? selectedStoreId;
  UserDetails? user;
  late StoreData currentStore;
  late Size size;
  final _formKey = GlobalKey<FormState>();

  final List formFields = [
    mobileNumberKey,
    selectStoreKey,
    storeNameKey,
    storeUrlKey,
    storeDescKey,
    selectZipCodeKey,
    selectCityKey,
    bankNameKey,
    bankCodeKey,
    accountNameKey,
    accountNumberKey,
    taxNameKey,
    taxNumberKey,
    panNumberKey,
    latitudeKey,
    longitudeKey,
    deliverableTypeKey,
    selectZonesKey,
    selectCategoryKey
  ];
  final Map apiformFields = {
    mobileNumberKey: "mobile",
    selectStoreKey: "store_id",
    storeNameKey: "store_name",
    storeUrlKey: "store_url",
    storeDescKey: "description",
    selectZipCodeKey: "zipcode",
    selectCityKey: "city",
    bankNameKey: "bank_name",
    bankCodeKey: "bank_code",
    accountNameKey: "account_name",
    accountNumberKey: "account_number",
    taxNameKey: "tax_name",
    taxNumberKey: "tax_number",
    panNumberKey: "pan_number",
    latitudeKey: "latitude",
    longitudeKey: "longitude",
    deliverableTypeKey: "deliverable_type",
    selectZonesKey: "deliverable_zones",
    selectCategoryKey: "requested_categories"
  };
  @override
  void initState() {
    super.initState();
    files['recent_doc'] = <dynamic>[];
    for (var key in formFields) {
      controllers[key] = TextEditingController();

      focusNodes[key] = FocusNode();
    }
    files[selectCategoryKey] = <String>[];
    controllers[deliverableTypeKey]!.text = sellerDeliverableTypes.keys.first;
    Future.delayed(Duration.zero, () {
      context.read<AllStoresCubit>().fetchAllStores(context);
      context.read<ZoneListCubit>().getZoneList(
        context,
        {},
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<AllStoresCubit, AllStoresState>(
      listener: (context, state) {
        if (state is AllStoresFetchSuccess) {
          if (context.read<UserDetailsCubit>().state
              is UserDetailsFetchSuccess) {
            List<int> userStoreIds = context
                .read<UserDetailsCubit>()
                .getuserDetails()
                .storeData!
                .map((store) => store.storeId!)
                .toList();
            state.stores
                .removeWhere((element) => userStoreIds.contains(element.id));
          }
        }
      },
      child: GestureDetector(
        onTap: () => FocusScope.of(context).unfocus(),
        child: Scaffold(
            appBar: const CustomAppbar(titleKey: addStoreKey),
            bottomNavigationBar: buildSubmitButon(),
            body: Form(
              key: _formKey,
              child: SignupPage2(
                controllers: controllers,
                files: files,
                focusNodes: focusNodes,
                selectedStore: selectedStoreId,
                isEditProfileScreen: false,
                callback: updateSelectedStore,
                selectedZipcodeCity: selectedZipcodeCity,
                allStoresCubit: context.read<AllStoresCubit>(),
              ),
            )),
      ),
    );
  }

  updateSelectedStore(int id) {
    selectedStoreId = id;
    if (mounted) setState(() {});
  }

  buildSubmitButon() {
    return CustomBottomButtonContainer(
      child: BlocConsumer<AddStoreCubit, AddStoreState>(
        listener: (context, state) {
          if (state is AddStoreSuccess) {
            Utils.showSnackBar(message: state.message);
            Navigator.of(context).pop();
          } else if (state is AddStoreFailure) {
            Utils.showSnackBar(message: state.errorMessage);
          }
        },
        builder: (context, state) {
          return CustomRoundedButton(
            widthPercentage: 1.0,
            buttonTitle: addStoreKey,
            showBorder: false,
            child: state is AddStoreProgress
                ? const CustomCircularProgressIndicator()
                : null,
            onTap: () async {
              if (_formKey.currentState!.validate()) {
                if (isDemoApp) {
                  Utils.showSnackBar(message: demoModeOnKey);
                  return;
                }
                if (state is! AddStoreProgress) {
                  if (isFileSelectedOrNot(
                          storeThumbnailKey, selectStoreThumbnailKey) &&
                      isFileSelectedOrNot(storeLogoKey, selectStoreLogoKey) &&
                      isFileSelectedOrNot(
                          addressProofKey, selectAddressProofKey)) {
                    apiParams.clear();

                    controllers.forEach((key, controller) {
                      apiParams[apiformFields[key]] = controller.text;
                    });
                    selectedZipcodeCity.forEach(
                      (key, value) {
                        apiParams[apiformFields[key]] = value;
                      },
                    );
                    apiParams["mobile"] =
                        context.read<UserDetailsCubit>().getUserMobile();
                    apiParams["store_id"] = selectedStoreId.toString();
                    apiParams["address_proof"] =
                        await api.MultipartFile.fromFile(
                            files[addressProofKey]!.path);

                    apiParams["store_logo"] = await api.MultipartFile.fromFile(
                        files[storeLogoKey]!.path);

                    apiParams["store_thumbnail"] =
                        await api.MultipartFile.fromFile(
                            files[storeThumbnailKey]!.path);
                    files.forEach(
                      (key, value) {
                        if (key == otherImagesKey) {
                          apiParams[apiformFields[otherImagesKey]!] =
                              value.keys.join(",");
                        } else if (key == selectZonesKey) {
                          if ((files[selectZonesKey] ??
                                  {} as Map<String, String>)
                              .isNotEmpty) {
                            apiParams[apiformFields[selectZonesKey]!] =
                                (files[selectZonesKey]).keys.join(",");
                          }
                        } else if (key == selectCategoryKey) {
                          if ((files[selectCategoryKey] ??
                                  {} as Map<String, String>)
                              .isNotEmpty) {
                            apiParams[apiformFields[selectCategoryKey]!] =
                                (files[selectCategoryKey]).join(",");
                          }
                        }
                      },
                    );
                    if (files['recent_doc'] != null &&
                        files['recent_doc'].isNotEmpty) {
                      for (int i = 0; i < files['recent_doc'].length; i++) {
                        apiParams['other_documents[$i]'] =
                            await api.MultipartFile.fromFile(
                                files['recent_doc'][i].path);
                      }
                    }
                  }
                }
                if (isDemoApp) {
                  Utils.showSnackBar(message: demoModeOnKey);
                  return;
                }
                context.read<AddStoreCubit>().addSellerStore(params: apiParams);
              } else {
                Utils.showSnackBar(
                    message: pleaseEnterRequiredFieldsKey);
              }
            },
          );
        },
      ),
    );
  }

  isFileSelectedOrNot(String filekey, String errmsgkey) {
    if (!files.containsKey(filekey) || files[filekey] == null) {
      Utils.showSnackBar(message: errmsgkey);
      return false;
    }
    return true;
  }
}
