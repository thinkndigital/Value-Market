import 'dart:io';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/categoryListCubit.dart';
import 'package:eshopplus_seller/commons/blocs/zoneListCubit.dart';
import 'package:eshopplus_seller/commons/blocs/allStoreCubit.dart';
import 'package:eshopplus_seller/features/auth/blocs/zipcodeListCubit.dart';
import 'package:eshopplus_seller/commons/blocs/userDetailsCubit.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/category.dart';
import 'package:eshopplus_seller/features/profile/addProduct/widgets/helper/categorySelectionDialog.dart';
import 'package:eshopplus_seller/features/profile/addProduct/widgets/helper/zoneSelectionDialog.dart';
import 'package:eshopplus_seller/features/auth/widgets/zipcodeSelectionDialog.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customDropDownContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/dottedLineRectPainter.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:open_filex/open_filex.dart';
import '../../../commons/blocs/storesCubit.dart';
import '../../../commons/models/store.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customTextFieldContainer.dart';

class SignupPage2 extends StatefulWidget {
  bool isEditProfileScreen;
  Map<String, TextEditingController> controllers;
  Map<String, dynamic> files;
  Map<String, FocusNode> focusNodes;
  int? selectedStore;
  Function? callback;
  AllStoresCubit allStoresCubit;
  final Map<String, String> selectedZipcodeCity;
  SignupPage2(
      {super.key,
      required this.controllers,
      required this.focusNodes,
      required this.files,
      this.selectedStore,
      this.callback,
      required this.isEditProfileScreen,
      required this.selectedZipcodeCity,
      required this.allStoresCubit});

  @override
  State<SignupPage2> createState() => _SignupPage2State();
}

class _SignupPage2State extends State<SignupPage2> {
  late BuildContext dialogContext;
  List<Store> storeList = [];
  late ZipcodeListCubit zipcodeCubit;
  late ZipcodeListCubit cityCubit;
  String otherDocumentsTitle = '';
  @override
  void initState() {
    super.initState();

    zipcodeCubit = ZipcodeListCubit();
    cityCubit = ZipcodeListCubit();

    if (widget.isEditProfileScreen) {
      otherDocumentsTitle = widget.allStoresCubit
          .getAllAllStores()
          .firstWhere((e) =>
              e.id ==
              context
                  .read<UserDetailsCubit>()
                  .getDefaultStoreOfUser(context)
                  .storeId)
          .noteForUploadOtherDocuments!;
    } else if (widget.selectedStore != null) {
      otherDocumentsTitle = widget.allStoresCubit
          .getAllAllStores()
          .firstWhere((e) => e.id == widget.selectedStore)
          .noteForUploadOtherDocuments!;
    }
    Future.delayed(Duration.zero, (() {
      zipcodeCubit.getZipcodeList(
          context,
          {
            ApiURL.storeIdApiKey:
                context.read<StoresCubit>().getDefaultStore().id.toString(),
          },
          true);
      cityCubit.getZipcodeList(
          context,
          {
            ApiURL.storeIdApiKey:
                context.read<StoresCubit>().getDefaultStore().id.toString(),
          },
          false);
    }));
  }

  @override
  void dispose() {
    zipcodeCubit.close();
    cityCubit.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<CategoryListCubit, CategoryListState>(
      listener: (context, state) {
        if (state is CategoryListFetchSuccess) {
          if (state.categoryList.isNotEmpty) {
            for (var category in state.categoryList) {
              if (widget.files[selectCategoryKey]
                  .contains(category.id.toString())) {
                widget.controllers[selectCategoryKey]!.text = widget
                        .controllers[selectCategoryKey]!.text.isEmpty
                    ? category.name
                    : '${widget.controllers[selectCategoryKey]!.text}, ${category.name}';
                if (category.children != null &&
                    category.children!.isNotEmpty) {
                  widget.controllers[selectCategoryKey]!.text = widget
                          .controllers[selectCategoryKey]!.text.isEmpty
                      ? category.name
                      : '${widget.controllers[selectCategoryKey]!.text}, ${category.name}';
                }
              }
            }
          }
        }
      },
      child: SingleChildScrollView(
        padding: !widget.isEditProfileScreen
            ? EdgeInsets.symmetric(
                horizontal: appContentHorizontalPadding,
                vertical: appContentVerticalSpace)
            : EdgeInsets.only(
                left: appContentHorizontalPadding,
                right: appContentHorizontalPadding,
                bottom: appContentVerticalSpace),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Utils.buildSignupHeader(context, storeDetailsKey),
            if (!widget.isEditProfileScreen)
              CustomTextFieldContainer(
                hintTextKey: selectStoreKey,
                textEditingController: widget.controllers[selectStoreKey]!,
                labelKey: selectStoreKey,
                textInputAction: TextInputAction.next,
                focusNode: AlwaysDisabledFocusNode(),
                isSetValidator: true,
                errmsg: selectStoreKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[storeNameKey]),
                suffixWidget: const Icon(Icons.arrow_drop_down),
                onTap: () {
                  Utils.openModalBottomSheet(
                          context,
                          staticContent: false,
                          storeSelectionWidget(widget.allStoresCubit,
                              context.read<CategoryListCubit>()))
                      .then((value) {});
                },
              ),
            CustomTextFieldContainer(
                hintTextKey: storeNameKey,
                textEditingController: widget.controllers[storeNameKey]!,
                labelKey: storeNameKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[storeNameKey],
                isSetValidator: true,
                errmsg: storeNameKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[storeUrlKey])),
            CustomTextFieldContainer(
                hintTextKey: storeUrlKey,
                textEditingController: widget.controllers[storeUrlKey]!,
                labelKey: storeUrlKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[storeUrlKey],
                keyboardType: TextInputType.url,
                isFieldValueMandatory: false,
                isSetValidator: false,
                validator: (value) {
                  return Validator.validateUrl(value, context);
                },
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[storeDescKey])),
            CustomTextFieldContainer(
                hintTextKey: storeDescKey,
                textEditingController: widget.controllers[storeDescKey]!,
                labelKey: storeDescKey,
                keyboardType: TextInputType.multiline,
                minLines: 3, // Display 3 lines initially
                maxLines:
                    5, // Allow the field to expand as more lines are added
                textInputAction: TextInputAction.newline,
                focusNode: widget.focusNodes[storeDescKey],
                isFieldValueMandatory: true,
                isSetValidator: true,
                errmsg: storeDescKey,
                onFieldSubmitted: (v) =>
                    widget.focusNodes[storeDescKey]!.unfocus()),
            if (!widget.isEditProfileScreen) categoryListWidget(),
            zipcodeCityWidget(),
            DesignConfig.smallHeightSizedBox,
            CustomTextContainer(
              textKey: zoneSelectionNoteKey,
              style: Theme.of(context)
                  .textTheme
                  .bodyMedium!
                  .copyWith(color: Theme.of(context).colorScheme.error),
            ),
            CustomDropDownContainer(
                labelKey: deliverableTypeKey,
                dropDownDisplayLabels: sellerDeliverableTypes.values.toList(),
                selectedValue: widget.controllers[deliverableTypeKey]!.text,
                onChanged: (value) {
                  setState(() {
                    widget.controllers[deliverableTypeKey]!.text =
                        value.toString();
                  });
                },
                values: sellerDeliverableTypes.keys.toList()),
            if (isSelectZipCode(widget.controllers[deliverableTypeKey]!.text))
              zoneSelectionWidget(),
            if (!widget.isEditProfileScreen) ...[
              Utils.buildImageUploadWidget(
                  context: context,
                  labelKey: storeLogoKey,
                  file: widget.files[storeLogoKey],
                  onTapUpload: () =>
                      Utils.openFileExplorer(fileType: FileType.image)
                          .then((value) {
                        if (value != null) {
                          setState(() {
                            widget.files[storeLogoKey] = value.first;
                          });
                        }
                      }),
                  onTapClose: () {
                    setState(() {
                      widget.files[storeLogoKey] = null;
                    });
                  }),
              Utils.buildImageUploadWidget(
                  context: context,
                  labelKey: storeThumbnailKey,
                  file: widget.files[storeThumbnailKey],
                  onTapUpload: () =>
                      Utils.openFileExplorer(fileType: FileType.image)
                          .then((value) {
                        if (value != null) {
                          setState(() {
                            widget.files[storeThumbnailKey] = value.first;
                          });
                        }
                      }),
                  onTapClose: () {
                    setState(() {
                      widget.files[storeThumbnailKey] = null;
                    });
                  }),
              Utils.buildImageUploadWidget(
                  context: context,
                  labelKey: addressProofKey,
                  file: widget.files[addressProofKey],
                  onTapUpload: () =>
                      Utils.openFileExplorer(fileType: FileType.image)
                          .then((value) {
                        if (value != null) {
                          setState(() {
                            widget.files[addressProofKey] = value.first;
                          });
                        }
                      }),
                  onTapClose: () {
                    setState(() {
                      widget.files[addressProofKey] = null;
                    });
                  }),
            ],
            DesignConfig.smallHeightSizedBox,
            GestureDetector(
              onTap: () {
                Utils.openFileExplorer(isMultiple: true).then((value) async {
                  if (value != null) {
                    for (int i = 0; i < value.length; i++) {
                      if (widget.files['recent_doc'] == null) {
                        widget.files['recent_doc'] = [];
                      }
                      widget.files['recent_doc'].add(value[i]);

                      setState(() {});
                    }
                  }
                });
              },
              child: CustomPaint(
                  painter: DottedLineRectPainter(
                    strokeWidth: 1.0,
                    radius: 3.0,
                    dashWidth: 4.0,
                    dashSpace: 2.0,
                    color: Theme.of(context).colorScheme.secondary,
                  ),
                  child: Container(
                    alignment: Alignment.center,
                    width: double.maxFinite,
                    padding: EdgeInsets.symmetric(horizontal: 4, vertical: 6),
                    decoration: BoxDecoration(
                        color: Theme.of(context)
                            .colorScheme
                            .secondary
                            .withValues(alpha: 0.10),
                        borderRadius: BorderRadius.circular(3)),
                    child: CustomTextContainer(
                      textKey: otherDocumentsTitle.isNotEmpty
                          ? '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: uploadKey)} $otherDocumentsTitle'
                          : uploadOtherDocumentsKey,
                      style: Theme.of(context).textTheme.titleSmall!.copyWith(
                          color: Theme.of(context).colorScheme.secondary),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  )),
            ),
            DesignConfig.smallHeightSizedBox,
            if (widget.files[otherDocumentsKey] != null ||
                widget.files['recent_doc'] != null)
              Column(
                children: ((widget.files['recent_doc'] ?? []) +
                        (widget.files[otherDocumentsKey] ?? []))
                    .map<Widget>((e) => GestureDetector(
                          onTap: () {
                            //if the file is a string then it will open the url in browser
                            //else if it is a file then it will open the file in file explorer
                            if (e is String) {
                              Utils.launchURL(e);
                            } else if (e is File) {
                              OpenFilex.open(e.path);
                            }
                          },
                          child: Container(
                            margin: EdgeInsetsDirectional.only(bottom: 4),
                            padding: EdgeInsets.symmetric(
                                horizontal: 4, vertical: 4),
                            decoration: BoxDecoration(
                                color: Theme.of(context)
                                    .colorScheme
                                    .primary
                                    .withValues(alpha: 0.10),
                                borderRadius: BorderRadius.circular(3)),
                            child: Row(
                              children: <Widget>[
                                Expanded(
                                  child: CustomTextContainer(
                                    textKey: e is String
                                        ? e.split('/').last
                                        : (e as File).path.split('/').last,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleSmall!
                                        .copyWith(
                                            color: Theme.of(context)
                                                .colorScheme
                                                .primary),
                                  ),
                                ),
                                //we will use this icon button to remove the file from list and it will only work for recently uploaded files
                                //not for already uploaded files
                                if (e is File)
                                  IconButton(
                                      visualDensity: VisualDensity(
                                          horizontal: -4, vertical: -4),
                                      onPressed: () {
                                        setState(() {
                                          widget.files['recent_doc'].remove(e);
                                        });
                                      },
                                      icon: Icon(
                                        Icons.delete,
                                        color:
                                            Theme.of(context).colorScheme.error,
                                      ))
                              ],
                            ),
                          ),
                        ))
                    .toList(),
              ),
            Utils.buildSignupHeader(context, bankDetailsKey),
            CustomTextFieldContainer(
                hintTextKey: bankNameKey,
                textEditingController: widget.controllers[bankNameKey]!,
                labelKey: bankNameKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[bankNameKey],
                isSetValidator: true,
                errmsg: bankNameKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[bankCodeKey])),
            CustomTextFieldContainer(
                hintTextKey: bankCodeKey,
                textEditingController: widget.controllers[bankCodeKey]!,
                labelKey: bankCodeKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[bankCodeKey],
                isSetValidator: true,
                errmsg: bankCodeKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[accountNameKey])),
            CustomTextFieldContainer(
                hintTextKey: accountNameKey,
                textEditingController: widget.controllers[accountNameKey]!,
                labelKey: accountNameKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[accountNameKey],
                isSetValidator: true,
                errmsg: accountNameKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[accountNumberKey])),
            CustomTextFieldContainer(
                hintTextKey: accountNumberKey,
                textEditingController: widget.controllers[accountNumberKey]!,
                labelKey: accountNumberKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[accountNumberKey],
                isSetValidator: true,
                errmsg: accountNumberKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[taxNameKey])),
            Utils.buildSignupHeader(context, otherDetailsKey),
            CustomTextFieldContainer(
                hintTextKey: taxNameKey,
                textEditingController: widget.controllers[taxNameKey]!,
                labelKey: taxNameKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[taxNameKey],
                isFieldValueMandatory: false,
                errmsg: taxNameKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[taxNumberKey])),
            CustomTextFieldContainer(
                hintTextKey: taxNumberKey,
                textEditingController: widget.controllers[taxNumberKey]!,
                labelKey: taxNumberKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[taxNumberKey],
                isFieldValueMandatory: false,
                errmsg: taxNumberKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[panNumberKey])),
            CustomTextFieldContainer(
                hintTextKey: panNumberKey,
                textEditingController: widget.controllers[panNumberKey]!,
                labelKey: panNumberKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[panNumberKey],
                isFieldValueMandatory: false,
                errmsg: panNumberKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[latitudeKey])),
            CustomTextFieldContainer(
                hintTextKey: latitudeKey,
                textEditingController: widget.controllers[latitudeKey]!,
                labelKey: latitudeKey,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[latitudeKey],
                isFieldValueMandatory: false,
                errmsg: latitudeKey,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[longitudeKey])),
            CustomTextFieldContainer(
                hintTextKey: longitudeKey,
                textEditingController: widget.controllers[longitudeKey]!,
                labelKey: longitudeKey,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.done,
                focusNode: widget.focusNodes[longitudeKey],
                isFieldValueMandatory: false,
                errmsg: longitudeKey,
                onFieldSubmitted: (v) =>
                    widget.focusNodes[longitudeKey]!.unfocus()),
          ],
        ),
      ),
    );
  }

  BlocBuilder<ZoneListCubit, ZoneListState> zoneSelectionWidget() {
    return BlocBuilder<ZoneListCubit, ZoneListState>(
      builder: (context, state) {
        if (state is ZoneListFetchSuccess) {
          if (!widget.files.containsKey(selectZonesKey)) {
            widget.files[selectZonesKey] = {};
          }
          return CustomTextFieldContainer(
            hintTextKey: selectZonesKey,
            textEditingController: widget.controllers[selectZonesKey]!,
            labelKey: selectZonesKey,
            textInputAction: TextInputAction.next,
            isFieldValueMandatory: true,
            focusNode: AlwaysDisabledFocusNode(),
            isSetValidator: true,
            errmsg: selectZonesKey,
            suffixWidget: const Icon(Icons.arrow_drop_down),
            maxLines: 3,
            minLines: 1,
            onTap: () {
              if (state.brandList.isEmpty) return;
              showDialog(
                context: context,
                barrierDismissible: false,
                builder: (BuildContext mcontext) {
                  dialogContext = mcontext;
                  return AlertDialog(
                    insetPadding:
                        const EdgeInsets.all(appContentHorizontalPadding),
                    backgroundColor: white,
                    contentPadding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    shape: DesignConfig.setRoundedBorder(white, 10, false),
                    content: ZoneSelectionDialog(
                      selectedId: Map<String, String>.from(
                          widget.files[selectZonesKey]),
                      onZoneSelect: (Map<String, String> ids) {
                        widget.files[selectZonesKey].clear();
                        widget.files[selectZonesKey].addAll(ids);
                        setState(() {
                          widget.controllers[selectZonesKey]!.text =
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

  BlocBuilder<CategoryListCubit, CategoryListState> categoryListWidget() {
    return BlocBuilder<CategoryListCubit, CategoryListState>(
      builder: (context, state) {
        if (state is CategoryListFetchSuccess) {
          return CustomTextFieldContainer(
            hintTextKey: selectCategoryKey,
            textEditingController: widget.controllers[selectCategoryKey]!,
            labelKey: selectCategoryKey,
            textInputAction: TextInputAction.next,
            onFieldSubmitted: null,
            focusNode: AlwaysDisabledFocusNode(),
            isSetValidator: true,
            errmsg: selectCategoryKey,
            maxLines: 3,
            minLines: 1,
            suffixWidget: const Icon(Icons.arrow_drop_down),
            onTap: () {
              if (state.categoryList.isEmpty) return;
              showDialog(
                context: context,
                barrierDismissible: false,
                builder: (BuildContext mcontext) {
                  dialogContext = mcontext;

                  return AlertDialog(
                    insetPadding:
                        const EdgeInsets.all(appContentHorizontalPadding),
                    backgroundColor: white,
                    contentPadding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    shape: DesignConfig.setRoundedBorder(white, 10, false),
                    content: CategorySelectionDialog(
                        categorylist: state.categoryList,
                        selectedIds: widget.files[selectCategoryKey],
                        isMultiSelect: true,
                        onCategorySelect: (List<Category> categories) {
                          widget.files[selectCategoryKey].clear();
                          widget.controllers[selectCategoryKey]!.clear();

                          for (var category in categories) {
                            widget.files[selectCategoryKey]
                                .add(category.id.toString());
                            setState(() {
                              widget
                                  .controllers[selectCategoryKey]!.text = widget
                                      .controllers[selectCategoryKey]!
                                      .text
                                      .isEmpty
                                  ? category.name
                                  : '${widget.controllers[selectCategoryKey]!.text}, ${category.name}';
                            });
                          }
                          Navigator.pop(dialogContext);
                        }),
                  );
                },
              );
            },
          );
        }
        if (state is CategoryListFetchFailure) {
          return CustomTextContainer(
            textKey: state.errorMessage,
            style: Theme.of(context)
                .textTheme
                .bodyMedium!
                .copyWith(color: Theme.of(context).colorScheme.error),
          );
        } else {
          return const SizedBox.shrink();
        }
      },
    );
  }

  storeSelectionWidget(
      AllStoresCubit allStoresCubit, CategoryListCubit categoryListCubit) {
    return BlocBuilder<AllStoresCubit, AllStoresState>(
        bloc: allStoresCubit,
        builder: (context, state) {
          if (state is AllStoresFetchSuccess) {
            return FilterContainerForBottomSheet(
              title: selectStoreKey,
              borderedButtonTitle: "",
              primaryButtonTitle: "",
              isFilterButton: false,
              borderedButtonOnTap: () {},
              primaryButtonOnTap: () {},
              content: SizedBox(
                width: double.maxFinite,
                child: Column(
                    children: List.generate(state.stores.length, (index) {
                  Store store = state.stores[index];
                  return ListTile(
                    onTap: () {
                      widget.selectedStore = store.id;
                      widget.controllers[selectStoreKey]!.text = store.name;
                      if (widget.callback != null) {
                        if (!widget.isEditProfileScreen) {
                          categoryListCubit.getCategoryList(
                              context,
                              {
                                ApiURL.storeIdApiKey: store.id.toString(),
                              },
                              getAllCategories: true);
                          widget.controllers[selectCategoryKey]!.clear();
                          widget.files[selectCategoryKey].clear();
                        }
                        widget.callback!(store.id);
                        otherDocumentsTitle = widget.allStoresCubit
                            .getAllAllStores()
                            .firstWhere((e) => e.id == widget.selectedStore)
                            .noteForUploadOtherDocuments!;
                      }
                      Navigator.of(context).pop();
                    },
                    title: Text(store.name),
                    trailing: widget.selectedStore != null &&
                            store.id == widget.selectedStore
                        ? const Icon(Icons.check)
                        : null,
                  );
                })),
              ),
            );
          }

          if (state is StoresFetchFailure) {
            return ErrorScreen(
                buttonText: retryKey,
                onPressed: () =>
                    context.read<AllStoresCubit>().fetchAllStores(context));
          }
          return Center(
            child: CustomCircularProgressIndicator(
              indicatorColor: Theme.of(context).colorScheme.primary,
            ),
          );
        });
  }

  zipcodeCityWidget() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        DesignConfig.smallHeightSizedBox,
        CustomTextContainer(
          textKey: cityZipcodeSelectionNoteKey,
          style: Theme.of(context)
              .textTheme
              .bodyMedium!
              .copyWith(color: Theme.of(context).colorScheme.error),
        ),
        BlocBuilder<ZipcodeListCubit, ZipcodeListState>(
          bloc: cityCubit,
          builder: (context, state) {
            if (state is ZipcodeListFetchSuccess) {
              return CustomTextFieldContainer(
                hintTextKey: selectCityKey,
                textEditingController: widget.controllers[selectCityKey]!,
                labelKey: selectCityKey,
                textInputAction: TextInputAction.next,
                focusNode: AlwaysDisabledFocusNode(),
                isSetValidator: true,
                errmsg: selectCityKey,
                suffixWidget: const Icon(Icons.arrow_drop_down),
                onTap: () {
                  if (state.zipcodeList.isEmpty) return;
                  showDialog(
                    context: context,
                    barrierDismissible: false,
                    builder: (BuildContext mcontext) {
                      dialogContext = mcontext;
                      return AlertDialog(
                        insetPadding:
                            const EdgeInsets.all(appContentHorizontalPadding),
                        backgroundColor: white,
                        contentPadding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 5),
                        shape: DesignConfig.setRoundedBorder(white, 10, false),
                        content: ZipcodeSelectionDialog(
                          selectedId:
                              widget.selectedZipcodeCity[selectCityKey] ?? "",
                          isFetchZipcode: false,
                          onZipcodeSelect: (Map<String, String> ids) {
                            widget.selectedZipcodeCity[selectCityKey] =
                                ids.keys.first;
                            setState(() {
                              widget.controllers[selectCityKey]!.text =
                                  ids.values.first;
                            });

                            Navigator.pop(dialogContext);
                          },
                          zipcodeListCubit: cityCubit,
                        ),
                      );
                    },
                  );
                },
              );
            } else if (state is ZipcodeListFetchFailure) {
              return Utils.msgWithTryAgain(
                  context,
                  'City not found',
                  () => zipcodeCubit.getZipcodeList(
                      context,
                      {
                        ApiURL.storeIdApiKey: context
                            .read<StoresCubit>()
                            .getDefaultStore()
                            .id
                            .toString(),
                      },
                      false,
                      isSetInitial: true));
            } else {
              return const SizedBox.shrink();
            }
          },
        ),
        BlocBuilder<ZipcodeListCubit, ZipcodeListState>(
          bloc: zipcodeCubit,
          builder: (context, state) {
            if (state is ZipcodeListFetchSuccess) {
              return CustomTextFieldContainer(
                hintTextKey: selectZipCodeKey,
                textEditingController: widget.controllers[selectZipCodeKey]!,
                labelKey: selectZipCodeKey,
                textInputAction: TextInputAction.next,
                focusNode: AlwaysDisabledFocusNode(),
                isSetValidator: true,
                errmsg: selectZipCodeKey,
                suffixWidget: const Icon(Icons.arrow_drop_down),
                onTap: () {
                  if (state.zipcodeList.isEmpty) return;
                  showDialog(
                    context: context,
                    barrierDismissible: false,
                    builder: (BuildContext mcontext) {
                      dialogContext = mcontext;
                      return AlertDialog(
                        insetPadding:
                            const EdgeInsets.all(appContentHorizontalPadding),
                        backgroundColor: white,
                        contentPadding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 5),
                        shape: DesignConfig.setRoundedBorder(white, 10, false),
                        content: ZipcodeSelectionDialog(
                          selectedId:
                              widget.selectedZipcodeCity[selectZipCodeKey] ??
                                  "",
                          isFetchZipcode: true,
                          onZipcodeSelect: (Map<String, String> ids) {
                            widget.selectedZipcodeCity[selectZipCodeKey] =
                                ids.keys.first;
                            setState(() {
                              widget.controllers[selectZipCodeKey]!.text =
                                  ids.values.first;
                            });
                            Navigator.pop(dialogContext);
                          },
                          zipcodeListCubit: zipcodeCubit,
                        ),
                      );
                    },
                  );
                },
              );
            } else if (state is ZipcodeListFetchFailure) {
              return CustomTextContainer(
                textKey: noZipcodeFoundKey,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              );
            } else if (state is ZipcodeListFetchProgress) {
              return CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.primary);
            } else {
              return const SizedBox.shrink();
            }
          },
        ),
      ],
    );
  }
}
