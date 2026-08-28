import 'dart:async';
import 'dart:io';
import 'package:value_market_seller/commons/repositories/productRepository.dart';
import 'package:value_market_seller/commons/widgets/customImageWidget.dart';
import 'package:value_market_seller/commons/widgets/errorScreen.dart';
import 'package:value_market_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:value_market_seller/features/profile/addProduct/models/media.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import '../../../../../core/constants/appConstants.dart';
import '../../../../../core/localization/labelKeys.dart';
import '../../blocs/mediaListCubit.dart';
import 'package:flutter_staggered_grid_view/flutter_staggered_grid_view.dart';
import '../../../../../utils/utils.dart';
import '../../../../../commons/widgets/customAppbar.dart';

class MediaListPage extends StatefulWidget {
  final Function? onMediaSelect;
  final String? mediaType;
  final MediaListCubit mediaListCubit;
  final bool isMultipleSelect;

  const MediaListPage(
      {Key? key,
      this.onMediaSelect,
      this.mediaType,
      this.isMultipleSelect = false,
      required this.mediaListCubit})
      : super(key: key);

  static Widget getRouteInstance() {
    Map<String, dynamic> arguments = Get.arguments ?? {};
    return BlocProvider.value(
      value: arguments['mediaListCubit'] as MediaListCubit,
      child: MediaListPage(
        onMediaSelect: arguments['onMediaSelect'],
        mediaType: arguments['mediaType'],
        mediaListCubit: arguments['mediaListCubit'],
        isMultipleSelect: arguments['isMultipleSelect'],
      ),
    );
  }

  @override
  MediaListPageState createState() => MediaListPageState();
}

class MediaListPageState extends State<MediaListPage> {
  bool _isSearchMode = false;
  final TextEditingController _searchController = TextEditingController();
  FocusNode searchFocusNode = FocusNode();
  String? prevVal;
  Map<String, String> apiParameter = {};
  final scrollController = ScrollController();
  List<Media> loadedBrandlist = [];
  int currOffset = 0;
  bool isloadmore = true;
  List<File> newSelectedFilelist = [];
  Map<String, String> selectedPath = {};
  @override
  void initState() {
    super.initState();
    apiParameter = {};
    newSelectedFilelist = [];

    if (widget.mediaType != null && widget.mediaType!.trim().isNotEmpty) {
      apiParameter["type"] = widget.mediaType!;
    }
    setupScrollController(context);
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

  setAllList() {
    if (apiParameter.containsKey("search")) {
      apiParameter.remove("search");
    }
    widget.mediaListCubit.setOldList(currOffset, loadedBrandlist, isloadmore,
        (widget.mediaListCubit.state as MediaListFetchSuccess).mediatype);
  }

  loadPage({bool isSetInitialPage = false}) {
    Map<String, String> parameter = {};
    if (apiParameter.isNotEmpty) {
      parameter.addAll(apiParameter);
    }
    widget.mediaListCubit
        .getMediaList(context, parameter, isSetInitial: isSetInitialPage);
  }

  @override
  void dispose() {
    _searchController.dispose();
    searchFocusNode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      onPopInvokedWithResult: (didPop, result) {
        setAllList();
      },
      child: Scaffold(
        appBar: buildAppbar(),
        floatingActionButton: FloatingActionButton(
          onPressed: () {
            newSelectedFilelist.clear();
            openFileSelection();
          },
          child: const Icon(Icons.add),
        ),
        body: BlocBuilder<MediaListCubit, MediaListState>(
          builder: (context, state) {
            if (state is MediaListFetchProgress && state.isFirstFetch) {
              return Utils.loadingIndicator();
            } else if (state is MediaListFetchFailure) {
              return ErrorScreen(
                  onPressed: () => loadPage(isSetInitialPage: true),
                  text: state.errorMessage);
            }
            return RefreshIndicator(
                onRefresh: refreshList, child: listContent(state));
          },
        ),
      ),
    );
  }

  openFileSelection({bool isFromReselect = false}) {
    Utils.openFileExplorer(
            fileType: widget.mediaType == mediaTypeImage
                ? FileType.image
                : widget.mediaType == mediaTypeVideo
                    ? FileType.video
                    : null,
            isMultiple: true)
        .then((value) {
      if (value != null) {
        newSelectedFilelist.addAll(value as Iterable<File>);
      }
      if (value != null || isFromReselect) {
        Utils.openModalBottomSheet(
          context,
          fileUploadWidget(),
        );
      }
    });
  }

  Widget fileUploadWidget() {
    final typeNotifier = ValueNotifier<bool>(false);

    return FilterContainerForBottomSheet(
      title: selectMediaKey,
      borderedButtonTitle: selectMediaKey,
      primaryButtonTitle: uploadKey,
      borderedButtonOnTap: () {
        Navigator.of(context).pop();
        openFileSelection(isFromReselect: true);
      },
      primaryButtonOnTap: () {
        Navigator.of(context).pop();
        Utils.showLoader(context);
        ProductRepository()
            .uploadMedia(newSelectedFilelist, context, widget.mediaType ?? "")
            .then(
          (value) {
            Utils.hideLoader(context);
            loadedBrandlist.insertAll(0, value);
            widget.mediaListCubit.setOldList(
                currOffset,
                loadedBrandlist,
                isloadmore,
                (widget.mediaListCubit.state as MediaListFetchSuccess)
                    .mediatype);
          },
        ).catchError((e) {
          Utils.hideLoader(context);
          Utils.showSnackBar(message: e.toString());
        });
      },
      content: ValueListenableBuilder(
          valueListenable: typeNotifier,
          builder: (context, value, _) {
            return Column(
              children: List.generate(
                newSelectedFilelist.length,
                (index) {
                  File file = newSelectedFilelist[index];

                  return ListTile(
                    title: Text(
                      file.path.split("/").last,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    leading: mediaWidget(null, 25, mwidth: 25, file: file),
                    contentPadding: EdgeInsetsDirectional.only(
                        bottom: newSelectedFilelist.length == 1 ? 25 : 0,
                        top: newSelectedFilelist.length == 1 ? 25 : 0),
                    trailing: IconButton(
                        onPressed: () {
                          newSelectedFilelist.removeAt(index);
                          typeNotifier.value = !(typeNotifier.value);
                        },
                        icon: const Icon(Icons.delete)),
                  );
                },
              ),
            );
          }),
    );
  }

  listContent(MediaListState state) {
    List<Media> medialist = [];

    bool isLoading = false, isLoadMore = false;
    int offset = 0;
    if (state is MediaListFetchProgress) {
      medialist = state.oldMediaList;
      offset = state.currOffset;
      isLoading = true;
    } else if (state is MediaListFetchSuccess) {
      medialist = state.mediaList;
      offset = state.currOffset;
      isLoadMore = state.isLoadmore;
    }
    if (_searchController.text.trim().isEmpty && medialist.isNotEmpty) {
      currOffset = offset;
      loadedBrandlist = [];
      loadedBrandlist = medialist;
      isloadmore = isLoadMore;
    }
    return MasonryGridView.builder(
      controller: scrollController,
      gridDelegate: const SliverSimpleGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2),
      itemCount: medialist.length + (isLoading ? 1 : 0),
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      padding: const EdgeInsets.all(10),
      itemBuilder: (BuildContext context, int index) {
        if (index < medialist.length) {
          double itemHeight = (index % 3 == 0)
              ? 200
              : (index % 3 == 1)
                  ? 150
                  : 250;
          Media media = medialist[index];
          return GestureDetector(
            onTap: () {
              if (selectedPath.containsKey(media.relativePath)) {
                selectedPath.remove(media.relativePath);
              } else {
                selectedPath[media.relativePath ?? ""] = media.image ?? "";
              }
              setState(() {});

              if (!widget.isMultipleSelect) {
                doneSelection();
              }
            },
            child: Stack(children: [
              mediaWidget(media, itemHeight),
              PositionedDirectional(
                bottom: 0,
                start: 0,
                end: 0,
                child: Container(
                  padding: const EdgeInsetsDirectional.only(start: 5, end: 5),
                  color: Colors.black
                      .withValues(alpha: 0.1), // Semi-transparent Container
                  child: Text(
                    media.name ?? "",
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall!
                        .copyWith(color: Colors.black),
                  ),
                ),
              ),
              Icon(
                selectedPath.containsKey(media.relativePath)
                    ? Icons.radio_button_checked
                    : Icons.radio_button_off,
                color: Theme.of(context).colorScheme.primary,
              )
            ]),
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

  doneSelection() {
    if (widget.onMediaSelect != null) {
      widget.onMediaSelect!(selectedPath);
    }
    Navigator.of(context).pop();
  }

  mediaWidget(Media? media, double mheight,
      {double? mwidth, BoxFit? mboxfit, File? file}) {
    String filetype = "";
    if (file != null) {
      filetype = file.path.split('.').last;
      if (imagetypelist.contains(filetype)) {
        return Image.file(
          file,
          fit: mboxfit ?? BoxFit.fill,
          height: mheight,
          width: mwidth,
        );
      } else {
        Media media = Media();
        media.type = filetype;
        return otherMediaWidget(media, mheight,
            mwidth: mwidth, mboxfit: mboxfit);
      }
    } else if (media!.type == mediaTypeImage) {
      return CustomImageWidget(
        url: media.image ?? "",
        boxFit: mboxfit ?? BoxFit.fill,
        height: mheight,
        width: mwidth,
      );
    } else {
      return otherMediaWidget(media, mheight, mwidth: mwidth, mboxfit: mboxfit);
    }
  }

  otherMediaWidget(Media? media, double mheight,
      {double? mwidth, BoxFit? mboxfit}) {
    return Image(
        image: AssetImage(Utils.getImagePath(media!.type == mediaTypeAudio
            ? 'audio-file.png'
            : media.type == mediaTypeVideo
                ? 'video-file.png'
                : 'doc-file.png')),
        height: mheight,
        width: mwidth,
        fit: mboxfit ?? BoxFit.fill);
  }

  Future<void> refreshList() async {
    await Future.delayed(const Duration(seconds: 2), () {
      loadPage(isSetInitialPage: true);
    });
  }

  CustomAppbar buildAppbar() {
    return CustomAppbar(
      titleKey: selectMediaKey,
      showBackButton: _isSearchMode ? false : true,
      leadingWidget: _isSearchMode ? buildSearchField() : null,
      trailingWidget: Row(
        children: [
          IconButton(
            padding: EdgeInsets.zero,
            icon: Icon(_isSearchMode ? Icons.close : Icons.search_outlined),
            onPressed: _toggleSearchMode,
          ),
          if (!_isSearchMode && selectedPath.isNotEmpty)
            IconButton(
              padding: EdgeInsets.zero,
              icon: const Icon(Icons.done_all),
              onPressed: () {
                doneSelection();
              },
            ),
        ],
      ),
    );
  }

  buildSearchField() {
    return Utils.buildSearchField(
        context: context,
        controller: _searchController,
        focusNode: searchFocusNode,
        onChanged: (value) {
          searchChange(value);
        });
  }

  void _toggleSearchMode() {
    setState(() {
      _isSearchMode = !_isSearchMode;
      if (!_isSearchMode) {
        setState(() {
          _searchController.clear();
          searchChange("");
        });
      } else {
        FocusScope.of(context).requestFocus(searchFocusNode);
      }
    });
  }

  searchChange(String val) {
    if (val.trim().isEmpty) {
      setAllList();
      return;
    }
    apiParameter["search"] = val;
    loadPage(isSetInitialPage: true);
  }
}
