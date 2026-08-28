import 'package:eshop_plus/main.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/ui/search/blocs/searchProductCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/ui/search/models/searchedProduct.dart';
import 'package:eshop_plus/commons/product/repositories/productRepository.dart';
import 'package:eshop_plus/ui/explore/screens/exploreScreen.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:speech_to_text/speech_recognition_error.dart';
import 'package:speech_to_text/speech_recognition_result.dart';
import 'package:speech_to_text/speech_to_text.dart';

class SpeechToTextIcon extends StatefulWidget {
  final SpeechToText speechToText;
  final Function callback;
  final StateSetter setState;
  const SpeechToTextIcon(
      {Key? key,
      required this.speechToText,
      required this.callback,
      required this.setState})
      : super(key: key);

  @override
  _SpeechToTextIconState createState() => _SpeechToTextIconState();
}

class _SpeechToTextIconState extends State<SpeechToTextIcon> {
  bool _isListening = false;
  String _currentLocaleId = '';
  bool _speechEnabled = false;
  String _lastWords = '';

  /// This has to happen only once per app
  Future<bool> _initSpeech() async {
    try {
      var speechEnabled = await widget.speechToText
          .initialize(onError: errorListener, onStatus: statusListener);
      if (_speechEnabled) {
        var systemLocale = await widget.speechToText.systemLocale();
        _currentLocaleId = systemLocale?.localeId ?? '';
      }
      if (!mounted) return false;

      setState(() {
        _speechEnabled = speechEnabled;
      });
      setState(() {});
    } catch (e) {
      setState(() {
        debugPrint('Speech recognition failed: ${e.toString()}');
        _speechEnabled = false;
      });
    }
    return _speechEnabled;
  }

  void errorListener(SpeechRecognitionError error) {
    _stopListening();
    if (mounted) {
      Utils.showSnackBar(
          message: 'Speech recognition failed. Please try again',
          context: navigatorKey.currentContext!);
    }

    debugPrint(
        'Received error status: $error, listening: ${widget.speechToText.isListening}');
  }

  void statusListener(String status) {
    debugPrint(
        'Received listener status: $status, listening: ${widget.speechToText.isListening}');
  }

  /// Each time to start a speech recognition session
  void _startListening() async {
    if (!_speechEnabled) {
      await _initSpeech();
    }
    FocusScope.of(context).unfocus();
    bool status = await Utils.requestMicrophonePermission(context);
    if (status) {
      setState(() {
        _isListening = true;
      });

      if (_speechEnabled) {
        try {
          await widget.speechToText.listen(
            onResult: resultListener,
            listenFor: const Duration(seconds: 30),
            pauseFor: const Duration(seconds: 5),
            listenOptions: SpeechListenOptions(
                partialResults: true,
                cancelOnError: true,
                listenMode: ListenMode.confirmation),
            localeId: _currentLocaleId,
            onSoundLevelChange: _onSoundLevelChange,
          );
        } on ListenFailedException catch (e) {
          throw ListenFailedException(e.message, e.details);
        }
      }
    }
  }

  /// This callback is invoked each time new recognition results are
  /// available after `listen` is called.
  void resultListener(SpeechRecognitionResult result) {
    debugPrint(
        'Result listener final: ${result.finalResult}, words: ${result.recognizedWords}');
    if (mounted)
      setState(() {
        _lastWords = result.recognizedWords;
      });
    if (_lastWords.isNotEmpty) {
      widget.callback(_lastWords);
      getProducts(search: _lastWords);
    }
  }

  /// Restart listening when silent
  void _onSoundLevelChange(double level) {
    if (level < 0.2 && widget.speechToText.isNotListening) {
      _startListening();
    }
  }

  void _stopListening() {
    widget.speechToText.stop();
    widget.setState(() => _isListening = false);
  }

  getProducts({required String search}) {
    context.read<SearchProductCubit>().searchProducts(
        storeId: context.read<StoresCubit>().getDefaultStore().id!,
        query: search);
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<SearchProductCubit, SearchProductState>(
      listener: (context, state) {
        if (state is SearchProductFetchSuccess && _isListening) {
          _stopListening();
          ProductRepository().addSearchInLocalHistory(_lastWords);
          navigatoToExploreScreen(state);
        }
        if (state is SearchProductFetchFailure) {
          if (_isListening) {
            _stopListening();
          }
        }
      },
      builder: (context, state) {
        return IconButton(
          padding: EdgeInsets.zero,
          icon: Icon(
            _isListening ? Icons.mic : Icons.mic_none,
            color: _isListening
                ? Theme.of(context).colorScheme.primary
                : Theme.of(context).colorScheme.secondary,
          ),
          onPressed: _isListening ? _stopListening : _startListening,
        );
      },
    );
  }

  navigatoToExploreScreen(SearchProductFetchSuccess state) {
    List<SearchedProduct> regularProducts = state.searchProducts
        .where((element) => element.type == 'products')
        .toList();
    List<SearchedProduct> comboProducts = state.searchProducts
        .where((element) => element.type == 'combo_products')
        .toList();

    Utils.navigateToScreen(
      navigatorKey.currentContext!,
      Routes.exploreScreen,
      arguments: ExploreScreen.buildArguments(
          title: _lastWords,
          productIds: regularProducts.map((e) => e.productId!).toList(),
          comboProductIds: comboProducts.isNotEmpty
              ? comboProducts.map((e) => e.productId!).toList()
              : [],
          fromSearchScreen: true),
      preventDuplicates: false,
    );
  }
}
