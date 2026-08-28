import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/ui/categoty/models/category.dart';
import 'package:eshop_plus/ui/explore/screens/exploreScreen.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:speech_to_text/speech_recognition_result.dart';
import 'package:speech_to_text/speech_to_text.dart';

class SubCategoryScreen extends StatefulWidget {
  final Category category;

  static Widget getRouteInstance() => SubCategoryScreen(
        category: Get.arguments['category'] as Category,
      );
  const SubCategoryScreen({Key? key, required this.category}) : super(key: key);

  @override
  State<SubCategoryScreen> createState() => _SubCategoryScreenState();
}

class _SubCategoryScreenState extends State<SubCategoryScreen> {
  TextEditingController _searchController = TextEditingController();
  FocusNode _searchFocusNode = FocusNode();
  late SpeechToText _speechToText;
  bool _isListening = false;
  bool _speechEnabled = false;
  bool _isSearchMode = false;
  late Size size;
  List<Category> catChildren = [], filteredChildren = [];
  String query = "";
  @override
  void initState() {
    super.initState();
    catChildren = widget.category.children ?? [];
    filteredChildren = catChildren; // Initialize with all children
  }

  /// Each time to start a speech recognition session
  void _startListening() async {
    if (_speechEnabled) {
      setState(() {
        _isListening = true;
      });
      await _speechToText.listen(onResult: _onSpeechResult);
    }
  }

  void _onSpeechResult(SpeechRecognitionResult result) {
    setState(() {
      _searchController.text = result.recognizedWords;
      updateSearch(_searchController.text);
    });
  }

  void _stopListening() {
    _speechToText.stop();
    setState(() => _isListening = false);
  }

  void _toggleSearchMode() {
    setState(() {
      _isSearchMode = !_isSearchMode;
      if (!_isSearchMode) {
        setState(() {
          _searchController.clear();
          updateSearch('');
        });
        _stopListening();
      } else {
        FocusScope.of(context).requestFocus(_searchFocusNode);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
          appBar: buildAppbar(context),
          body: filteredChildren.isNotEmpty
              ? Padding(
                  padding: const EdgeInsetsDirectional.symmetric(
                      horizontal: appContentHorizontalPadding),
                  child: SingleChildScrollView(
                    padding:
                        const EdgeInsetsDirectional.symmetric(vertical: 24),
                    child: Wrap(
                      crossAxisAlignment: WrapCrossAlignment.start,
                      direction: Axis.horizontal,
                      spacing: appContentHorizontalPadding,
                      runSpacing: appContentHorizontalPadding,
                      children: List.generate(
                          filteredChildren.length,
                          (index) =>
                              buildCategory(filteredChildren[index], context)),
                    ),
                  ),
                )
              : const Center(
                  child: CustomTextContainer(
                  textKey: noSubCategoriesFoundKey,
                ))),
    );
  }

  CustomAppbar buildAppbar(BuildContext context) {
    return CustomAppbar(
      titleKey: widget.category.name.capitalizeFirst!,
      showBackButton: true,
      leadingWidget: _isSearchMode || _isListening ? buildSearchField() : null,
      trailingWidget: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: Icon(_isSearchMode || _isListening
                ? Icons.close
                : Icons.search_outlined),
            onPressed: _toggleSearchMode,
          ),
          IconButton(
            icon: Icon(
              _isListening ? Icons.mic : Icons.mic_none,
              color: _isListening
                  ? Theme.of(context).colorScheme.primary
                  : Theme.of(context).colorScheme.secondary,
            ),
            onPressed: _isListening ? _stopListening : _startListening,
          ),
        ],
      ),
    );
  }

  buildSearchField() {
    return Container(
      width: MediaQuery.of(context).size.width * 0.7,
      height: 40,
      padding:
          const EdgeInsetsDirectional.only(start: appContentHorizontalPadding),
      child: TextFormField(
        controller: _searchController,
        autofocus: true,
        textAlignVertical: TextAlignVertical.center,
        focusNode: _searchFocusNode,
        decoration: InputDecoration(
          contentPadding: const EdgeInsetsDirectional.symmetric(horizontal: 15),
          hintText: context
              .read<SettingsAndLanguagesCubit>()
              .getTranslatedValue(labelKey: searchCategoryKey),
          hintStyle: Theme.of(context).textTheme.bodyMedium!.copyWith(
              color: Theme.of(context)
                  .colorScheme
                  .secondary
                  .withValues(alpha: 0.67)),
        ),
        onChanged: (value) {
          updateSearch(_searchController.text);
        },
      ),
    );
  }

  List<Category> filterChildren(String query, List<Category> children) {
    return children.where((child) {
      String name = child.name.toLowerCase();
      return name.contains(query.toLowerCase());
    }).toList();
  }

  void updateSearch(String newQuery) {
    setState(() {
      query = newQuery;
      filteredChildren = filterChildren(newQuery, catChildren);
    });
  }

  buildCategory(Category category, BuildContext context) {
    return SizedBox(
      width: (MediaQuery.of(context).size.width -
              appContentHorizontalPadding * 4) /
          3,
      height: 150,
      child: GestureDetector(
        onTap: () {
          if (category.children!.isEmpty) {
            Utils.navigateToScreen(context, Routes.exploreScreen,
                arguments: ExploreScreen.buildArguments(category: category));
          } else {
            Utils.navigateToScreen(
              context,
              Routes.subCategoryScreen,
              arguments: {
                'category': category,
              },
            );
          }
        },
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            CustomImageWidget(
              url: category.image,
              width: 108,
              height: 100,
              borderRadius: borderRadius,
            ),
            DesignConfig.smallHeightSizedBox,
            CustomTextContainer(
              textKey: category.name,
              style: Theme.of(context).textTheme.bodyMedium,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
            )
          ],
        ),
      ),
    );
  }
}
