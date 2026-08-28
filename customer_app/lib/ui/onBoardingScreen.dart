import 'dart:async';

import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';
import 'package:value_market_customer/commons/widgets/customRoundedButton.dart';
import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:video_player/video_player.dart';

import '../core/routes/routes.dart';
import '../utils/utils.dart';

class OnBoardingScreen extends StatefulWidget {
  const OnBoardingScreen({super.key});

  static Widget getRouteInstance() => const OnBoardingScreen();

  @override
  State<OnBoardingScreen> createState() => _OnBoardingScreenState();
}

class _OnBoardingScreenState extends State<OnBoardingScreen> {
  VideoPlayerController? _videoPlayerController;
  late VoidCallback _videoListener;
  int _currentIndex = 0;
  final List<String> _videoUrls = [];
  final List<double> _progressValues = [];
  Timer? _timer;
  @override
  void initState() {
    super.initState();

    Future.delayed(Duration.zero).then((value) {
      final settings = context.read<SettingsAndLanguagesCubit>().getSettings();
      if (settings.systemSettings?.onBoardingMediaType == videoMediaType) {
        _videoUrls
            .addAll(settings.systemSettings?.onBoardingVideo as List<String>);

        _videoUrls.forEach((key) {
          _progressValues.addAll([0]);
        });
        _videoListener = () {
          setState(() {
            _progressValues[_currentIndex] =
                _videoPlayerController!.value.isInitialized
                    ? _videoPlayerController!.value.position.inMilliseconds /
                        _videoPlayerController!.value.duration.inMilliseconds
                    : 0;
          });

          if (_videoPlayerController!.value.position ==
              _videoPlayerController!.value.duration) {
            _onNextPressed();
          }
        };
        _initializePlayer(_videoUrls[_currentIndex]);
        _startTimer();
      }
    });
  }

  Future<void> _initializePlayer(String url) async {
    _videoPlayerController = VideoPlayerController.networkUrl(Uri.parse(url))
      ..initialize().then((_) {
        _videoPlayerController?.play();
        // Ensure the first frame is shown after the video is initialized, even before the play button has been pressed.
        _videoPlayerController?.addListener(_videoListener);
        setState(() {});
      });
  }

  void _onNextPressed() {
    if (_currentIndex < _videoUrls.length - 1) {
      setState(() {
        _currentIndex++;
        _videoPlayerController!.removeListener(_videoListener);
        _initializePlayer(_videoUrls[_currentIndex]);
      });
    } else {
      // Navigate to the next screen (e.g., home screen) after the last video
    }
  }

  void _startTimer() {
    if (_videoPlayerController != null) {
      _timer = Timer.periodic(Duration(microseconds: 5000), (timer) {
        if (_videoPlayerController!.value.isInitialized) {
          setState(() {
            _progressValues[_currentIndex] =
                _videoPlayerController!.value.position.inMilliseconds /
                    _videoPlayerController!.value.duration.inMilliseconds;
          });
        }
      });
    }
  }

  Widget _buildProgressIndicator() {
    return _videoPlayerController != null
        ? Row(
            children: List.generate(_videoUrls.length, (index) {
              return Expanded(
                child: Padding(
                  padding:
                      const EdgeInsetsDirectional.symmetric(horizontal: 4.0),
                  child: LinearProgressIndicator(
                    value: _progressValues[index],
                    minHeight: 1,
                    backgroundColor: Colors.white.withValues(alpha: 0.24),
                    borderRadius: BorderRadius.circular(3),
                    valueColor: AlwaysStoppedAnimation<Color>(
                        index <= _currentIndex
                            ? Colors.white
                            : Colors.white.withValues(alpha: 0.24)),
                  ),
                ),
              );
            }),
          )
        : const SizedBox.shrink();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _videoPlayerController?.removeListener(_videoListener);
    _videoPlayerController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final settings = context.read<SettingsAndLanguagesCubit>().getSettings();
    return Scaffold(
        body: Stack(
      children: [
        buildImageOrVideoList(),
        Positioned(
          top: MediaQuery.of(context).padding.top + 30,
          right: appContentHorizontalPadding,
          child: Align(
            alignment: Alignment.topRight,
            child: CustomRoundedButton(
              horizontalPadding: 2,
              widthPercentage: 0.25,
              buttonTitle: skipKey,
              showBorder: false,
              onTap: () => navigateToPage(Routes.loginScreen),
            ),
          ),
        ),
        Align(
          alignment: Alignment.bottomCenter,
          child: Padding(
            padding: const EdgeInsetsDirectional.only(
                start: appContentHorizontalPadding,
                end: appContentHorizontalPadding,
                bottom: 24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                if (settings.systemSettings?.onBoardingMediaType ==
                    videoMediaType) ...[
                  const SizedBox(height: 24),
                  _buildProgressIndicator(),
                  const SizedBox(height: 24),
                ],
                Row(
                  children: [
                    CustomRoundedButton(
                      widthPercentage: 0.44,
                      buttonTitle: registerKey,
                      showBorder: false,
                      onTap: () => navigateToPage(Routes.signupScreen),
                    ),
                    const Spacer(),
                    CustomRoundedButton(
                      widthPercentage: 0.44,
                      backgroundColor: Colors.transparent,
                      buttonTitle: signInKey,
                      borderColor: Theme.of(context).colorScheme.onPrimary,
                      showBorder: true,
                      onTap: () => navigateToPage(Routes.loginScreen),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ],
    ));
  }

  buildImageOrVideoList() {
    final settings = context.read<SettingsAndLanguagesCubit>().getSettings();
    if ((settings.systemSettings?.showVideosInOnBoardingScreen() ?? false)) {
      return Stack(
        children: [
          _videoPlayerController != null &&
                  _videoPlayerController!.value.isInitialized
              ? Container(
                  width: MediaQuery.of(context).size.width,
                  height: MediaQuery.of(context).size.height,
                  child: AspectRatio(
                    aspectRatio: 1 / 1,
                    child: VideoPlayer(_videoPlayerController!),
                  ),
                )
              : const Center(child: CircularProgressIndicator()),
          buildShadowContainer()
        ],
      );
    }
    if ((settings.systemSettings?.showImagesInOnBoardingScreen() ?? false)) {
      return PageView.builder(
          onPageChanged: (inndex) {},
          itemCount: settings.systemSettings?.onBoardingImage!.length,
          itemBuilder: (context, index) {
            return Stack(
              children: [
                CustomImageWidget(
                    width: MediaQuery.of(context).size.width,
                    height: MediaQuery.of(context).size.height,
                    boxFit: BoxFit.cover,
                    url: settings.systemSettings?.onBoardingImage![index]
                        as String),
                buildShadowContainer()
              ],
            );
          });
    }
    return Container();
  }

  buildShadowContainer() {
    return Container(
      width: MediaQuery.of(context).size.width,
      height: MediaQuery.of(context).size.height,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment(-0.00, -1.00),
          end: Alignment(0, 1),
          colors: [Color(0xFF201A1A), Color(0x00201A1A), Color(0xF9201A1A)],
        ),
      ),
    );
  }

  navigateToPage(String route) {
    context.read<SettingsAndLanguagesCubit>().changeShowOnBoardingScreen(false);
    Utils.navigateToScreen(context, route, replaceAll: true);
  }
}
