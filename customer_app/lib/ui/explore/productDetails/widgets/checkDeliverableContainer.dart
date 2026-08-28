import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/ui/profile/address/blocs/cityCubit.dart';
import 'package:eshop_plus/ui/profile/address/blocs/zipcodeCubit.dart';
import 'package:eshop_plus/ui/explore/cubits/checkProductDeliverabilityCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/ui/profile/address/models/city.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/ui/profile/address/models/zipcode.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextFieldContainer.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:eshop_plus/utils/validator.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class CheckDeliverableContainer extends StatefulWidget {
  final Product product;
  const CheckDeliverableContainer({Key? key, required this.product})
      : super(key: key);

  @override
  _CheckDeliverableContainerState createState() =>
      _CheckDeliverableContainerState();
}

class _CheckDeliverableContainerState extends State<CheckDeliverableContainer> {
  Map<String, TextEditingController> controllers = {};
  bool _showSelectCity = false;
  City? _selectedCity;
  Zipcode? _selectedZipcode;
  final List formFields = [
    selectCityKey,
    searchCityKey,
    selectPincodeKey,
    searchPincodeKey
  ];
  List<City> filteredCities = [];
  List<Zipcode> filteredZipcodes = [];
  @override
  void dispose() {
    controllers.forEach((key, controller) {
      controller.dispose();
    });
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    formFields.forEach((key) {
      controllers[key] = TextEditingController();
    });
    Future.delayed(Duration.zero, () {
      context.read<CityCubit>().getCities();
      context.read<ZipcodeCubit>().getZipcodes();
    });
  }

  @override
  Widget build(BuildContext context) {
    return MultiBlocListener(
        listeners: [
          BlocListener<CityCubit, CityState>(
            listener: (context, state) {
              if (state is CityFetchSuccess) {
                filteredCities = state.cities;
              }
            },
          ),
          BlocListener<ZipcodeCubit, ZipcodeState>(
            listener: (context, state) {
              if (state is ZipcodeFetchSuccess) {
                filteredZipcodes = state.zipcodes;
              }
            },
          ),
        ],
        child: BlocConsumer<CheckProductDeliverabilityCubit,
            CheckProductDeliverabilityState>(
          listener: (context, state) {
            if (state is CheckProductDeliverabilityFetchSuccess) {
              Utils.showSnackBar(
                  context: context, message: state.successMessage);
            }
            if (state is CheckProductDeliverabilityFetchFailure) {
              Utils.showSnackBar(context: context, message: state.errorMessage);
            }
          },
          builder: (context, state) {
            return GestureDetector(
              onTap: () {
                setState(() {
                  _showSelectCity = !_showSelectCity;
                });
              },
              child: CustomDefaultContainer(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        CustomTextContainer(
                          textKey: checkProductDeliverabilityKey,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const Icon(Icons.arrow_forward_ios, size: 24)
                      ],
                    ),
                    if (_showSelectCity) ...[
                      DesignConfig.defaultHeightSizedBox,
                      context
                                  .read<StoresCubit>()
                                  .getDefaultStore()
                                  .productDeliverabilityType ==
                              'city_wise_deliverability'
                          ? CustomTextFieldContainer(
                              readOnly: true,
                              hintTextKey: selectCityKey,
                              textEditingController:
                                  controllers[selectCityKey]!,
                              textInputAction: TextInputAction.next,
                              isFieldValueMandatory: true,
                              validator: (v) =>
                                  Validator.emptyValueValidation(context, v),
                              suffixWidget: state
                                      is CheckProductDeliverabilityFetchInProgress
                                  ? CustomCircularProgressIndicator(
                                      indicatorColor:
                                          Theme.of(context).colorScheme.primary)
                                  : TextButton(
                                      onPressed: () => selectCityBottomsheet(
                                          context,
                                          context.read<CityCubit>(),
                                          context.read<
                                              CheckProductDeliverabilityCubit>()),
                                      child: Text([
                                        CheckProductDeliverabilityFetchFailure,
                                        CheckProductDeliverabilityFetchSuccess
                                      ].contains(state)
                                          ? checkKey
                                          : changekey),
                                    ),
                              onTap: () => selectCityBottomsheet(
                                  context,
                                  context.read<CityCubit>(),
                                  context
                                      .read<CheckProductDeliverabilityCubit>()),
                            )
                          : CustomTextFieldContainer(
                              readOnly: true,
                              hintTextKey: selectPincodeKey,
                              textEditingController:
                                  controllers[selectPincodeKey]!,
                              textInputAction: TextInputAction.done,
                              isFieldValueMandatory: true,
                              validator: (v) =>
                                  Validator.emptyValueValidation(context, v),
                              suffixWidget: TextButton(
                                onPressed: () => selectZipcodeBottomsheet(
                                    context,
                                    context.read<ZipcodeCubit>(),
                                    context.read<
                                        CheckProductDeliverabilityCubit>()),
                                child: Text(
                                  [
                                    CheckProductDeliverabilityFetchFailure,
                                    CheckProductDeliverabilityFetchSuccess
                                  ].contains(state)
                                      ? checkKey
                                      : changekey,
                                ),
                              ),
                              onTap: () => selectZipcodeBottomsheet(
                                  context,
                                  context.read<ZipcodeCubit>(),
                                  context
                                      .read<CheckProductDeliverabilityCubit>()),
                            ),
                    ]
                  ],
                ),
              ),
            );
          },
        ));
  }

  selectCityBottomsheet(
    BuildContext buildContext,
    CityCubit cityCubit,
    CheckProductDeliverabilityCubit checkProductDeliverabilityCubit,
  ) {
    return Utils.openModalBottomSheet(buildContext,
        StatefulBuilder(builder: (context, StateSetter setState) {
      return BlocBuilder<CityCubit, CityState>(
        bloc: cityCubit,
        builder: (context, state) {
          return Container(
              height: MediaQuery.of(buildContext).size.height,
              padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding,
              ),
              child: Column(
                children: [
                  CustomTextFieldContainer(
                    hintTextKey: searchCityKey,
                    textEditingController: controllers[searchCityKey]!,
                    onChanged: (value) => cityCubit.getCities(
                        search: controllers[searchCityKey]!.text.trim()),
                    suffixWidget: Padding(
                      padding: const EdgeInsetsDirectional.only(end: 10),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          GestureDetector(
                              onTap: () => cityCubit.getCities(
                                  search:
                                      controllers[searchCityKey]!.text.trim()),
                              child: const Icon(Icons.search_outlined)),
                          GestureDetector(
                              onTap: () {
                                setState(() {
                                  controllers[searchCityKey]!.clear();
                                  cityCubit.getCities(search: '');
                                });
                              },
                              child: const Icon(Icons.close)),
                        ],
                      ),
                    ),
                  ),
                  DesignConfig.smallHeightSizedBox,
                  state is CityFetchSuccess
                      ? Expanded(
                          child: ListView.separated(
                            shrinkWrap: true,
                            physics: const AlwaysScrollableScrollPhysics(),
                            itemCount: filteredCities.length,
                            itemBuilder: (_, index) {
                              City city = filteredCities[index];
                              return buildTile(
                                  _selectedCity != null
                                      ? _selectedCity!.id == city.id
                                      : false,
                                  city.name!, () {
                                setState(() {
                                  _selectedCity = city;
                                  controllers[selectCityKey]!.text = city.name!;
                                  controllers[selectPincodeKey]!.clear();
                                  checkProductDeliverabilityCubit
                                      .checkProductDeliverability(
                                          productParams: {
                                        ApiURL.storeIdApiKey:
                                            widget.product.storeId,
                                        ApiURL.productIdApiKey:
                                            widget.product.id,
                                        ApiURL.productTypeApiKey:
                                            widget.product.type ==
                                                    comboProductType
                                                ? comboType
                                                : regularType,
                                        ApiURL.cityApiKey:
                                            controllers[selectCityKey]!.text,
                                      },
                                          sellerParams: {
                                        ApiURL.storeIdApiKey:
                                            widget.product.storeId,
                                        ApiURL.sellerIdApiKey:
                                            widget.product.sellerId,
                                        ApiURL.cityApiKey:
                                            controllers[selectCityKey]!.text,
                                      });

                                  Navigator.of(context).pop();
                                });
                              });
                            },
                            separatorBuilder:
                                (BuildContext context, int index) {
                              return const SizedBox(height: 2);
                            },
                          ),
                        )
                      : state is CityFetchFailure
                          ? const Center(
                              child: CustomTextContainer(
                                  textKey: dataNotAvailableKey))
                          : CustomCircularProgressIndicator(
                              indicatorColor:
                                  Theme.of(context).colorScheme.primary)
                ],
              ));
        },
      );
    }), staticContent: false, isScrollControlled: true);
  }

  selectZipcodeBottomsheet(BuildContext buildContext, ZipcodeCubit zipcodeCubit,
      CheckProductDeliverabilityCubit checkProductDeliverabilityCubit) {
    return Utils.openModalBottomSheet(buildContext,
        StatefulBuilder(builder: (context, StateSetter setState) {
      return BlocBuilder<ZipcodeCubit, ZipcodeState>(
          bloc: zipcodeCubit,
          builder: (context, state) {
            return Container(
                padding: const EdgeInsetsDirectional.symmetric(
                  horizontal: appContentHorizontalPadding,
                ),
                child: Column(
                  children: [
                    CustomTextFieldContainer(
                      hintTextKey: searchPincodeKey,
                      textEditingController: controllers[searchPincodeKey]!,
                      onChanged: (p0) => zipcodeCubit.getZipcodes(
                          search: controllers[searchPincodeKey]!.text.trim()),
                      suffixWidget: Padding(
                        padding: const EdgeInsetsDirectional.only(end: 10),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            GestureDetector(
                                onTap: () => zipcodeCubit.getZipcodes(
                                      search: controllers[searchPincodeKey]!
                                          .text
                                          .trim(),
                                    ),
                                child: const Icon(Icons.search_outlined)),
                            GestureDetector(
                                onTap: () {
                                  setState(() {
                                    controllers[searchPincodeKey]!.clear();
                                    zipcodeCubit.getZipcodes(search: '');
                                  });
                                },
                                child: const Icon(Icons.close)),
                          ],
                        ),
                      ),
                    ),
                    DesignConfig.smallHeightSizedBox,
                    state is ZipcodeFetchSuccess
                        ? Expanded(
                            child: ListView.separated(
                              shrinkWrap: true,
                              physics: const AlwaysScrollableScrollPhysics(),
                              itemCount: filteredZipcodes.length,
                              itemBuilder: (_, index) {
                                Zipcode zipcode = filteredZipcodes[index];
                                return buildTile(
                                    _selectedZipcode != null
                                        ? _selectedZipcode!.id == zipcode.id
                                        : false,
                                    zipcode.zipcode!, () {
                                  setState(() {
                                    _selectedZipcode = zipcode;
                                    controllers[selectPincodeKey]!.text =
                                        zipcode.zipcode!;
                                    checkProductDeliverabilityCubit
                                        .checkProductDeliverability(
                                            productParams: {
                                          ApiURL.storeIdApiKey:
                                              widget.product.storeId,
                                          ApiURL.productIdApiKey:
                                              widget.product.id,
                                          ApiURL.productTypeApiKey:
                                              widget.product.type ==
                                                      comboProductType
                                                  ? comboType
                                                  : regularType,
                                          ApiURL.zipCodeApiKey:
                                              controllers[selectPincodeKey]!
                                                  .text,
                                        },
                                            sellerParams: {
                                          ApiURL.storeIdApiKey:
                                              widget.product.storeId,
                                          ApiURL.sellerIdApiKey:
                                              widget.product.sellerId,
                                          ApiURL.zipCodeApiKey:
                                              controllers[selectPincodeKey]!
                                                  .text,
                                        });

                                    Navigator.of(context).pop();
                                  });
                                });
                              },
                              separatorBuilder:
                                  (BuildContext context, int index) {
                                return const SizedBox(height: 2);
                              },
                            ),
                          )
                        : state is ZipcodeFetchFailure
                            ? const Center(
                                child: CustomTextContainer(
                                    textKey: dataNotAvailableKey))
                            : CustomCircularProgressIndicator(
                                indicatorColor:
                                    Theme.of(context).colorScheme.primary)
                  ],
                ));
          });
    }), staticContent: false, isScrollControlled: true);
  }

  buildTile(bool isSelected, String title, VoidCallback onTap) {
    return ListTile(
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(15.0)),
        ),
        selectedColor:
            Theme.of(context).colorScheme.primary.withValues(alpha: 0.1),
        title: CustomTextContainer(
          textKey: title,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: isSelected
                  ? Theme.of(context).colorScheme.primary
                  : Colors.black,
              fontWeight: isSelected ? FontWeight.w500 : FontWeight.w400,
              letterSpacing: 0.5),
        ),
        tileColor: isSelected
            ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.1)
            : Colors.transparent,
        onTap: onTap,
        trailing: isSelected
            ? Icon(Icons.check, color: Theme.of(context).colorScheme.primary)
            : null);
  }
}
