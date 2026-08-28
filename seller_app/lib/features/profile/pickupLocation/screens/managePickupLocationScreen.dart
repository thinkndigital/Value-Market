import 'dart:async';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/features/profile/pickupLocation/blocs/getPickupLocationCubit.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/routes/routes.dart';

import '../models/location.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import '../../../../utils/utils.dart';

import '../../../../commons/widgets/customRoundedButton.dart';

class ManagePickupLocationScreen extends StatefulWidget {
  const ManagePickupLocationScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => GetPickupLocationCubit(),
        child: const ManagePickupLocationScreen(),
      );
  @override
  _ManagePickupLocationScreenState createState() =>
      _ManagePickupLocationScreenState();
}

class _ManagePickupLocationScreenState
    extends State<ManagePickupLocationScreen> {
  final scrollController = ScrollController();
  List<Location> locationlist = [];
  int currOffset = 0;
  bool isloadmore = true;
  @override
  void initState() {
    super.initState();

    setupScrollController(context);
    loadPage(isSetInitialPage: true);
  }

  loadPage({bool isSetInitialPage = false}) {
    Map<String, String> parameter = {
      ApiURL.storeIdApiKey:
          context.read<StoresCubit>().getDefaultStore().id.toString(),
    };

    BlocProvider.of<GetPickupLocationCubit>(context)
        .getPickupLocation(context, parameter, isSetInitial: isSetInitialPage);
  }

  setupScrollController(context) {
    scrollController.addListener(() {
      if (scrollController.position.atEdge) {
        if (scrollController.position.pixels != 0) {
          loadPage();
        }
      }
    });
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        appBar: const CustomAppbar(
          titleKey: addPickupLocKey,
          showBackButton: true,
        ),
        body: BlocBuilder<GetPickupLocationCubit, GetPickupLocationState>(
          builder: (context, state) {
            if (state is GetPickupLocationProgress && state.isFirstFetch) {
              return Utils.loadingIndicator();
            } else if (state is GetPickupLocationFailure) {
              return Utils.msgWithTryAgain(context, state.errorMessage,
                  () => loadPage(isSetInitialPage: true));
            }
            return RefreshIndicator(
                onRefresh: refreshList, child: listContent(state));
          },
        ),
        bottomNavigationBar:
            BlocBuilder<GetPickupLocationCubit, GetPickupLocationState>(
          builder: (context, state) {
            return CustomBottomButtonContainer(
              child: CustomRoundedButton(
                widthPercentage: 1,
                buttonTitle: addPickupLocKey,
                showBorder: false,
                onTap: () {
                  Utils.navigateToScreen(context, Routes.addPickupLocScreen,
                      arguments: {
                        "callback": (Location location, bool isAdd) {
                          if (state is GetPickupLocationSuccess) {
                            isloadmore = state.isLoadmore;
                            currOffset = state.currOffset;
                          }
                          if (isAdd) {
                            if (locationlist.isEmpty) isloadmore = false;
                            locationlist.insert(0, location);
                          } else {
                            int index = locationlist.indexWhere(
                                (element) => element.id == location.id);
                            if (index != -1) {
                              locationlist[index] = location;
                            }
                          }
                          BlocProvider.of<GetPickupLocationCubit>(context)
                              .setOldList(currOffset, locationlist, isloadmore);
                        }
                      });
                },
              ),
            );
          },
        ));
  }

  listContent(GetPickupLocationState state) {
    locationlist = [];
    bool isLoading = false;
    if (state is GetPickupLocationProgress) {
      locationlist = state.oldLocationList;
      isLoading = true;
    } else if (state is GetPickupLocationSuccess) {
      locationlist = state.locationList;
      currOffset = state.currOffset;
      isLoading = state.isLoadmore;
    }
    isloadmore = isLoading;
    return ListView.separated(
      separatorBuilder: (context, index) {
        return const SizedBox(height: 8);
      },
      physics: const AlwaysScrollableScrollPhysics(),
      controller: scrollController,
      padding: EdgeInsets.all(appContentHorizontalPadding),
      itemBuilder: (context, index) {
        if (index < locationlist.length) {
          return locationWidget(locationlist[index]);
        } else {
          Timer(const Duration(milliseconds: 30), () {
            scrollController.jumpTo(scrollController.position.maxScrollExtent);
          });

          return Utils.loadingIndicator();
        }
      },
      itemCount: locationlist.length + (isLoading ? 1 : 0),
    );
  }

  Future<void> refreshList() async {
    await Future.delayed(const Duration(seconds: 2), () {
      loadPage(isSetInitialPage: true);
    });
  }

  buildLabel(String title, String value) {
    return Padding(
      padding: EdgeInsetsDirectional.symmetric(
        horizontal: appContentHorizontalPadding,
        vertical: 4,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomTextContainer(
            textKey: title,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(
            width: 4,
          ),
          CustomTextContainer(
            textKey: ':',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(
            width: 4,
          ),
          Expanded(
            child: CustomTextContainer(
              textKey: value,
              style: Theme.of(context).textTheme.bodyLarge!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.8)),
            ),
          ),
        ],
      ),
    );
  }

  locationWidget(Location location) {
    return Container(
        padding: const EdgeInsetsDirectional.symmetric(vertical: 12),
        decoration: ShapeDecoration(
            color: Theme.of(context).colorScheme.primaryContainer,
            shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(borderRadius)),
            shadows: DesignConfig.appShadow),
        child: Column(
          children: <Widget>[
            buildLabel(pickupLocationKey, location.pickupLocation ?? ""),
            const SizedBox(
              height: 4,
            ),
            const Divider(
              height: 1,
            ),
            const SizedBox(
              height: 4,
            ),
            buildLabel(nameKey, location.name ?? ""),
            buildLabel(emailKey, location.email ?? ""),
            buildLabel(phoneNumberKey, location.phone ?? ""),
            buildLabel(cityKey, location.city ?? ""),
            buildLabel(pincodeKey, location.pincode ?? ""),
            buildLabel(addressKey, location.address ?? ""),
            buildLabel(additionalAddressKey, location.address2 ?? ""),
          ],
        ));
  }
}
