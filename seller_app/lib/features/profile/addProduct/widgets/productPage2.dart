import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_seller/commons/models/product.dart';
import 'package:value_market_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_seller/commons/widgets/customDropDownContainer.dart';
import 'package:value_market_seller/commons/widgets/errorScreen.dart';

import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/configs/appConfig.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';

import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/features/profile/addProduct/widgets/aiPromptField.dart';
import 'package:value_market_seller/utils/inputValidators.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/routes/routes.dart';
import '../blocs/mediaListCubit.dart';
import '../blocs/taxlistCubit.dart';
import '../../../../commons/blocs/zoneListCubit.dart';
import '../../pickupLocation/blocs/getPickupLocationCubit.dart';
import '../../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../pickupLocation/models/location.dart';
import '../../../../../utils/designConfig.dart';
import '../../../../core/theme/colors.dart';
import '../../../../commons/widgets/customLabelContainer.dart';
import '../../../../commons/widgets/customTextFieldContainer.dart';
import '../screens/addProductScreen.dart';
import 'helper/helperWidgets.dart';
import 'helper/shippingSelectionDialog.dart';
import 'helper/taxSelectionDialog.dart';
import 'helper/zoneSelectionDialog.dart';

class ProductPage2 extends StatefulWidget {
  Map<String, TextEditingController> controllers;
  final Map<String, TextEditingController> nameControllers;
  Map<String, dynamic> files = {};
  Map<String, FocusNode> focusNodes;
  Map<String, String> selectedTax;
  final Product? product;
  Map<String, String> deliverableTypes;
  ProductPage2({
    super.key,
    required this.controllers,
    required this.nameControllers,
    required this.selectedTax,
    required this.focusNodes,
    required this.files,
    required this.deliverableTypes,
    this.product,
  });

  @override
  State<ProductPage2> createState() => _ProductPage2State();
}

class _ProductPage2State extends State<ProductPage2> {
  late BuildContext dialogContext;
  Map<String, String?> zoneApiParams = {};
  @override
  void initState() {
    super.initState();

    context.read<TaxListCubit>().getTaxList(context, {
      ApiURL.storeIdApiKey:
          context.read<StoresCubit>().getDefaultStore().id.toString(),
    });
    zoneApiParams = {
      ApiURL.storeIdApiKey: context
          .read<UserDetailsCubit>()
          .getDefaultStoreOfUser(context)
          .storeId
          .toString(),
      //if the seller has set deliverable type to all then we'll remove seller id
      ApiURL.sellerIdApiKey: context
                  .read<UserDetailsCubit>()
                  .getDefaultStoreOfUser(context)
                  .deliverableType ==
              1
          ? null
          : context
              .read<UserDetailsCubit>()
              .getDefaultStoreOfUser(context)
              .sellerId
              .toString()
    };
    if (widget.controllers[deliverableTypeKey]!.text == '2') {
      callZoneApi();
    }
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(
        appContentHorizontalPadding,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          BlocBuilder<TaxListCubit, TaxListState>(
            builder: (context, state) {
              if (state is TaxListFetchSuccess) {
                return CustomTextFieldContainer(
                  hintTextKey: productTaxKey,
                  textEditingController: widget.controllers[productTaxKey]!,
                  labelKey: productTaxKey,
                  textInputAction: TextInputAction.next,
                  isFieldValueMandatory: false,
                  focusNode: AlwaysDisabledFocusNode(),
                  errmsg: productTaxKey,
                  suffixWidget: const Icon(Icons.arrow_drop_down),
                  onFieldSubmitted: (v) => FocusScope.of(context)
                      .requestFocus(widget.focusNodes[forStandardShippingKey]),
                  onTap: () {
                    if (state.taxList.isEmpty) return;
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
                          shape:
                              DesignConfig.setRoundedBorder(white, 10, false),
                          content: TaxSelectionDialog(
                            selectedtax: widget.selectedTax,
                            taxlist: state.taxList,
                            selectionCallback: (Map<String, String> ids) {
                              widget.selectedTax.clear();
                              widget.selectedTax.addAll(ids);
                              setState(() {
                                widget.controllers[productTaxKey]!.text =
                                    ids.values.join(", ");
                              });
                              Navigator.pop(dialogContext);
                            },
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
          ),
          HelperWidgets.taxWithSwitchWidget(context, taxIncludeInPriceKey,
              widget.controllers[taxIncludeInPriceKey]!.text == "1",
              changeCallback: (bool value) {
            widget.controllers[taxIncludeInPriceKey]!.text = value ? "1" : "0";
            setState(() {});
          }),
          const SizedBox(height: 5),
          if (widget.controllers[productTypeKey]!.text !=
              digitalProductType) ...[
            CustomTextFieldContainer(
                hintTextKey: minimumOrderQtyKey,
                textEditingController: widget.controllers[minimumOrderQtyKey]!,
                labelKey: minimumOrderQtyKey,
                isSetValidator: true,
                autofocus: false,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[minimumOrderQtyKey],
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[totalAllowedQtyKey])),
            CustomTextFieldContainer(
                hintTextKey: totalAllowedQtyKey,
                textEditingController: widget.controllers[totalAllowedQtyKey]!,
                labelKey: totalAllowedQtyKey,
                textInputAction: TextInputAction.next,
                keyboardType: TextInputType.number,
                focusNode: widget.focusNodes[totalAllowedQtyKey],
                isFieldValueMandatory: false,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[quantityStepSizeKey])),
            CustomTextFieldContainer(
                hintTextKey: quantityStepSizeKey,
                textEditingController: widget.controllers[quantityStepSizeKey]!,
                labelKey: quantityStepSizeKey,
                textInputAction: TextInputAction.next,
                keyboardType: TextInputType.number,
                focusNode: widget.focusNodes[quantityStepSizeKey],
                isSetValidator: true,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[deliverableTypeKey])),
            CustomDropDownContainer(
                labelKey: deliverableTypeKey,
                dropDownDisplayLabels: widget.deliverableTypes.values.toList(),
                selectedValue: widget.controllers[deliverableTypeKey]!.text,
                onChanged: (value) {
                  setState(() {
                    widget.controllers[deliverableTypeKey]!.text =
                        value.toString();
                  });
                  if (value == '2') {
                    callZoneApi();
                  }
                },
                values: widget.deliverableTypes.keys.toList()),
            if (isSelectZipCode(widget.controllers[deliverableTypeKey]!.text))
              BlocBuilder<ZoneListCubit, ZoneListState>(
                builder: (context, state) {
                  if (state is ZoneListFetchSuccess) {
                    if (!widget.files.containsKey(selectZonesKey)) {
                      widget.files[selectZonesKey] = {};
                    }
                    return CustomTextFieldContainer(
                      hintTextKey: selectZonesKey,
                      textEditingController:
                          widget.controllers[selectZonesKey]!,
                      labelKey: selectZonesKey,
                      textInputAction: TextInputAction.next,
                      isFieldValueMandatory: false,
                      focusNode: AlwaysDisabledFocusNode(),
                      isSetValidator: true,
                      errmsg: selectZonesKey,
                      maxLines: 3,
                      minLines: 1,
                      suffixWidget: const Icon(Icons.arrow_drop_down),
                      onTap: () {
                        if (state.brandList.isEmpty) return;
                        showDialog(
                          context: context,
                          barrierDismissible: false,
                          builder: (BuildContext mcontext) {
                            dialogContext = mcontext;
                            return AlertDialog(
                              insetPadding: const EdgeInsets.all(
                                  appContentHorizontalPadding),
                              backgroundColor: white,
                              contentPadding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 5),
                              shape: DesignConfig.setRoundedBorder(
                                  white, 10, false),
                              content: ZoneSelectionDialog(
                                params: zoneApiParams,
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
                  }
                  if (state is ZoneListFetchFailure) {
                    return ErrorScreen(
                      text: state.errorMessage,
                      child: state is ZoneListFetchProgress
                          ? CustomCircularProgressIndicator(
                              indicatorColor:
                                  Theme.of(context).colorScheme.primary,
                            )
                          : null,
                      onPressed: () {
                        callZoneApi();
                      },
                    );
                  }
                  if (state is ZoneListFetchProgress) {
                    return CustomCircularProgressIndicator(
                        indicatorColor: Theme.of(context).colorScheme.primary);
                  } else {
                    return const SizedBox.shrink();
                  }
                },
              ),
            BlocBuilder<GetPickupLocationCubit, GetPickupLocationState>(
              builder: (context, state) {
                if (state is GetPickupLocationSuccess) {
                  return CustomTextFieldContainer(
                    hintTextKey: forStandardShippingKey,
                    textEditingController:
                        widget.controllers[forStandardShippingKey]!,
                    labelKey: forStandardShippingKey,
                    textInputAction: TextInputAction.next,
                    focusNode: AlwaysDisabledFocusNode(),
                    isSetValidator: false,
                    isFieldValueMandatory: false,
                    suffixWidget: const Icon(Icons.arrow_drop_down),
                    errmsg: forStandardShippingKey,
                    onFieldSubmitted: (v) => FocusScope.of(context)
                        .requestFocus(widget
                            .focusNodes[minimumFreeDeliveryOrderQuantityKey]),
                    onTap: () {
                      if (state.locationList.isEmpty) return;
                      showDialog(
                        context: context,
                        barrierDismissible: false,
                        builder: (BuildContext mcontext) {
                          dialogContext = mcontext;
                          return AlertDialog(
                            insetPadding: const EdgeInsets.all(
                                appContentHorizontalPadding),
                            backgroundColor: white,
                            contentPadding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 5),
                            shape:
                                DesignConfig.setRoundedBorder(white, 10, false),
                            content: ShippingSelectionDialog(
                                locationListCubit:
                                    context.read<GetPickupLocationCubit>(),
                                selectedPickupLocation:
                                    widget.files[forStandardShippingKey],
                                onShippingSelect: (Location location) {
                                  if (widget.files[forStandardShippingKey] !=
                                      location.pickupLocation.toString()) {
                                    widget.files[forStandardShippingKey] =
                                        location.pickupLocation.toString();
                                    setState(() {
                                      widget
                                          .controllers[forStandardShippingKey]!
                                          .text = location.pickupLocation ?? '';
                                    });
                                  }
                                  Navigator.pop(dialogContext);
                                }),
                          );
                        },
                      );
                    },
                  );
                } else {
                  return const SizedBox.shrink();
                }
              },
            ),
            CustomTextFieldContainer(
                hintTextKey: minimumFreeDeliveryOrderQuantityKey,
                textEditingController:
                    widget.controllers[minimumFreeDeliveryOrderQuantityKey]!,
                labelKey: minimumFreeDeliveryOrderQuantityKey,
                textInputAction: TextInputAction.next,
                focusNode:
                    widget.focusNodes[minimumFreeDeliveryOrderQuantityKey],
                keyboardType: TextInputType.number,
                isFieldValueMandatory: false,
                onFieldSubmitted: (v) => FocusScope.of(context)
                    .requestFocus(widget.focusNodes[deliveryChargesKey])),
            CustomTextFieldContainer(
                hintTextKey: deliveryChargesKey,
                textEditingController: widget.controllers[deliveryChargesKey]!,
                labelKey: deliveryChargesKey,
                textInputAction: TextInputAction.next,
                focusNode: widget.focusNodes[deliveryChargesKey],
                keyboardType: TextInputType.number,
                isFieldValueMandatory: false,
                onFieldSubmitted: (v) => FocusScope.of(context).requestFocus(
                    widget.focusNodes[cancelableTillWhichStatusKey])),
            const SizedBox(height: 8),
            confirmationWidget(),
            if (widget.controllers[isCancelableKey]!.text == "1")
              CustomDropDownContainer(
                  labelKey: cancelableTillWhichStatusKey,
                  dropDownDisplayLabels: cancelableStatusTypes.values.toList(),
                  selectedValue:
                      widget.controllers[cancelableTillWhichStatusKey]!.text,
                  onChanged: (value) {
                    setState(() {
                      widget.controllers[cancelableTillWhichStatusKey]!.text =
                          value.toString();
                    });
                  },
                  values: cancelableStatusTypes.keys.toList()),
            HelperWidgets.taxWithSwitchWidget(context, isAttachmentRequiredKey,
                widget.controllers[isAttachmentRequiredKey]!.text == "1",
                isHorizontal: true, changeCallback: (bool value) {
              widget.controllers[isAttachmentRequiredKey]!.text =
                  value ? "1" : "0";
              setState(() {});
            }),
          ],
          imageWidget(),
          videoWidget(),
          DesignConfig.defaultHeightSizedBox,
          if (context
                  .read<SettingsAndLanguagesCubit>()
                  .getSettings()
                  .systemSettings!
                  .AISetting !=
              null)
            AiPromptField(
              productNameController:
                  widget.nameControllers[defaultLanguageCode]!,
              descriptionController: widget.controllers[descriptionKey]!,
              selectedLanguage: 'English',
              isShortDescription: false,
              callback: (value) => setState(() {
                widget.controllers[descriptionKey]!.text = '<p>$value</p>';
              }),
            ),
          InkWell(
            onTap: () {
              FocusScope.of(context).unfocus();
              Utils.navigateToScreen(context, Routes.htmlEditorPage,
                  arguments: {
                    "callback": (String value) {
                      // Save HTML directly
                      widget.controllers[descriptionKey]!.text = value;
                    },
                    "currText": widget.controllers[descriptionKey]!.text,
                    "title": descriptionKey,
                  });
            },
            child: htmlWidget(
                descriptionKey, widget.controllers[descriptionKey]!.text),
          ),
          if (!isAddEditComboProduct) ...[
            DesignConfig.defaultHeightSizedBox,
            if (context
                    .read<SettingsAndLanguagesCubit>()
                    .getSettings()
                    .systemSettings!
                    .AISetting !=
                null)
              AiPromptField(
                productNameController:
                    widget.nameControllers[defaultLanguageCode]!,
                descriptionController: widget.controllers[extraDescKey]!,
                selectedLanguage: 'English',
                isShortDescription: false,
                callback: (value) => setState(() {
                  widget.controllers[extraDescKey]!.text = '<p>$value</p>';
                }),
              ),
            InkWell(
              onTap: () {
                FocusScope.of(context).unfocus();
                Utils.navigateToScreen(context, Routes.htmlEditorPage,
                    arguments: {
                      "callback": (String value) async {
                        // Save HTML directly
                        widget.controllers[extraDescKey]!.text = value;

                        // Optional: Update local controller if needed
                      },
                      "currText": widget.controllers[extraDescKey]!.text,
                      "title": extraDescKey,
                    });
              },
              child: htmlWidget(
                  extraDescKey, widget.controllers[extraDescKey]!.text),
            ),
          ]
        ],
      ),
    );
  }

  htmlWidget(String title, String currentText) {
    return Column(
      children: [
        CustomLabelContainer(
          textKey: title,
          isFieldValueMandatory: isAddEditComboProduct ? true : false,
        ),
        const SizedBox(
          height: 10,
        ),
        Container(
          decoration: BoxDecoration(
              color: Colors.white,
              border: Border.all(color: borderColor.withValues(alpha: 0.4)),
              borderRadius: BorderRadius.circular(borderRadius)),
          padding: const EdgeInsets.fromLTRB(appContentHorizontalPadding, 1,
              appContentHorizontalPadding, appContentHorizontalPadding),
          child: IgnorePointer(
              child: ClipRRect(
            borderRadius: const BorderRadius.all(Radius.circular(borderRadius)),
            clipBehavior: Clip.antiAliasWithSaveLayer,
            child: SizedBox(
                width: double.maxFinite,
                height: 150,
                child: Utils.buildDescription(currentText)),
          )),
        ),
      ],
    );
  }

  videoWidget() {
    videoMediaCubit ??= MediaListCubit();

    return Column(
      children: [
        CustomDropDownContainer(
            labelKey: selectVideoTypeKey,
            isFieldValueMandatory: false,
            dropDownDisplayLabels: videoTypes.values.toList(),
            selectedValue: widget.controllers[selectVideoTypeKey]!.text,
            onChanged: (value) {
              setState(() {
                widget.controllers[selectVideoTypeKey]!.text = value!;
              });
            },
            values: videoTypes.keys.toList()),
        videoTypeWidget(),
      ],
    );
  }

  imageWidget() {
    imageMediaCubit ??= MediaListCubit();

    return Column(
      children: [
        Utils.buildImageUploadWidget(
            context: context,
            labelKey: mainImageKey,
            file: null,
            imgurl: widget.controllers[mainImageKey]!.text,
            onTapUpload: () {
              openImageMediaSelection(false);
            },
            onTapClose: () {
              widget.controllers[mainImageKey]!.text = "";
              widget.files.remove(mainImageKey);
              setState(() {});
            }),
        SizedBox(
          height: MediaQuery.of(context).size.width * 0.6,
          child: ListView(
            scrollDirection: Axis.horizontal,
            children: <Widget>[
              Utils.buildImageUploadWidget(
                  context: context,
                  labelKey: otherImagesKey,
                  file: null,
                  isFieldValueMandatory: false,
                  onTapUpload: () {
                    openImageMediaSelection(true);
                  },
                  onTapClose: () {}),
              DesignConfig.smallWidthSizedBox,
              selectedOtherImageListWidgets(),
            ],
          ),
        )
      ],
    );
  }

  videoTypeWidget() {
    String selectedSelfHostedVideo = widget.controllers[selectVideoKey]!.text;
    if (widget.controllers[selectVideoTypeKey]!.text == vimeoType ||
        widget.controllers[selectVideoTypeKey]!.text == youtubeType) {
      return CustomTextFieldContainer(
        hintTextKey: videoLinkKey,
        textEditingController: widget.controllers[videoLinkKey]!,
        labelKey: videoLinkKey,
        textInputAction: TextInputAction.next,
        focusNode: widget.focusNodes[videoLinkKey],
        keyboardType: TextInputType.url,
        isSetValidator: true,
      );
    } else if (widget.controllers[selectVideoTypeKey]!.text == selfHostedType) {
      return Material(
        color: Colors.grey[300],
        child: ListTile(
            title: Text(selectedSelfHostedVideo.split("/").last),
            titleTextStyle: Theme.of(context).textTheme.bodySmall,
            dense: true,
            contentPadding: const EdgeInsetsDirectional.only(
                start: 8, top: 2, bottom: 2, end: 8),
            trailing: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(5.0),
                  ),
                ),
                child: Text(
                  context.read<SettingsAndLanguagesCubit>().getTranslatedValue(
                      labelKey: selectedSelfHostedVideo.trim().isEmpty
                          ? selectVideoKey
                          : deleteKey),
                  style: Theme.of(context)
                      .textTheme
                      .titleSmall!
                      .copyWith(color: Theme.of(context).colorScheme.primary),
                ),
                onPressed: () {
                  if (selectedSelfHostedVideo.trim().isEmpty) {
                    selectVideo();
                  } else {
                    widget.controllers[selectVideoKey]!.text = "";
                    widget.files[selectVideoKey] = "";
                  }
                  setState(() {});
                })),
      );
    } else {
      return const SizedBox.shrink();
    }
  }

  selectVideo() {
    if (videoMediaCubit!.state is! MediaListFetchSuccess) {
      videoMediaCubit!
          .getMediaList(context, {"type": mediaTypeVideo}, isSetInitial: true);
    } else {}
    Utils.navigateToScreen(context, Routes.mediaListScreen, arguments: {
      'mediaListCubit': videoMediaCubit,
      'mediaType': mediaTypeVideo,
      'isMultipleSelect': false,
      "onMediaSelect": (Map<String, String> path) {
        if (path.isNotEmpty) {
          widget.controllers[selectVideoKey]!.text = path.keys.first;
          widget.files[selectVideoKey] = path.values.first;
          setState(() {});
        }
      }
    });
  }

  selectedOtherImageListWidgets() {
    Map<String, String> imglist =
        Map<String, String>.from(widget.files[otherImagesKey] ?? {});

    Size size = MediaQuery.of(context).size;
    return ListView.separated(
      padding: EdgeInsets.only(top: 35),
      separatorBuilder: (context, index) => const SizedBox(width: 8),
      scrollDirection: Axis.horizontal,
      itemCount: imglist.values.length,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemBuilder: (context, index) {
        String key = imglist.keys.elementAt(index);
        String value = imglist[key] ?? "";
        return Stack(
          clipBehavior: Clip.none,
          children: [
            Container(
              width: size.width * 0.4,
              height: size.width * 0.4,
              decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(8),
                  image: DecorationImage(
                    image: NetworkImage(value),
                    fit: BoxFit.cover,
                  )),
            ),
            Positioned(
              right: 5,
              top: 5,
              child: GestureDetector(
                onTap: () {
                  imglist.remove(key);
                  widget.files[otherImagesKey] = imglist;
                  widget.controllers[otherImagesKey]!.text =
                      imglist.keys.join(",");
                  setState(() {});
                },
                child: Container(
                  height: 34,
                  width: 34,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                      color: Theme.of(context).scaffoldBackgroundColor,
                      shape: BoxShape.circle),
                  child: Icon(
                    Icons.close_rounded,
                    size: 18,
                    color: Theme.of(context).colorScheme.error,
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  openImageMediaSelection(bool ismultiimage) {
    if (imageMediaCubit!.state is! MediaListFetchSuccess) {
      imageMediaCubit!
          .getMediaList(context, {"type": mediaTypeImage}, isSetInitial: true);
    } else {}
    Utils.navigateToScreen(context, Routes.mediaListScreen, arguments: {
      'mediaListCubit': imageMediaCubit,
      'mediaType': mediaTypeImage,
      'isMultipleSelect': ismultiimage,
      "onMediaSelect": (Map<String, String> path) {
        if (path.isNotEmpty) {
          if (ismultiimage) {
            Map<String, String> imglist = widget.files[otherImagesKey] ?? {};
            imglist.addAll(path);
            widget.files[otherImagesKey] = imglist;
            widget.controllers[otherImagesKey]!.text = imglist.keys.join(",");
          } else {
            widget.files[mainImageKey] = path.keys.first;
            widget.controllers[mainImageKey]!.text = path.values.first;
          }
          setState(() {});
        }
      }
    });
  }

  confirmationWidget() {
    return Wrap(
      alignment: WrapAlignment.start,
      spacing: appContentHorizontalPadding,
      runSpacing: 8,
      children: [
        HelperWidgets.taxWithSwitchWidget(context, isCodAllowedKey,
            widget.controllers[isCodAllowedKey]!.text == "1",
            isHorizontal: false, changeCallback: (bool value) {
          widget.controllers[isCodAllowedKey]!.text = value ? "1" : "0";
          setState(() {});
        }),
        HelperWidgets.taxWithSwitchWidget(context, isReturnableKey,
            widget.controllers[isReturnableKey]!.text == "1",
            isHorizontal: false, changeCallback: (bool value) {
          widget.controllers[isReturnableKey]!.text = value ? "1" : "0";
          setState(() {});
        }),
        HelperWidgets.taxWithSwitchWidget(context, isCancelableKey,
            widget.controllers[isCancelableKey]!.text == "1",
            isHorizontal: false, changeCallback: (bool value) {
          widget.controllers[isCancelableKey]!.text = value ? "1" : "0";
          setState(() {});
        }),
      ],
    );
  }

  void callZoneApi() {
    context.read<ZoneListCubit>().getZoneList(context, zoneApiParams);
  }
}
