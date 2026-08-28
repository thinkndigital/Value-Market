import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:video_player/video_player.dart';
import 'package:vimeo_player_flutter/vimeo_player_flutter.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';

class MediaViewer extends StatefulWidget {
  final List<String> imageUrls;
  final String? videoUrl;
  final String? videoType;

  final bool isFullScreen;
  MediaViewer(
      {required this.imageUrls,
      this.videoUrl,
      this.videoType,
      this.isFullScreen = false});

  @override
  _MediaViewerState createState() => _MediaViewerState();
}

class _MediaViewerState extends State<MediaViewer> {
  late bool isProductStyle1 = true;
  VideoPlayerController? _videoController;
  YoutubePlayerController? _youtubeController;
  int _selectedIndex = 0; // Index of the currently displayed image
  bool _isVideoPlaying = false;
  late PageController _pageController;
  bool _isPlayerReady = false;
  late VoidCallback _videoListener;
  @override
  void initState() {
    super.initState();

    _pageController = PageController(initialPage: _selectedIndex);
    if (widget.videoUrl != '') {
      if (widget.videoType == selfHostedType) {
        _videoController = VideoPlayerController.networkUrl(
          Uri.parse(
            widget.videoUrl!,
          ),
          videoPlayerOptions: VideoPlayerOptions(
            mixWithOthers: true,
          ),
        )..initialize().then((_) {
            // Ensure the first frame is shown after the video is initialized, even before the play button has been pressed.
            _videoController?.addListener(_videoListener);
          });
        _videoListener = () {
          if (_videoController!.value.position ==
              _videoController!.value.duration) {
            _isVideoPlaying = false;
          }
        };
      } else if (widget.videoType == youtubeVideoType) {
        _youtubeController = YoutubePlayerController(
          initialVideoId:
              YoutubePlayer.convertUrlToId(widget.videoUrl ?? '') ?? '',
          flags: const YoutubePlayerFlags(
              mute: false,
              hideControls: false,
              controlsVisibleAtStart: true,
              autoPlay: true,
              hideThumbnail: false,
              enableCaption: true,
              loop: false,
              useHybridComposition: false,
              disableDragSeek: true),
        )..addListener(() {
            if (_youtubeController!.value.isReady && !_isPlayerReady) {
              setState(() {
                _isPlayerReady = true;
              });
            }
          });
        ;
      }
    }
  }

  @override
  void dispose() {
    if (_videoController != null) {
      _videoController!.dispose();
    }
    if (_youtubeController != null) {
      _youtubeController!.dispose();
    }
    super.dispose();
  }

  @override
  void deactivate() {
    // Pauses video while navigating to next page.
    if (_youtubeController != null) _youtubeController!.pause();
    if (_videoController != null) {
      _videoController!.pause();
    }
    super.deactivate();
  }

  @override
  Widget build(BuildContext context) {
    isProductStyle1 = context
            .read<StoresCubit>()
            .getDefaultStore()
            .storeSettings!
            .productStyle ==
        'style_1';
    if (widget.isFullScreen) {
      if (widget.imageUrls[_selectedIndex] == 'video' &&
          widget.videoUrl != '') {
        if (widget.videoType == youtubeVideoType) {
          return SizedBox(
            height: MediaQuery.of(context).size.height,
            width: MediaQuery.of(context).size.width,
            child: Center(
              child: buildYoutubeVideo(context),
            ),
          );
        }
      }
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      mainAxisAlignment: MainAxisAlignment.start,
      children: <Widget>[
        SizedBox(
          height: MediaQuery.of(context).size.width -
              appContentHorizontalPadding * 2,
          child: Stack(
            clipBehavior: Clip.none,
            alignment: Alignment.bottomCenter,
            children: [
              PageView.builder(
                controller: _pageController,
                clipBehavior: Clip.none,
                onPageChanged: (index) => onChangeVideo(index),
                itemCount: widget.imageUrls.length,
                itemBuilder: (context, index) {
                  if (widget.imageUrls[_selectedIndex] == 'video' &&
                      widget.videoUrl != '') {
                    if (widget.videoType == youtubeVideoType) {
                      return SizedBox(
                        height: MediaQuery.of(context).size.height * 0.3,
                        child: buildYoutubeVideo(context),
                      );
                    } else if (widget.videoType == 'vimeo') {
                      List<String> id =
                          widget.videoUrl!.split('https://vimeo.com/');
                      return AspectRatio(
                        aspectRatio: 16 / 9,
                        child: VimeoPlayer(
                          videoId: id[1],
                        ),
                      );
                    } else {
                      return _videoController!.value.isInitialized
                          ? SafeArea(
                              child: Center(
                              child: GestureDetector(
                                onTap: () {
                                  setState(() {
                                    _isVideoPlaying
                                        ? _videoController!.pause()
                                        : _videoController!.play();
                                    _isVideoPlaying = !_isVideoPlaying;
                                  });
                                },
                                child: SizedBox(
                                  height:
                                      MediaQuery.of(context).size.height / 3,
                                  width: _videoController!.value.size.width,
                                  child: Stack(
                                    alignment: Alignment.bottomCenter,
                                    children: <Widget>[
                                      VideoPlayer(
                                        _videoController!,
                                      ),
                                      VideoProgressIndicator(
                                        _videoController!,
                                        allowScrubbing: true,
                                      ),
                                      if (!_isVideoPlaying)
                                        const Center(
                                          child: Icon(
                                            Icons.play_circle_outline,
                                            size: 64.0,
                                            color: Colors.white,
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                              ),
                            ))
                          : CustomCircularProgressIndicator(
                              indicatorColor:
                                  Theme.of(context).colorScheme.primary,
                            );
                    }
                  } else {
                    return GestureDetector(
                      onTap: () => Utils.navigateToScreen(
                          context, Routes.fullScreenImageScreen,
                          arguments: {
                            'imageUrl': widget.imageUrls[_selectedIndex],
                            'index': _selectedIndex,
                            'imageUrls': widget.imageUrls
                                .where((element) => element != 'video')
                                .toList(),
                          }),
                      child: Stack(
                        clipBehavior: Clip.none,
                        children: [
                          CustomImageWidget(
                            height: MediaQuery.of(context).size.width,
                            width: MediaQuery.of(context).size.width,
                            url: widget.imageUrls[index],
                            boxFit: BoxFit.cover,
                            borderRadius: borderRadius,
                          ),
                        ],
                      ),
                    );
                  }
                },
              ),
              if (isProductStyle1)
                Positioned(
                  bottom: -13,
                  child: AnimatedContainer(
                    height: 26,
                    alignment: Alignment.center,
                    duration: const Duration(seconds: 2),
                    padding: const EdgeInsetsDirectional.symmetric(
                        horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primary,
                      border: Border.all(color: Colors.white, width: 2),
                      borderRadius: BorderRadius.circular(100),
                    ),
                    child: CustomTextContainer(
                      textKey:
                          '${_selectedIndex + 1} / ${widget.imageUrls.length}',
                      style: Theme.of(context).textTheme.bodySmall!.copyWith(
                          color: Theme.of(context).colorScheme.onPrimary),
                    ),
                  ),
                ),
            ],
          ),
        ),
        if (!isProductStyle1)
          SizedBox(
            height: 100,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: widget.imageUrls.length,
              itemBuilder: (context, index) {
                return GestureDetector(
                  onTap: () => onChangeVideo(index),
                  child: widget.imageUrls[index] == 'video'
                      ? Container(
                          width: 80,
                          height: 80,
                          margin: const EdgeInsets.all(8.0),
                          alignment: Alignment.center,
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.2),
                          child: const Icon(Icons.play_arrow_rounded,
                              size: 50, color: Colors.white),
                        )
                      : Container(
                          margin: const EdgeInsets.all(8.0),
                          decoration: BoxDecoration(
                            border: Border.all(
                              color: _selectedIndex == index
                                  ? Theme.of(context).colorScheme.primary
                                  : Theme.of(context)
                                      .inputDecorationTheme
                                      .iconColor!,
                              width: _selectedIndex == index ? 1.5 : 0.5,
                            ),
                          ),
                          child: CustomImageWidget(
                            url: widget.imageUrls[index],
                            width: 80,
                            height: 80,
                            borderRadius: 2,
                          ),
                        ),
                );
              },
            ),
          ),
      ],
    );
  }

  buildYoutubeVideo(BuildContext context) {
    return SizedBox(
      width: double.maxFinite,
      child: YoutubePlayer(
        controller: _youtubeController!,
        showVideoProgressIndicator: true,
        progressIndicatorColor: Theme.of(context).colorScheme.primary,
        liveUIColor: Theme.of(context).colorScheme.onPrimary,
        progressColors: ProgressBarColors(
            backgroundColor: Colors.white.withValues(alpha: 0.5),
            playedColor: Theme.of(context).colorScheme.primary,
            handleColor: Theme.of(context).colorScheme.primary),
        onReady: () {
          setState(() {
            _isPlayerReady = true;
          });
        },
        onEnded: (metaData) {
          _youtubeController!.reload();
        },
      ),
    );
  }

  onChangeVideo(int index) {
    _selectedIndex = index; // Update main image
    _pageController.jumpToPage(index);
    if (widget.imageUrls[index] == 'video') {
    } else {
      if (_videoController != null && _videoController!.value.isPlaying) {
        _videoController!.pause();
        setState(() {
          _isVideoPlaying = false;
        });
      }
      if (_youtubeController != null && _youtubeController!.value.isPlaying) {
        _youtubeController!.pause();
      }
    }
    setState(() {});
  }
}
