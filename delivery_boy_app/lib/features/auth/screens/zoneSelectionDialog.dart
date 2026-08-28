import 'dart:async';
import 'package:value_market_delivery_boy/features/auth/blocs/zoneListCubit.dart';
import 'package:value_market_delivery_boy/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../models/zone.dart';
import '../../../../../../utils/designConfig.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../../../../utils/utils.dart';
import '../../../core/theme/colors.dart';

class ZoneSelectionDialog extends StatefulWidget {
  final Function onZoneSelect;
  final Map<String, String> selectedId;
  final ZoneListCubit zoneListCubit;
  const ZoneSelectionDialog(
      {super.key,
      required this.zoneListCubit,
      required this.onZoneSelect,
      required this.selectedId});

  @override
  ZoneSelectionDialogState createState() => ZoneSelectionDialogState();
}

class ZoneSelectionDialogState extends State<ZoneSelectionDialog> {
  final TextEditingController _searchQuery = TextEditingController();
  String _searchText = "";
  bool isSearching = false, isloadmore = true;
  final scrollController = ScrollController();
  List<Zone> loadedBrandlist = [];
  int currOffset = 0;
  Map<String, String>? apiParameter;
  Map<String, String> selectedIdList = {};
  @override
  void initState() {
    super.initState();
    apiParameter = {};
    selectedIdList.addAll(widget.selectedId);
    isSearching = false;
    setupScrollController(context);
  }

  loadPage({bool isSetInitialPage = false}) {
    Map<String, String> parameter = {};
    if (apiParameter != null) {
      parameter.addAll(apiParameter!);
    }
    widget.zoneListCubit
        .getZoneList(context, parameter, isSetInitial: isSetInitialPage);
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

  ZoneSelectionDialogState() {
    _searchQuery.addListener(() {
      searchBrand();
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
    widget.zoneListCubit.setOldList(currOffset, loadedBrandlist, isloadmore);
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
            ),
            ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(context).size.height * 0.6,
                ),
                child: BlocBuilder<ZoneListCubit, ZoneListState>(
                  bloc: widget.zoneListCubit,
                  builder: (context, state) {
                    return dropdownListWidget(state);
                  },
                )),
            const SizedBox(height: 5),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                TextButton(
                    onPressed: () {
                      Navigator.of(context).pop();
                    },
                    child: const CustomTextContainer(textKey: cancelKey)),
                TextButton(
                    onPressed: () {
                      widget.onZoneSelect(selectedIdList);
                    },
                    child: const CustomTextContainer(textKey: applyKey))
              ],
            )
          ]),
        ),
      ),
    );
  }

  dropdownListWidget(ZoneListState state) {
    if (state is ZoneListFetchProgress && state.isFirstFetch) {
      return Utils.loadingIndicator();
    } else if (state is ZoneListFetchFailure) {
      return Utils.msgWithTryAgain(
          context, state.errorMessage, () => loadPage(isSetInitialPage: true));
    }
    List<Zone> zonelist = [];

    bool isLoading = false, isLoadmore = false;

    int offset = 0;
    if (state is ZoneListFetchProgress) {
      zonelist = state.oldBrandList;
      offset = state.currOffset;
      isLoading = true;
    } else if (state is ZoneListFetchSuccess) {
      zonelist = state.brandList;
      offset = state.currOffset;
      isLoadmore = state.isLoadmore;
    }
    if (_searchText.trim().isEmpty && zonelist.isNotEmpty) {
      currOffset = offset;
      isloadmore = isLoadmore;
      loadedBrandlist = [];
      loadedBrandlist = zonelist;
    }
    return ListView.separated(
      shrinkWrap: true,
      controller: scrollController,
      itemCount: zonelist.length + (isLoading ? 1 : 0),
      padding: const EdgeInsets.symmetric(vertical: 8),
      separatorBuilder: (context, index) {
        return Divider(
          color: grey,
          thickness: 1,
        );
      },
      itemBuilder: (context, index) {
        if (index < zonelist.length) {
          Zone zipcode = zonelist[index];
          String city = zipcode.zonename ?? "";

          return GestureDetector(
            onTap: () {
              if (selectedIdList.containsKey(zipcode.id.toString())) {
                selectedIdList.remove(zipcode.id.toString());
              } else {
                selectedIdList[zipcode.id.toString()] = city;
              }
              setState(() {});
            },
            child: Row(
              mainAxisSize: MainAxisSize.max,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  flex: 1,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        city,
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium!
                            .merge(TextStyle(color: primaryColor)),
                      ),
                      if (zipcode.zipcodeList!.isNotEmpty)
                        zipcodeCityNames(
                            serviceableZipcodesKey,
                            zipcode.zipcodeList!
                                .map((e) => e.zipcode!)
                                .toList()
                                .join(",")),
                      if (zipcode.cityList!.isNotEmpty)
                        zipcodeCityNames(
                            serviceableCitiesKey,
                            zipcode.cityList!
                                .map((e) => e.cityName!)
                                .toList()
                                .join(","))
                    ],
                  ),
                ),
                if (selectedIdList.containsKey(zipcode.id.toString()))
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

  zipcodeCityNames(String lbl, String namelist) {
    return Row(
      children: [
        Text(
          "${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: lbl)} : ",
          style: Theme.of(context)
              .textTheme
              .bodySmall!
              .copyWith(fontWeight: FontWeight.bold),
        ),
        Expanded(
            child: Text(
          namelist,
          style: Theme.of(context).textTheme.bodySmall!,
        ))
      ],
    );
  }
}
