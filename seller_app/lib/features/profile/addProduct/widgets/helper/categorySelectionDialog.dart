import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/category.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../../../utils/designConfig.dart';


class CategorySelectionDialog extends StatefulWidget {
  final List<Category>? categorylist;
  final Function(List<Category>) onCategorySelect;
  final String? selectedId;
  final bool isMultiSelect;
  final List<String>? selectedIds; // New list for multi-select

  const CategorySelectionDialog({
    super.key,
    required this.categorylist,
    required this.onCategorySelect,
    this.selectedId,
    this.selectedIds,
    this.isMultiSelect = false, // Default is single select
  });

  @override
  State<StatefulWidget> createState() {
    return CategorySelectionDialogState();
  }
}

class CategorySelectionDialogState extends State<CategorySelectionDialog> {
  final TextEditingController searchQuery = TextEditingController();
  final FocusNode searchFocusNode = FocusNode();
  String searchText = "";
  bool isSearching = false;
  List<Category> orderlist = [];
  Set<String> selectedCategoryIds = {}; // For multi-selection

  @override
  void initState() {
    super.initState();
    isSearching = false;
    orderlist.addAll(widget.categorylist ?? []);

    // Initialize selected category IDs based on mode
    if (widget.isMultiSelect) {
      selectedCategoryIds.addAll(widget.selectedIds ?? []);
    }
  }

  searchCategoryFun() {
    if (searchQuery.text.trim().isEmpty) {
      isSearching = false;
      searchText = "";
    } else {
      isSearching = true;
      searchText = searchQuery.text;
    }

    orderlist.clear();
    if (searchText.trim().isEmpty) {
      orderlist.addAll(widget.categorylist ?? []);
    } else {
      for (Category element in (widget.categorylist ?? [])) {
        if (element.name.toLowerCase().contains(searchText.toLowerCase())) {
          orderlist.add(element);
        } else if (element.children!.isNotEmpty) {
          searchFilterList(element.children!);
        }
      }
    }
    setState(() {});
  }

  searchFilterList(List<Category> elementlist) {
    for (var childCategory in elementlist) {
      if (childCategory.name.toLowerCase().contains(searchText.toLowerCase())) {
        orderlist.add(childCategory);
      } else if (childCategory.children!.isNotEmpty) {
        searchFilterList(childCategory.children!);
      }
    }
  }

  void handleCategoryTap(Category category) {
    if (widget.isMultiSelect) {
      setState(() {
        if (selectedCategoryIds.contains(category.id.toString())) {
          selectedCategoryIds.remove(category.id.toString());
        } else {
          selectedCategoryIds.add(category.id.toString());
        }
      });
    } else {
      widget.onCategorySelect([category]);
    }
  }

  @override
  void dispose() {
    searchQuery.dispose();
    searchFocusNode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
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
          controller: searchQuery,
          onChanged: (s) => searchCategoryFun(),
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
        Expanded(
          child: ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(context).size.height * 0.6,
              ),
              child: orderlist.isEmpty
                  ? const Center(
                      child: CustomTextContainer(textKey: dataNotAvailableKey),
                    )
                  : dropdownListWidget()),
        ),
        const SizedBox(height: 5),
        if (widget.isMultiSelect)
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
                    List<Category> selectedCategories =
                        getSelectedCategories(widget.categorylist ?? []);
                    widget.onCategorySelect(selectedCategories);
                  },
                  child: const CustomTextContainer(textKey: applyKey))
            ],
          )
      ]),
    );
  }

  List<Category> getSelectedCategories(List<Category> categories) {
    List<Category> selected = [];

    for (var category in categories) {
      if (selectedCategoryIds.contains(category.id.toString())) {
        selected.add(category);
      }
      if (category.children != null && category.children!.isNotEmpty) {
        selected.addAll(getSelectedCategories(category.children!));
      }
    }

    return selected;
  }

  Widget buildTreeNode(Category node, {double isChildren = 0}) {
    List<Widget> listwidget = [];
    if (node.children!.isNotEmpty) {
      double spadding = isChildren + 10;
      for (var element in node.children!) {
        listwidget.add(buildTreeNode(element, isChildren: spadding));
      }
    }
    return Column(
      children: [
        categoryWidget(node, leadingGap: isChildren),
        ...listwidget,
      ],
    );
  }

  Widget dropdownListWidget() {
    return ListView(
      shrinkWrap: true,
      children: orderlist.map((node) => buildTreeNode(node)).toList(),
    );
  }

  Widget categoryWidget(Category category, {double leadingGap = 0}) {
    bool isSelected = widget.isMultiSelect
        ? selectedCategoryIds.contains(category.id.toString())
        : widget.selectedId == category.id.toString();

    return ListTile(
      onTap: () => handleCategoryTap(category),
      dense: true,
      contentPadding: EdgeInsetsDirectional.only(start: leadingGap),
      horizontalTitleGap: 0,
      title: Text(
        category.name,
      ),
      leading: Icon(
        widget.isMultiSelect
            ? (isSelected ? Icons.check_box : Icons.check_box_outline_blank)
            : (isSelected
                ? Icons.radio_button_checked
                : Icons.radio_button_off),
        size: 18,
        color: isSelected ? primaryColor : grey,
      ),
    );
  }
}
