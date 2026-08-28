import 'dart:async';

import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/theme/colors.dart';
import 'package:value_market_seller/utils/designConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../blocs/zipcodeListCubit.dart';
import '../models/zipcode.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import '../../../utils/utils.dart';

class ZipcodeSelectionDialog extends StatefulWidget {
  final Function onZipcodeSelect;
  final String selectedId;
  final ZipcodeListCubit zipcodeListCubit;
  final bool isFetchZipcode;
  const ZipcodeSelectionDialog(
      {super.key,
      required this.zipcodeListCubit,
      required this.onZipcodeSelect,
      required this.isFetchZipcode,
      required this.selectedId});

  @override
  State<StatefulWidget> createState() {
    return ZipcodeSelectionDialogState();
  }
}

class ZipcodeSelectionDialogState extends State<ZipcodeSelectionDialog> {
  final TextEditingController _searchQuery = TextEditingController();
  String _searchText = "";
  bool isSearching = false, isloadmore = true;
  final scrollController = ScrollController();
  List<Zipcode> loadedBrandlist = [];
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
    widget.zipcodeListCubit.getZipcodeList(
        context, parameter, widget.isFetchZipcode,
        isSetInitial: isSetInitialPage);
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
    widget.zipcodeListCubit.setOldList(currOffset, loadedBrandlist, isloadmore);
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
                child: BlocBuilder<ZipcodeListCubit, ZipcodeListState>(
                  bloc: widget.zipcodeListCubit,
                  builder: (context, state) {
                    return dropdownListWidget(state);
                  },
                )),
          ]),
        ),
      ),
    );
  }

  dropdownListWidget(ZipcodeListState state) {
    if (state is ZipcodeListFetchProgress && state.isFirstFetch) {
      return Utils.loadingIndicator();
    } else if (state is ZipcodeListFetchFailure) {
      return Utils.msgWithTryAgain(
          context, state.errorMessage, () => loadPage(isSetInitialPage: true));
    }
    List<Zipcode> zipcodelist = [];

    bool isLoading = false, isLoadmore = false;

    int offset = 0;
    if (state is ZipcodeListFetchProgress) {
      zipcodelist = state.oldZipcodeList;
      offset = state.currOffset;
      isLoading = true;
    } else if (state is ZipcodeListFetchSuccess) {
      zipcodelist = state.zipcodeList;
      offset = state.currOffset;
      isLoadmore = state.isLoadmore;
      if (state.zipcodeList.isEmpty) {
        return const Padding(
          padding: EdgeInsets.symmetric(vertical: 16),
          child: CustomTextContainer(textKey: dataNotAvailableKey),
        );
      }
    }
    if (_searchText.trim().isEmpty && zipcodelist.isNotEmpty) {
      currOffset = offset;
      isloadmore = isLoadmore;
      loadedBrandlist = [];
      loadedBrandlist = zipcodelist;
    }
    return ListView.separated(
      shrinkWrap: true,
      controller: scrollController,
      itemCount: zipcodelist.length + (isLoading ? 1 : 0),
      padding: const EdgeInsets.symmetric(vertical: 8),
      separatorBuilder: (context, index) {
        return Divider(
          color: grey,
          thickness: 1,
        );
      },
      itemBuilder: (context, index) {
        if (index < zipcodelist.length) {
          Zipcode zipcode = zipcodelist[index];

          return GestureDetector(
            onTap: () {
              widget.onZipcodeSelect({
                zipcode.id.toString():
                    widget.isFetchZipcode ? zipcode.zipcode! : zipcode.cityName!
              });
            },
            child: Row(
              mainAxisSize: MainAxisSize.max,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  flex: 1,
                  child: Text(
                    widget.isFetchZipcode
                        ? zipcode.zipcode!
                        : zipcode.cityName!,
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium!
                        .merge(TextStyle(color: black)),
                  ),
                ),
                if (widget.selectedId == zipcode.id.toString())
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
