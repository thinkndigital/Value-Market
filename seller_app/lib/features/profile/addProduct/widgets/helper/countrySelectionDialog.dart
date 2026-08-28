import 'dart:async';

import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/core/theme/colors.dart';
import 'package:value_market_seller/features/profile/addProduct/models/country.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../blocs/countryListCubit.dart';

import '../../../../../utils/designConfig.dart';
import '../../../../../utils/utils.dart';

class CountrySelectionDialog extends StatefulWidget {
  final Function onCountrySelect;
  final int? selectedId;
  final CountryListCubit countryListCubit;
  const CountrySelectionDialog(
      {super.key,
      required this.countryListCubit,
      required this.onCountrySelect,
      this.selectedId});

  @override
  State<StatefulWidget> createState() {
    return CountrySelectionDialogState();
  }
}

class CountrySelectionDialogState extends State<CountrySelectionDialog> {
  final TextEditingController _searchQuery = TextEditingController();
  String _searchText = "";
  bool isSearching = false, isloadmore = true;
  final scrollController = ScrollController();
  List<Country> loadedBrandlist = [];
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
    };
    if (apiParameter != null) {
      parameter.addAll(apiParameter!);
    }
    widget.countryListCubit
        .getCountryList(context, parameter, isSetInitial: isSetInitialPage);
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
    widget.countryListCubit.setOldList(currOffset, loadedBrandlist, isloadmore);
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
                child: BlocBuilder<CountryListCubit, CountryListState>(
                  bloc: widget.countryListCubit,
                  builder: (context, state) {
                    return dropdownListWidget(state);
                  },
                )),
          ]),
        ),
      ),
    );
  }

  dropdownListWidget(CountryListState state) {
    if (state is CountryListFetchProgress && state.isFirstFetch) {
      return Utils.loadingIndicator();
    } else if (state is CountryListFetchFailure) {
      return Utils.msgWithTryAgain(
          context, state.errorMessage, () => loadPage(isSetInitialPage: true));
    }
    List<Country> countrylist = [];

    bool isLoading = false, isLoadMore = false;
    int offset = 0;
    if (state is CountryListFetchProgress) {
      countrylist = state.oldCountryList;
      offset = state.currOffset;
      isLoading = true;
    } else if (state is CountryListFetchSuccess) {
      countrylist = state.countryList;
      offset = state.currOffset;
      isLoadMore = state.isLoadmore;
    }
    if (_searchText.trim().isEmpty && countrylist.isNotEmpty) {
      currOffset = offset;
      isloadmore = isLoadMore;
      loadedBrandlist = [];
      loadedBrandlist = countrylist;
    }
    return ListView.separated(
      controller: scrollController,
      shrinkWrap: true,
      itemCount: countrylist.length + (isLoading ? 1 : 0),
      padding: const EdgeInsets.symmetric(vertical: 8),
      separatorBuilder: (context, index) {
        return Divider(
          color: grey,
          thickness: 1,
        );
      },
      itemBuilder: (context, index) {
        if (index < countrylist.length) {
          Country country = countrylist[index];

          return GestureDetector(
            onTap: () {
              widget.onCountrySelect(country);
            },
            child: Row(
              mainAxisSize: MainAxisSize.max,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  flex: 1,
                  child: Text(
                    country.name ?? '',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium!
                        .merge(TextStyle(color: black)),
                  ),
                ),
                if (widget.selectedId == country.id)
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
