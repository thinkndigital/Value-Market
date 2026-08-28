import 'dart:convert';
import 'dart:developer';
import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/userDetailsCubit.dart';
import 'package:eshopplus_seller/commons/models/mainAttribute.dart';
import 'package:eshopplus_seller/commons/models/productVariant.dart';
import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/addProductCubit.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import '../blocs/brandListCubit.dart';
import '../blocs/categoryListCubit.dart';
import '../blocs/countryListCubit.dart';
import '../blocs/getProductByTypeCubit.dart';
import '../blocs/mediaListCubit.dart';
import '../../pickupLocation/blocs/getPickupLocationCubit.dart';
import '../../../../commons/blocs/storesCubit.dart';
import '../../../../commons/models/product.dart';
import '../../../../commons/models/store.dart';
import '../repositories/attributeRepository.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import '../../../../utils/utils.dart';
import '../../../../commons/widgets/customAppbar.dart';
import '../../../../commons/widgets/customBottomButtonContainer.dart';
import '../../../../commons/widgets/customRoundedButton.dart';
import '../../../mainScreen.dart';
import '../widgets/addProductConfirmation.dart';
import '../widgets/customStepper.dart';
import '../widgets/helper/helperWidgets.dart';
import '../widgets/productPage1.dart';
import '../widgets/productPage2.dart';
import '../widgets/productPage3.dart';

MediaListCubit? imageMediaCubit, videoMediaCubit;
late Map<String, dynamic> variations;
GlobalKey<FormState>? addProductFormKey;
late List<String> taglist;
bool isAddEditComboProduct = false;

class AddProductScreen extends StatefulWidget {
  final Product? product;
  final bool? isAddEditCombo;
  const AddProductScreen(
      {super.key, this.product, this.isAddEditCombo = false});

  static Widget getRouteInstance() {
    Map? arguments = Get.arguments;
    return BlocProvider(
      create: (context) => GetPickupLocationCubit(),
      child: AddProductScreen(
        isAddEditCombo:
            arguments != null && arguments.containsKey('isAddEditCombo')
                ? arguments["isAddEditCombo"]
                : false,
        product: arguments != null && arguments.containsKey('product')
            ? arguments['product']
            : null,
      ),
    );
  }

  @override
  _AddProductScreenState createState() => _AddProductScreenState();
}

class _AddProductScreenState extends State<AddProductScreen>
    with TickerProviderStateMixin {
  int _currentStep = 1;
  int _stepLength = 3;
  final List<int> steps = [1, 2, 3];
  Map<String, TextEditingController> controllers = {};
  Store? defaultstore;
  Map<String, dynamic> files = {};
  Map<String, FocusNode> focusNodes = {};
  Map<String, Map<String, dynamic>> selectedAttributes = {};
  Map<String, String> selectedTax = {};
  Map<String, Product> selectedSimilarProducts = {};
  Map<String, ProductVariant> selectedProducts = {};
  Map<String, TextEditingController> nameControllers = {};
  Map<String, TextEditingController> descControllers = {};

  Map<String, String> deliverableTypes = Map.from(productDeliverableTypes);
  final Map<String, String> apiFormField = {
    productNameKey: "pro_input_name",
    productSortDesKey: "short_description",
    tagsKey: "tags",
    productTypeKey: "product_type",
    productTaxKey: "pro_input_tax[]",
    madeInKey: "made_in",
    brandKey: "brand",
    totalAllowedQtyKey: "total_allowed_quantity",
    minimumOrderQtyKey: "minimum_order_quantity",
    quantityStepSizeKey: "quantity_step_size",
    deliverableTypeKey: "deliverable_type",
    selectZonesKey: "deliverable_zones[]",
    taxIncludeInPriceKey: "is_prices_inclusive_tax",
    isCodAllowedKey: "cod_allowed",
    isDownloadAllowedKey: "download_allowed",
    downloadLinkTypeKey: "download_link_type",
    selectDownloadableMediaKey: "pro_input_zip",
    digitalProductLinkKey: "download_link",
    isReturnableKey: "is_returnable",
    isCancelableKey: "is_cancelable",
    isAttachmentRequiredKey: "is_attachment_required",
    mainImageKey: "pro_input_image",
    otherImagesKey: "other_images",
    selectVideoTypeKey: "video_type",
    videoLinkKey: "video",
    selectVideoKey: "pro_input_video",
    descriptionKey: "pro_input_description",
    extraDescKey: "extra_input_description",
    selectCategoryKey: "category_id",
    attributesKey: "attribute_values",
    minimumFreeDeliveryOrderQuantityKey: "minimum_free_delivery_order_qty",
    deliveryChargesKey: "delivery_charges",
    forStandardShippingKey: "pickup_location",
    chooseStockMgmtTypeKey: "variant_stock_level_type",
    weightKey: "weight",
    heightKey: "height",
    breadthKey: "breadth",
    lengthKey: "length",
    SKUKey: "sku_variant_type",
    totalStockKey: "total_stock_variant_type",
    priceKey: "simple_price",
    specialPriceKey: "simple_special_price",
    stockStatusKey: "simple_product_stock_status",
    cancelableTillWhichStatusKey: "cancelable_till",
    enableStockManagementKey: "stock",
  };

  late final TabController _tabController;
  bool _canPop = false;
  @override
  void initState() {
    super.initState();
    variations = {};
    taglist = [];
    isAddEditComboProduct = widget.isAddEditCombo ?? false;
    StoreData currentStore =
        context.read<UserDetailsCubit>().getDefaultStoreOfUser(context);
    //if seller has set deliverable type including/ exluding then we'll remove all option here
    if (currentStore.deliverableType == 2) {
      deliverableTypes.remove('1');
    }
    for (var lang in context.read<SettingsAndLanguagesCubit>().getLanguages()) {
      nameControllers[lang.code!] = TextEditingController();
      descControllers[lang.code!] = TextEditingController();
    }
    if (isAddEditComboProduct) {
      apiFormField.remove(selectCategoryKey);
      apiFormField.remove(brandKey);
      apiFormField.remove(madeInKey);

      apiFormField[physicalProductKey] = "physical_product_variant_id";
      apiFormField[digitalProductKey] = "digital_product_id";
      apiFormField[hasSimilarProductsKey] = "has_similar_product";
      apiFormField[selectProductKey] = "similar_product_ids";
      apiFormField[productNameKey] = "title";
      apiFormField[descriptionKey] = "description";
      apiFormField[mainImageKey] = "image";
      apiFormField[productTypeKey] = "product_type_in_combo";

      apiFormField.remove(extraDescKey);
    }
    addProductFormKey = GlobalKey<FormState>();

    apiFormField.forEach((key, value) {
      controllers[key] = TextEditingController();
      focusNodes[key] = FocusNode();
    });

    _tabController = TabController(length: 3, vsync: this);
    controllers[isDownloadAllowedKey]!.text = "0";
    controllers[isCancelableKey]!.text = "0";
    controllers[isCodAllowedKey]!.text = "0";
    controllers[isReturnableKey]!.text = "0";
    controllers[isAttachmentRequiredKey]!.text = "0";
    controllers[taxIncludeInPriceKey]!.text = "0";
    controllers[deliverableTypeKey]!.text = deliverableTypes.keys.first;
    if (controllers.containsKey(indicatorKey)) {
      controllers[indicatorKey]!.text = indicatorTypes.keys.first;
    }
    controllers[productTypeKey]!.text = productTypes.keys.first;
    controllers[downloadLinkTypeKey]!.text = downloadLinkTypes.keys.first;
    controllers[selectVideoTypeKey]!.text = videoTypes.keys.first;

    controllers[cancelableTillWhichStatusKey]!.text =
        cancelableStatusTypes.entries.first.key;
    controllers[stockStatusKey]!.text = stockStatusTypes.keys.first;
    controllers[chooseStockMgmtTypeKey]!.text = stockMgmtTypes.keys.first;

    getData();
  }

  getData() async {
    Future.delayed(Duration.zero, () async {
      defaultstore = context.read<StoresCubit>().getDefaultStore();
      if (isAddEditComboProduct) {
        context.read<GetProductByTypeCubit>().getProductList(context, {
          ApiURL.storeIdApiKey: defaultstore!.id!.toString(),
          "type": physicalProductType
        });
        context.read<ProductsCubit>().getProducts(
              storeId: defaultstore!.id!,
              isComboProduct: true,
            );
      } else {
        context.read<CategoryListCubit>().getCategoryList(context, {
          ApiURL.storeIdApiKey: defaultstore!.id!.toString(),
        });

        context.read<BrandListCubit>().getBrandList(context, {
          ApiURL.storeIdApiKey: defaultstore!.id!.toString(),
        });
        context.read<CountryListCubit>().getCountryList(context, {
          ApiURL.storeIdApiKey: defaultstore!.id!.toString(),
        });
      }

      BlocProvider.of<GetPickupLocationCubit>(context).getPickupLocation(
          context,
          {
            ApiURL.storeIdApiKey: defaultstore!.id!.toString(),
            ApiURL.statusApiKey: '1' // pass 1 to get only verified locations
          },
          isSetInitial: true);

      if (widget.product != null) {
        await updateControllers();
      } else {
        setMapList();
      }
    });
  }

  setMapList() {
    Map<String, dynamic> currvariations = variations;
    variations = {};

    variations = HelperWidgets.generateVariationCombinations(
        selectedAttributes, currvariations);
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
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _canPop,
      onPopInvokedWithResult: (didPop, result) {
        if (!_canPop) {
          Utils.openAlertDialog(context,
              message: areYouSureKey,
              content: areYouSureToExitKey,
              yesLabel: exitKey, onTapNo: () {
            Navigator.of(context).pop();
          }, onTapYes: () {
            _canPop = true;
            Navigator.of(context).pop();
            Navigator.of(context).pop();
          });
        }
      },
      child: Scaffold(
        appBar: CustomAppbar(
          titleKey: widget.product != null ? editProductKey : addNewProductKey,
          onBackButtonTap: back,
        ),
        body: BlocListener<AddProductCubit, AddProductState>(
            listener: (context, state) {
              if (state is AddProductProgress) {
                Utils.showLoader(context);
              } else {
                Utils.hideLoader(context);
              }
              if (state is AddProductSuccess) {
                if (state.product.type == comboProductType) {
                  comboProductsCubit!.updateProductDetails(state.product);
                } else {
                  regularProductsCubit!.updateProductDetails(state.product);
                }
                Utils.showSnackBar(message: state.successMsg);
                Future.delayed(const Duration(milliseconds: 500), () {
                  _completeScreenAndPop();
                });
              } else if (state is AddProductFailure) {
                Utils.showSnackBar(message: state.errorMessage);
              }
            },
            child: buildBody()),
        bottomNavigationBar: buildNavigationButtons(),
      ),
    );
  }

  void _completeScreenAndPop() {
    // Before programmatically popping, set the flag to true
    // so the PopScope doesn't trigger the dialog.
    _canPop = true;
    Navigator.of(context).pop(); // Pops this screen
  }

  Widget buildNavigationButtons() {
    return CustomBottomButtonContainer(
        child: Row(
      children: <Widget>[
        if (_currentStep != 1) ...[
          Expanded(
            child: CustomRoundedButton(
              widthPercentage: 1,
              buttonTitle: backKey,
              showBorder: true,
              backgroundColor: Theme.of(context).colorScheme.onPrimary,
              borderColor: Theme.of(context).inputDecorationTheme.iconColor,
              style: Theme.of(context)
                  .textTheme
                  .titleMedium!
                  .copyWith(color: Theme.of(context).colorScheme.secondary),
              onTap: () {
                back();
              },
            ),
          ),
          DesignConfig.defaultWidthSizedBox,
        ],
        Expanded(
          child: CustomRoundedButton(
            widthPercentage: 1,
            buttonTitle: _currentStep <= _stepLength
                ? nextKey
                : widget.product == null
                    ? submitKey
                    : updateKey,
            showBorder: false,
            onTap: nextBtnPress,
          ),
        ),
      ],
    ));
  }

  buildBody() {
    return GestureDetector(
      onTap: () => FocusScope.of(context).unfocus(),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding, vertical: 16.0),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primaryContainer,
              boxShadow: const [
                BoxShadow(
                  color: Color(0x05000000),
                  blurRadius: 8,
                  offset: Offset(0, 2),
                  spreadRadius: 0,
                )
              ],
            ),
            child: CustomStepper(
              totalSteps: _stepLength,
              width: MediaQuery.of(context).size.width,
              curStep: _currentStep,
              stepCompleteColor: Theme.of(context).colorScheme.primary,
              currentStepColor: Theme.of(context).colorScheme.primary,
              inactiveColor: Theme.of(context).colorScheme.secondary,
              lineWidth: 2.0,
            ),
          ),
          const SizedBox(
            height: 12,
          ),
          Expanded(
            child: Container(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: Form(
                    key: addProductFormKey,
                    child: _currentStep == 1
                        ? ProductPage1(
                            controllers: controllers,
                            nameControllers: nameControllers,
                            descControllers: descControllers,
                            files: files,
                            selectedSimilarProducts: selectedSimilarProducts,
                            selectedProducts: selectedProducts,
                            focusNodes: focusNodes,
                            product: widget.product,
                            changeProductType: () {})
                        : _currentStep == 2
                            ? ProductPage2(
                                selectedTax: selectedTax,
                                controllers: controllers,
                                nameControllers: nameControllers,
                                files: files,
                                focusNodes: focusNodes,
                                product: widget.product,
                                deliverableTypes: deliverableTypes,
                              )
                            : _currentStep == 3
                                ? ProductPage3(
                                    controllers: controllers,
                                    selectedAttributes: selectedAttributes,
                                    focusNodes: focusNodes,
                                    tabController: _tabController,
                                    product: widget.product,
                                  )
                                : AddProductConfirmation(
                                    isEdit: widget.product != null))),
          )
        ],
      ),
    );
  }

  getUrl() async {
    ServiceProtocolInfo serviceProtocolInfo = await Service.getInfo();
    return serviceProtocolInfo.serverUri;
  }

  nextBtnPress() async {
    if (_currentStep <= _stepLength) {
      if (addProductFormKey!.currentState!.validate()) {
        if (_currentStep == 1 &&
            isAddEditComboProduct &&
            selectedProducts.isEmpty) {
          Utils.showSnackBar(message: selectProductKey);
          return;
        }
        if (_currentStep == 1 &&
            isAddEditComboProduct &&
            controllers[hasSimilarProductsKey]!.text == "1" &&
            selectedSimilarProducts.isEmpty) {
          Utils.showSnackBar(message: selectSimilarProductsKey);
          return;
        }
        if (_currentStep == 2 &&
            controllers[mainImageKey]!.text.trim().isEmpty) {
          Utils.showSnackBar(message: mainImageKey);
          return;
        }
        if (_currentStep == 2 &&
            isAddEditComboProduct &&
            controllers[descriptionKey]!.text.trim().isEmpty) {
          Utils.showSnackBar(message: descriptionKey);
          return;
        }
        if (_currentStep == 3 &&
            controllers[productTypeKey]!.text == variableProductType) {
          if (variations.isEmpty) {
            Utils.showSnackBar(message: addVariantWarningKey);
            return;
          }
        }
        if (_currentStep == 3 &&
            !isAddEditComboProduct &&
            variations.isNotEmpty) {
          if (variationValidation()) {
            return;
          }
        }

        goTo(_currentStep + 1);
      } else {
        Utils.showSnackBar(message: pleaseEnterRequiredFieldsKey);
      }
    } else {
      addProductProcess();
    }
  }

  variationValidation() {
    bool isError = false;
    List<String> vkeys = variations.keys.toList();
    for (int i = 0; i < variations.length; i++) {
      String key = vkeys[i];
      Map<String, dynamic> value = variations[key];
      if (!variations[key]['isDeleted']) {
        if (value[otherImagesKey].isEmpty) {
          Utils.showSnackBar(message: addVariantImagesKey);
          isError = true;
          break;
        }
        if ((value[priceKey].text.trim().isEmpty ||
            value[specialPriceKey].text.trim().isEmpty)) {
          Utils.showSnackBar(message: enterPriceKey);
          isError = true;
          break;
        } else if (controllers[chooseStockMgmtTypeKey]!.text ==
                variableLevelStockMgmtType &&
            (value[SKUKey].text.trim().isEmpty ||
                value[totalStockKey].text.trim().isEmpty)) {
          Utils.showSnackBar(message: totalStockKey);
          isError = true;
          break;
        } else {
          double price = double.parse(value[priceKey]!.text);
          double spprice = double.parse(value[specialPriceKey]!.text);
          if (spprice >= price) {
            Utils.showSnackBar(message: specialPriceErrMsgKey);

            isError = true;
            break;
          }
        }
      }
    }
    return isError;
  }

  back() {
    if (_currentStep > 1) {
      goTo(_currentStep - 1);
    } else {
      Utils.openAlertDialog(context,
          message: areYouSureKey,
          content: areYouSureToExitKey,
          yesLabel: exitKey, onTapNo: () {
        Navigator.of(context).pop();
      }, onTapYes: () {
        Navigator.of(context).pop();
        Navigator.of(context).pop();
      });
    }
  }

  goTo(int step) {
    setState(() => _currentStep = step);
    if (_currentStep > _stepLength) {}
  }

  addProductProcess() {
    Map<String, String> translatedNames = {};
    Map<String, String> translatedDescriptions = {};
    Map<String, dynamic> updatedVariants = Map.from(variations);

    nameControllers.forEach((lang, controller) {
      if (lang != "en") {
        translatedNames[lang] = controller.text.trim();
      }
    });

    descControllers.forEach((lang, controller) {
      if (lang != "en") {
        translatedDescriptions[lang] = controller.text.trim();
      }
    });
// Convert to JSON string for API
    String nameJson = jsonEncode(translatedNames);
    String descJson = jsonEncode(translatedDescriptions);

    Map<String, dynamic> params = {
      ApiURL.storeIdApiKey: defaultstore!.id!.toString(),
    };

    apiFormField.forEach((key, value) {
      params[value] = controllers[key]!.text;
    });
    params[apiFormField[productNameKey]!] =
        nameControllers[englishLangCode]!.text;
    params[apiFormField[productSortDesKey]!] =
        descControllers[englishLangCode]!.text;
    if (isAddEditComboProduct)
      params['translated_product_title'] = nameJson;
    else
      params['translated_product_name'] = nameJson;
    params['translated_product_short_description'] = descJson;
    //do not place files iteration first, because it replaces values of apiFormField iteration
    files.forEach(
      (key, value) {
        if (key == otherImagesKey) {
          params[apiFormField[otherImagesKey]!] = value.keys.join(",");
        } else if (key == selectZonesKey) {
          if ((files[selectZonesKey] ?? {} as Map<String, String>).isNotEmpty) {
            params[apiFormField[selectZonesKey]!] =
                (files[selectZonesKey]).keys.join(",");
          }
        } else if (key != selectVideoKey && apiFormField.containsKey(key)) {
          params[apiFormField[key]!] = value;
        }
      },
    );
    params[apiFormField[productTaxKey]!] = selectedTax.keys.join(",");
    List<String> currVarIds = [];
    // --- Custom Fields Logic Start ---
    if (defaultstore != null && defaultstore!.customFields.isNotEmpty) {
      for (final field in defaultstore!.customFields) {
        final fieldKey = 'custom_field_${field.id}';
        final paramBase = 'custom_fields[${field.id}][0][value]';
        if (field.type == 'checkbox') {
          // Multi-value
          final value = controllers[fieldKey]?.text ?? '';
          if (value.isNotEmpty) {
            final values = value.split(',');
            for (int i = 0; i < values.length; i++) {
              params['custom_fields[${field.id}][0][value][$i]'] = values[i];
            }
          }
        } else if (field.type == 'file') {
          // File fields: value is in files map
          if (files.containsKey(fieldKey) && files[fieldKey] != null) {
            params['custom_fields[${field.id}][][value]'] = files[fieldKey];
          }
        } else {
          // Single value fields
          final value = controllers[fieldKey]?.text ?? '';
          if (value.isNotEmpty) {
            params[paramBase] = value;
          }
        }
      }
    }
    // --- Custom Fields Logic End ---
    if (controllers[productTypeKey]!.text == simpleProductType) {
      if (controllers[enableStockManagementKey]!.text == "1") {
        params["product_sku"] = controllers[SKUKey]!.text;
        params["product_total_stock"] = controllers[totalStockKey]!.text;
        params["simple_product_stock_status"] =
            controllers[stockStatusKey]!.text;
      } else {
        params.remove("product_sku");
        params.remove("product_total_stock");
        params.remove("simple_product_stock_status");
      }
    } else if (controllers[productTypeKey]!.text == variableProductType) {
      //we'll not pass variant_stock_status this is add product
      if (widget.product != null) params['variant_stock_status'] = '0';
      params.remove("simple_product_stock_status");
      params.remove("simple_price");
      params.remove("simple_special_price");
      params.remove("product_total_stock");
      params.remove("product_sku");
      params.remove("sku_variant_type");
      params.remove("total_stock_variant_type");
      params.remove("variant_status");
      if (controllers[enableStockManagementKey]!.text.isEmpty ||
          controllers[enableStockManagementKey]!.text == "0") {
        params.remove("variant_stock_level_type");
      } else {
        if (controllers[chooseStockMgmtTypeKey]!.text ==
            productLevelStockMagmtType) {
          params["variant_status"] = controllers[stockStatusKey]!.text;
          params["total_stock_variant_type"] = controllers[totalStockKey]!.text;
          params["sku_variant_type"] = controllers[SKUKey]!.text;
        }
      }

      if (variations.isNotEmpty) {
        updatedVariants.removeWhere((key, value) => value["isDeleted"]);
        params["variants_ids"] = updatedVariants.keys.join(",");

        List price = [],
            spprice = [],
            imgs = [],
            weight = [],
            height = [],
            breadth = [],
            len = [],
            skuvarianttype = [],
            totalstocktype = [],
            stockstatus = [];
        updatedVariants.forEach((key, value) {
          currVarIds.add(key.replaceAll(" ", ","));
          if (!value["isDeleted"]) {
            price.add(value[priceKey].text);
            spprice.add(value[specialPriceKey].text);
            Map<String, String> imglist =
                Map<String, String>.from(value[otherImagesKey] ?? {});

            imgs.add(jsonEncode(imglist.keys.toList()));
            weight.add(value[weightKey].text);
            height.add(value[heightKey].text);
            breadth.add(value[breadthKey].text);
            len.add(value[lengthKey].text);
            skuvarianttype.add(value[SKUKey].text);
            totalstocktype.add(value[totalStockKey].text);
            stockstatus.add(value[stockStatusKey].text);
          }
        });

        params["variant_price"] = price.join(",");
        params["variant_special_price"] = spprice.join(",");
        params["variant_images"] = "[${imgs.join(",")}]";
        params["weight"] = weight.join(",");
        params["height"] = height.join(",");
        params["breadth"] = breadth.join(",");
        params["length"] = len.join(",");
        if (controllers[enableStockManagementKey]!.text == "1") {
          params["variant_sku"] = skuvarianttype.join(",");
          params["variant_total_stock"] = totalstocktype.join(",");
          params["variant_level_stock_status"] = stockstatus.join(",");
        }
      }
    }
    if (isAddEditComboProduct) {
      if (controllers[enableStockManagementKey]!.text == "1") {
        params["simple_stock_management_status"] = "on";
      } else {
        params["simple_stock_management_status"] = "off";
      }
    }

    List<String> varAttId = [];
    if (widget.product != null) {
      params['id'] = widget.product!.id.toString();
      params['product_id'] = widget.product!.id.toString();
      params['edit_product_id'] = widget.product!.id.toString();
      if (widget.product!.variants != null &&
          widget.product!.variants!.isNotEmpty) {
        varAttId.addAll(widget.product!.variants!.map(
          (e) {
            if (e.attributeValueIds != null &&
                e.attributeValueIds!.isNotEmpty) {
              List<int> targetParts = e.attributeValueIds!
                  .split(",")
                  .map(int.parse)
                  .toList()
                ..sort();
              return targetParts.join(",");
            }
            return "";
          },
        ).toList());
        params['edit_variant_id'] = widget.product!.variants!
            .where((e) => updatedVariants.containsKey(
                e.attributeValueIds!.split(',').toList().join(" ")))
            .map((element) => element.id)
            .toList()
            .join(",");
      }

      currVarIds.removeWhere(
        (element) => varAttId.contains(element),
      );

      if (currVarIds.isNotEmpty && params.containsKey("edit_variant_id")) {
        params.remove("edit_variant_id");
      }
    }
    if (params[apiFormField[weightKey]] == null ||
        params[apiFormField[weightKey]] == "") {
      params[apiFormField[weightKey]!] = '0.0';
    }
    if (params[apiFormField[heightKey]] == null ||
        params[apiFormField[heightKey]] == "") {
      params[apiFormField[heightKey]!] = '0.0';
    }
    if (params[apiFormField[breadthKey]] == null ||
        params[apiFormField[breadthKey]] == "") {
      params[apiFormField[breadthKey]!] = '0.0';
    }
    if (params[apiFormField[lengthKey]] == null ||
        params[apiFormField[lengthKey]] == "") {
      params[apiFormField[lengthKey]!] = '0.0';
    }
    if (isAddEditComboProduct) {
      if (controllers[productTypeKey]!.text == digitalProductType) {
        params[apiFormField[digitalProductKey]!] =
            selectedProducts.keys.join(",");
        params.remove(apiFormField[physicalProductKey]!);
      } else {
        params[apiFormField[productTypeKey]!] = physicalProductType;
        params[apiFormField[physicalProductKey]!] =
            selectedProducts.keys.join(",");
        params.remove(apiFormField[digitalProductKey]!);
      }
      if (params[apiFormField[hasSimilarProductsKey]] == "1") {
        params[apiFormField[selectProductKey]!] =
            selectedSimilarProducts.keys.join(",");
      }
    } else {
      params.remove(apiFormField[physicalProductKey]);
      params.remove(apiFormField[digitalProductKey]);
      params.remove(apiFormField[hasSimilarProductsKey]);
      params.remove(apiFormField[selectProductKey]);
      if (files[selectCategoryKey]!.isNotEmpty) {
        params[apiFormField[selectCategoryKey]!] = files[selectCategoryKey];
      } else {
        Utils.showSnackBar(message: selectCategoryKey);
      }
    }
    if (isDemoApp) {
      Utils.showSnackBar(message: demoModeOnKey);
      return;
    }

    context.read<AddProductCubit>().addProduct(
        context, params, widget.product != null,
        isComboProduct: isAddEditComboProduct);
  }

  Future<void> updateControllers() async {
    Product product = widget.product!;
    fillTranslatedFields(product);

    if (product.productType != null && product.productType!.isNotEmpty) {
      controllers[productTypeKey]!.text =
          product.productType! == physicalProductType
              ? simpleProductType
              : product.productType!;
    }

    if (!isAddEditComboProduct) {
      controllers[selectCategoryKey]!.text = product.categoryName ?? "";
      files[selectCategoryKey] = product.categoryId!.toString();

      controllers[brandKey]!.text = product.brandName ?? "";
      files[brandKey] = product.brand!;

      controllers[madeInKey]!.text = product.madeIn ?? '';

      controllers[extraDescKey]!.text = product.extraDescription ?? "";
    }

    controllers[tagsKey]!.text =
        product.tags != null ? product.tags!.join(",") : '';
    if (controllers[tagsKey]!.text.trim().isNotEmpty) {
      taglist.clear();
      taglist.addAll(controllers[tagsKey]!.text.split(","));
    }

    if (product.type != variableProductType) {
      // For simple product: enable if stock is not null and not empty
      controllers[enableStockManagementKey]!.text =
          (product.stock != null && product.stock!.isNotEmpty) ? "1" : "0";
    } else {
      // For variable product: enable only if stockType is set
      controllers[enableStockManagementKey]!.text =
          (product.stockType != null && product.stockType!.isNotEmpty)
              ? "1"
              : "0";
    }

    controllers[deliveryChargesKey]!.text = product.deliveryCharges.toString();
    controllers[minimumFreeDeliveryOrderQuantityKey]!.text =
        product.minimumFreeDeliveryOrderQty.toString();
    controllers[quantityStepSizeKey]!.text =
        product.quantityStepSize.toString();
    controllers[totalAllowedQtyKey]!.text =
        product.totalAllowedQuantity.toString();
    controllers[minimumOrderQtyKey]!.text =
        product.minimumOrderQuantity.toString();
    //if seller change type from All to Include and product hase All type, then we cant set that value in dropdown
    controllers[deliverableTypeKey]!.text =
        !deliverableTypes.containsKey(product.deliverableType.toString())
            ? deliverableTypes.keys.first
            : product.deliverableType!.toString();
    controllers[taxIncludeInPriceKey]!.text =
        product.isPricesInclusiveTax!.toString();

    if (isSelectZipCode(controllers[deliverableTypeKey]!.text)) {
      Map<String, String> ids = {};
      List<String> zonelist = product.deliverableZones!.split(",");
      List<String> zoneids = product.deliverableZoneIds!.split(",");
      for (int i = 0; i < zonelist.length; i++) {
        ids[zoneids[i]] = zonelist[i];
      }
      files[selectZonesKey] = ids;
      controllers[selectZonesKey]!.text = ids.values.join(", ");
    }

    controllers[forStandardShippingKey]!.text = product.pickupLocation ?? "";
    files[forStandardShippingKey] = product.pickupLocation ?? "";

    controllers[isCancelableKey]!.text = product.isCancelable!.toString();
    controllers[isReturnableKey]!.text = product.isReturnable!.toString();
    controllers[isCodAllowedKey]!.text = product.codAllowed!.toString();
    controllers[isAttachmentRequiredKey]!.text =
        product.isAttachmentRequired!.toString();
    controllers[cancelableTillWhichStatusKey]!.text =
        product.cancelableTill ?? cancelableStatusTypes.keys.first;
    controllers[descriptionKey]!.text = product.description ?? "";

    if (product.image != null && product.image!.trim().isNotEmpty) {
      files[mainImageKey] =
          "/${product.image!.split("/").sublist(product.image!.split("/").length - 2).join("/")}";
      controllers[mainImageKey]!.text = product.image!;
    }

    files[otherImagesKey] = <String, String>{};
    if (product.otherImages != null && product.otherImages!.isNotEmpty) {
      for (var item in product.otherImages!) {
        files[otherImagesKey][
                "/${item.split("/").sublist(item.split("/").length - 2).join("/")}"] =
            item;
      }

      controllers[otherImagesKey]!.text = files[otherImagesKey].keys.join(",");
    }

    controllers[selectVideoTypeKey]!.text =
        product.videoType ?? videoTypes.keys.first;
    controllers[videoLinkKey]!.text = product.video ?? '';

    controllers[selectVideoKey]!.text = (product.video ?? '').trim().isEmpty
        ? ""
        : "/${product.video!.split("/").sublist(product.video!.split("/").length - 2).join("/")}";

    files[selectVideoKey] = product.video ?? '';

    selectedTax = {};
    selectedProducts = {};
    selectedSimilarProducts = {};
    if (product.taxId != null &&
        product.taxId!.trim().isNotEmpty &&
        product.taxId != "0") {
      List<String> taxids = product.taxId!.split(",");
      List<String> taxnames = product.taxNames!.split(",");
      List<String> taxpercent = product.taxPercentage!.split(",");
      for (int i = 0; i < taxids.length; i++) {
        String taxval = "${taxnames[i]} (${taxpercent[i]}%)";
        selectedTax[taxids[i]] = taxval;
      }
      controllers[productTaxKey]!.text = selectedTax.values.join(",");
    }
    if (isAddEditComboProduct) {
      for (int i = 0; i < product.productDetails!.length; i++) {
        Product dproduct = product.productDetails![i];
        bool isDigitalProduct = dproduct.productType == digitalProductType;
        if (isDigitalProduct) {
          selectedProducts[dproduct.id!.toString()] = dproduct.variants!.first;
        } else {
          ProductVariant? selectedVariant = dproduct.variants!.firstWhere(
              (element) => product.productVariantIds!
                  .split(',')
                  .contains(element.id.toString()));
          String productName = selectedVariant.productName ?? "";
          if (selectedVariant.variantValues != null &&
              selectedVariant.variantValues!.isNotEmpty) {
            productName = "$productName - ${selectedVariant.variantValues}";
          }
          selectedProducts[selectedVariant.id!.toString()] = selectedVariant;
        }
      }
      controllers[hasSimilarProductsKey]!.text =
          product.hasSimilarProduct == "1" ? "1" : "0";
      if (product.hasSimilarProduct == "1") {
        for (var element in product.similarProductDetails!) {
          selectedSimilarProducts[element.id!.toString()] = element;
        }
      }
    }

    if (product.productType == variableProductType &&
        product.stockType != null &&
        product.stockType!.isNotEmpty) {
      controllers[chooseStockMgmtTypeKey]!.text =
          stockMgmtTypesBackend[product.stockType!]!;
    }

    if (product.attrValueIds!.isNotEmpty) {
      controllers[attributesKey]!.text = product.attrValueIds!;
    }
    if (controllers[productTypeKey]!.text == digitalProductType) {
      if (product.variants != null) {
        controllers[priceKey]!.text = product.variants!.first.price.toString();
        controllers[specialPriceKey]!.text =
            product.variants!.first.specialPrice.toString();
      }
      controllers[isDownloadAllowedKey]!.text =
          product.downloadAllowed!.toString();
      if (controllers[isDownloadAllowedKey]!.text == "1") {
        controllers[downloadLinkTypeKey]!.text = product.downloadType!;
        if (controllers[downloadLinkTypeKey]!.text == addLinkType) {
          controllers[digitalProductLinkKey]!.text = product.downloadLink ?? "";
        } else if (controllers[downloadLinkTypeKey]!.text == selfHostedKey) {
          controllers[selectDownloadableMediaKey]!
              .text = (product.downloadLink ?? "")
                  .trim()
                  .isEmpty
              ? ""
              : "/${product.downloadLink!.split("/").sublist(product.downloadLink!.split("/").length - 2).join("/")}";
        }
      }
    } else if (controllers[productTypeKey]!.text == simpleProductType) {
      controllers[SKUKey]!.text = product.sku!;
      controllers[totalStockKey]!.text = product.stock!;
      controllers[stockStatusKey]!.text =
          product.stockType == null || product.stockType!.trim().isEmpty
              ? "1"
              : product.availability.toString();

      controllers[priceKey]!.text = product.variants == null
          ? product.price ?? ""
          : product.variants!.first.price.toString();
      controllers[specialPriceKey]!.text = product.variants == null
          ? product.specialPrice ?? ""
          : product.variants!.first.specialPrice.toString();
      product.variants == null
          ? product.specialPrice ?? ""
          : product.variants!.first.specialPrice.toString();
      controllers[weightKey]!.text = product.variants == null
          ? product.weight ?? ""
          : product.variants!.first.weight.toString();

      controllers[heightKey]!.text = product.variants == null
          ? product.height ?? ""
          : product.variants!.first.height.toString();
      controllers[breadthKey]!.text = product.variants == null
          ? product.breadth ?? ""
          : product.variants!.first.breadth.toString();
      controllers[lengthKey]!.text = product.variants == null
          ? product.length ?? ""
          : product.variants!.first.length.toString();
    } else if (product.stockType == productLevelStockMgmtTypeNo &&
        product.variants != null) {
      controllers[SKUKey]!.text = product.variants!.first.sku!;
      controllers[totalStockKey]!.text = product.variants!.first.stock!;
      controllers[stockStatusKey]!.text =
          product.variants!.first.availability.toString();
    }

    Map<String, String> attributeIDValueId = {};
    if (product.attributes != null && product.attributes!.isNotEmpty) {
      await AttributeRepository().getAttributeListProcess({
        "attribute_value_ids": product.attrValueIds,
        ApiURL.storeIdApiKey: defaultstore!.id!.toString(),
      }).then((newPosts) {
        List<MainAttribute> mainAttributeList = [];
        List data = newPosts[ApiURL.dataKey];
        mainAttributeList
            .addAll(data.map((e) => MainAttribute.fromJson(e)).toList());
        List<String> variableListAttributeIds = [];
        if (product.productType == variableProductType &&
            product.variants != null &&
            product.variants!.isNotEmpty) {
          for (var element in product.variants!) {
            if (element.attributeValueIds!.trim().isNotEmpty) {
              variableListAttributeIds
                  .addAll(element.attributeValueIds!.split(","));
            }
          }
        }
        for (var element in product.attributes!) {
          attributeIDValueId[element.attrId!] = element.ids ?? "";

          selectedAttributes[element.attrId!] = {
            "forVariation": element.attributeValueMap!.keys
                .any((element) => variableListAttributeIds.contains(element)),
            "main": mainAttributeList
                .firstWhere(
                  (mainelement) => mainelement.id!.toString() == element.attrId,
                )
                .toMap(),
            "values": element.attributeValueMap ?? {}
          };
        }
      });
    }

    variations = {};

    if (product.productType == variableProductType &&
        product.variants != null &&
        product.variants!.isNotEmpty) {
      variations = HelperWidgets.generateVariationCombinations(
          selectedAttributes, variations);

      for (var element in product.variants!) {
        List<int> targetParts = element.attributeValueIds!
            .split(",")
            .map(int.parse)
            .toList()
          ..sort();
        String vid = targetParts.join(" ");
        filterVariations();
        if (variations.containsKey(vid)) {
          variations[vid][priceKey]!.text = element.price ?? "";
          variations[vid][specialPriceKey]!.text = element.specialPrice ?? "";
          variations[vid][weightKey]!.text = (element.weight ?? 0.0).toString();
          variations[vid][heightKey]!.text = (element.height ?? 0.0).toString();
          variations[vid][breadthKey]!.text =
              (element.breadth ?? 0.0).toString();
          variations[vid][lengthKey]!.text = (element.length ?? 0.0).toString();
          variations[vid][SKUKey]!.text = element.sku ?? "";
          variations[vid][totalStockKey]!.text = element.stock ?? "";
          variations[vid][stockStatusKey]!.text = element.availability ?? "";

          for (var item in element.images ?? []) {
            variations[vid][otherImagesKey][
                    "/${item.split("/").sublist(item.split("/").length - 2).join("/")}"] =
                item;
          }
        } else {
          variations.remove(vid);
        }
      }
    }
    if (product.customFields != null) {
      for (final field in product.customFields!) {
        final id = field['custom_field_id'];
        final type = field['type'];
        final value = field['value'];
        final fieldKey = 'custom_field_$id';
        if (type == 'checkbox' && value is List) {
          controllers[fieldKey]?.text = value.join(',');
        } else if (type == 'file' && value is String && value.isNotEmpty) {

          files[fieldKey] = value;
          controllers[fieldKey]?.text = value.split('/').last;
        } else if (value != null) {
          controllers[fieldKey]?.text = value.toString();
        }
        setState(() {});
      }
    }
  }

  void filterVariations() {
    // Create a Set of valid keys from productVariantList
    final validKeys = widget.product?.variants?.map((variant) {
      // Convert comma-separated ids to space-separated to match variation keys
      var ids = variant.attributeValueIds.toString().split(',')..toList();
      return ids.join(' ');
    });
    if (validKeys != null) {
      // Remove entries whose keys are not in validKeys
      // variations.removeWhere((key, value) => !validKeys.contains(key));
      variations.forEach((key, value) {
        if (!validKeys.contains(key)) {
          variations[key]["isDeleted"] = true;
        }
      });
      setState(() {});
    }
  }

  void fillTranslatedFields(Product product) {
    final translatedNames = product.translatedName;
    final translatedDescriptions = product.translatedShortDescription;

    translatedNames?.forEach((langCode, value) {
      if (!nameControllers.containsKey(langCode)) {
        nameControllers[langCode] = TextEditingController();
      }
      nameControllers[langCode]?.text = value ?? '';
    });

    translatedDescriptions?.forEach((langCode, value) {
      if (!descControllers.containsKey(langCode)) {
        descControllers[langCode] = TextEditingController();
      }
      descControllers[langCode]?.text = value ?? '';
    });
  }
}
