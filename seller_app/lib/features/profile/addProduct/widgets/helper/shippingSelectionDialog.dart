import 'dart:async';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../../core/theme/colors.dart';
import '../../../pickupLocation/blocs/getPickupLocationCubit.dart';
import '../../../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../pickupLocation/models/location.dart';
import '../../../../../utils/designConfig.dart';
import '../../../../../core/localization/labelKeys.dart';
import '../../../../../utils/utils.dart';

class ShippingSelectionDialog extends StatefulWidget {
  final Function onShippingSelect;
  final String? selectedPickupLocation;
  final GetPickupLocationCubit locationListCubit;
  const ShippingSelectionDialog(
      {super.key,
      required this.locationListCubit,
      required this.onShippingSelect,
      this.selectedPickupLocation});

  @override
  State<StatefulWidget> createState() {
    return ShippingSelectionDialogState();
  }
}

class ShippingSelectionDialogState extends State<ShippingSelectionDialog> {
  final TextEditingController _searchQuery = TextEditingController();
  String _searchText = "";
  bool isSearching = false, isloadmore = true;
  final scrollController = ScrollController();
  List<Location> loadedBrandlist = [];
  int currOffset = 0;
  Map<String, String>? apiParameter;
  @override
  void initState() {
    super.initState();
    apiParameter = {};
    isSearching = false;

    setupScrollController(context);
  }

  loadPage({bool isSetInitialPage = false}) {
    Map<String, String> parameter = {
      ApiURL.storeIdApiKey:
          context.read<StoresCubit>().getDefaultStore().id.toString(),
      ApiURL.statusApiKey: '1' // pass 1 to get only verified locations
    };
    if (apiParameter != null) {
      parameter.addAll(apiParameter!);
    }
    widget.locationListCubit
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

  searchBrand() {
    if (_searchQuery.text.trim().isEmpty) {
      setState(() {
        isSearching = false;
        _searchText = "";
      });
    } else {
      setState(() {
        isSearching = true;
        _searchText = _searchQuery.text;
      });
    }

    if (_searchText.trim().isEmpty) {
      setAllList();
      return;
    }
    apiParameter!["search"] = _searchText;
    loadPage(isSetInitialPage: true);
  }

  setAllList() {
    if (apiParameter!.containsKey("search")) {
      apiParameter!.remove("search");
    }
    widget.locationListCubit
        .setOldList(currOffset, loadedBrandlist, isloadmore);
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      onPopInvokedWithResult: (didPop, result) {
        setAllList();
      },
      child: SingleChildScrollView(
        child: SizedBox(
          width: double.maxFinite,
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Align(
              alignment: AlignmentDirectional.topEnd,
              child: IconButton(
                  onPressed: () {
                    Navigator.of(context).pop();
                  },
                  icon: const Icon(Icons.cancel_outlined)),
            ),
            TextField(
              controller: _searchQuery,
              decoration: InputDecoration(
                  enabledBorder: DesignConfig.setUnderlineInputBorder(grey),
                  focusedBorder: DesignConfig.setUnderlineInputBorder(grey),
                  border: DesignConfig.setUnderlineInputBorder(grey),
                  prefixIcon: Icon(Icons.search, color: grey),
                  hintText: context
                      .read<SettingsAndLanguagesCubit>()
                      .getTranslatedValue(labelKey: searchKey),
                  hintStyle: TextStyle(color: grey)),
              onChanged: (value) {
                searchBrand();
              },
            ),
            ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(context).size.height * 0.6,
                ),
                child:
                    BlocBuilder<GetPickupLocationCubit, GetPickupLocationState>(
                  bloc: widget.locationListCubit,
                  builder: (context, state) {
                    return dropdownListWidget(state);
                  },
                )),
          ]),
        ),
      ),
    );
  }

  dropdownListWidget(GetPickupLocationState state) {
    if (state is GetPickupLocationProgress && state.isFirstFetch) {
      return Utils.loadingIndicator();
    } else if (state is GetPickupLocationFailure) {
      return Utils.msgWithTryAgain(
          context, state.errorMessage, () => loadPage(isSetInitialPage: true));
    }
    List<Location> locationlist = [];

    bool isLoading = false, isLoadMore = false;
    int offset = 0;
    if (state is GetPickupLocationProgress) {
      locationlist = state.oldLocationList;
      offset = state.currPage;
      isLoading = true;
    } else if (state is GetPickupLocationSuccess) {
      locationlist = state.locationList;
      offset = state.currOffset;
      isLoadMore = state.isLoadmore;
    }
    if (_searchText.trim().isEmpty && locationlist.isNotEmpty) {
      currOffset = offset;
      loadedBrandlist = [];
      loadedBrandlist = locationlist;
      isloadmore = isLoadMore;
    }
    return ListView.separated(
      controller: scrollController,
      shrinkWrap: true,
      itemCount: locationlist.length + (isLoading ? 1 : 0),
      padding: const EdgeInsets.symmetric(vertical: 8),
      separatorBuilder: (context, index) {
        return Divider(
          color: grey,
          thickness: 1,
        );
      },
      itemBuilder: (context, index) {
        if (index < locationlist.length) {
          Location location = locationlist[index];

          return GestureDetector(
            onTap: () {
              widget.onShippingSelect(location);
            },
            child: Row(
              mainAxisSize: MainAxisSize.max,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  flex: 1,
                  child: Text(
                    location.pickupLocation ?? '',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium!
                        .merge(TextStyle(color: black)),
                  ),
                ),
                if (widget.selectedPickupLocation == location.pickupLocation)
                  Icon(Icons.check, color: primaryColor),
              ],
            ),
          );
        } else {
          Timer(const Duration(milliseconds: 30), () {
            scrollController.jumpTo(scrollController.position.maxScrollExtent);
          });

          return Utils.loadingIndicator();
        }
      },
    );
  }
}
