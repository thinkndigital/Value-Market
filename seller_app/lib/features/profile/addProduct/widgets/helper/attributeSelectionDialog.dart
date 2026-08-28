import 'dart:async';

import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/models/mainAttribute.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/core/theme/colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../blocs/attributeListCubit.dart';
import '../../../../../commons/blocs/storesCubit.dart';

import '../../../../../utils/designConfig.dart';
import '../../../../../utils/utils.dart';

class AttributeSelectionDialog extends StatefulWidget {
  final Function onAttributeSelect;
  final List<String>? selectedId;
  final bool isMainAttributeSelection;
  final AttributeListCubit attributeListCubit;
  final MainAttribute? mainAttribute;
  const AttributeSelectionDialog(
      {super.key,
      required this.isMainAttributeSelection,
      required this.attributeListCubit,
      required this.onAttributeSelect,
      this.mainAttribute,
      this.selectedId = const []});

  @override
  State<StatefulWidget> createState() {
    return AttributeSelectionDialogState();
  }
}

class AttributeSelectionDialogState extends State<AttributeSelectionDialog> {
  final TextEditingController _searchQuery = TextEditingController();
  String _searchText = "";
  bool isSearching = false, isloadmore = true;
  final scrollController = ScrollController();
  List<MainAttribute> loadedBrandlist = [];
  int currOffset = 0;
  Map<String, String>? apiParameter;
  Map<String, String> selectedValues = {};
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
          context.read<StoresCubit>().getDefaultStore().id.toString()
    };
    if (apiParameter != null) {
      parameter.addAll(apiParameter!);
    }
    widget.attributeListCubit
        .getAttributeList(context, parameter, isSetInitial: isSetInitialPage);
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
    widget.attributeListCubit
        .setOldList(currOffset, loadedBrandlist, isloadmore);
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      onPopInvokedWithResult: (didPop, result) {
        if (widget.isMainAttributeSelection) setAllList();
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
            if (widget.isMainAttributeSelection) ...[
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
                  child: BlocBuilder<AttributeListCubit, AttributeListState>(
                    bloc: widget.attributeListCubit,
                    builder: (context, state) {
                      return dropdownListWidget(state);
                    },
                  )),
            ] else if (widget.mainAttribute != null) ...[
              mainItemWidget(widget.mainAttribute!),
              const SizedBox(height: 5),
            ]
          ]),
        ),
      ),
    );
  }

  dropdownListWidget(AttributeListState state) {
    if (state is AttributeListFetchProgress && state.isFirstFetch) {
      return Utils.loadingIndicator();
    } else if (state is AttributeListFetchFailure) {
      return Utils.msgWithTryAgain(
          context, state.errorMessage, () => loadPage(isSetInitialPage: true));
    }
    List<MainAttribute> attributelist = [];

    bool isLoading = false, isLoadMore = false;
    int offset = 0;
    if (state is AttributeListFetchProgress) {
      attributelist = state.oldAttributeList;
      offset = state.currOffset;
      isLoading = true;
    } else if (state is AttributeListFetchSuccess) {
      attributelist = state.attributeList;
      offset = state.currOffset;
      isLoadMore = state.isLoadmore;
    }

    if (_searchText.trim().isEmpty && attributelist.isNotEmpty) {
      currOffset = offset;
      loadedBrandlist = [];
      loadedBrandlist = attributelist;
      isloadmore = isLoadMore;
    }
    return ListView.separated(
      controller: scrollController,
      shrinkWrap: true,
      itemCount: attributelist.length + (isLoading ? 1 : 0),
      padding: const EdgeInsets.symmetric(vertical: 8),
      separatorBuilder: (context, index) {
        return Divider(
          color: grey,
          thickness: 1,
        );
      },
      itemBuilder: (context, index) {
        if (index < attributelist.length) {
          MainAttribute mainAttribute = attributelist[index];
          return mainItemWidget(mainAttribute);
        } else {
          Timer(const Duration(milliseconds: 30), () {
            scrollController.jumpTo(scrollController.position.maxScrollExtent);
          });

          return Utils.loadingIndicator();
        }
      },
    );
  }

  itemWidget(MainAttribute mainAttribute, {String? vid, String? value}) {
    if (!widget.isMainAttributeSelection && widget.selectedId!.contains(vid)) {
      selectedValues[vid!] = value!;
    }

    return GestureDetector(
      onTap: () {
        if (widget.isMainAttributeSelection) {
          widget.onAttributeSelect(mainAttribute, <String, String>{});
        } else {
          String? mremovedVid;
          if (selectedValues.containsKey(vid)) {
            mremovedVid = vid;
            selectedValues.remove(vid);
          } else {
            selectedValues[vid!] = value!;
          }

          widget.onAttributeSelect(mainAttribute, selectedValues,
              removedVid: mremovedVid);
        }
      },
      child: Row(
        mainAxisSize: MainAxisSize.max,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          if (!widget.isMainAttributeSelection) const SizedBox(width: 8),
          Expanded(
            flex: 1,
            child: Text(
              widget.isMainAttributeSelection
                  ? mainAttribute.name ?? ''
                  : value ?? '',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium!
                  .merge(TextStyle(color: black)),
            ),
          ),
          if (widget.selectedId!.contains(widget.isMainAttributeSelection
              ? mainAttribute.id.toString()
              : vid!))
            Icon(Icons.check, color: primaryColor),
        ],
      ),
    );
  }

  mainItemWidget(MainAttribute mainAttribute) {
    if (widget.isMainAttributeSelection) {
      return itemWidget(mainAttribute);
    } else if (mainAttribute.attributeValueMap == null ||
        mainAttribute.attributeValueMap!.isEmpty) {
      return const SizedBox.shrink();
    } else {
      List keys = mainAttribute.attributeValueMap!.keys.toList();

      return Wrap(
        runSpacing: 12,
        children: List.generate(
          mainAttribute.attributeValueMap!.length,
          (index) {
            String mkey = keys[index];
            return itemWidget(mainAttribute,
                vid: mkey, value: mainAttribute.attributeValueMap![mkey]);
          },
        ),
      );
    }
  }
}
