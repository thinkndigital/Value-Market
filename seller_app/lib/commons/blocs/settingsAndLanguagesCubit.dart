import 'package:eshopplus_seller/commons/repositories/settingsRepository.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/localization/defaultLanguageTranslatedValues.dart';
import 'package:eshopplus_seller/features/auth/repositories/authRepository.dart';
import 'package:eshopplus_seller/main.dart';
import 'package:eshopplus_seller/commons/models/language.dart';
import 'package:eshopplus_seller/commons/models/settings.dart';

import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SettingsAndLanguagesState {}

class SettingsAndLanguagesInitial extends SettingsAndLanguagesState {}

class SettingsAndLanguagesFetchInProgress extends SettingsAndLanguagesState {}

class SettingsAndLanguagesFetchSuccess extends SettingsAndLanguagesState {
  final Language currentAppLanguage;
  final bool showOnBoardingScreen;
  final List<Language> languages;
  final Settings settings;
  final Map<String, String> currentLanguageTranslatedValues;

  SettingsAndLanguagesFetchSuccess(
      {required this.currentAppLanguage,
      required this.showOnBoardingScreen,
      required this.languages,
      required this.settings,
      required this.currentLanguageTranslatedValues});

  SettingsAndLanguagesFetchSuccess copyWith(
      {Language? currentAppLanguage,
      bool? showOnBoardingScreen,
      List<Language>? languages,
      Settings? settings,
      Map<String, String>? currentLanguageTranslatedValues}) {
    return SettingsAndLanguagesFetchSuccess(
        currentLanguageTranslatedValues: currentLanguageTranslatedValues ??
            this.currentLanguageTranslatedValues,
        currentAppLanguage: currentAppLanguage ?? this.currentAppLanguage,
        languages: languages ?? this.languages,
        settings: settings ?? this.settings,
        showOnBoardingScreen:
            showOnBoardingScreen ?? this.showOnBoardingScreen);
  }
}

class SettingsAndLanguagesFetchFailure extends SettingsAndLanguagesState {
  final String errorMessage;

  SettingsAndLanguagesFetchFailure(this.errorMessage);
}

class SettingsAndLanguagesCubit extends Cubit<SettingsAndLanguagesState> {
  final SettingsRepository _settingsRepository;

  SettingsAndLanguagesCubit(this._settingsRepository)
      : super(SettingsAndLanguagesInitial());

  void fetchSettingsAndLanguages() async {
    try {
      emit(SettingsAndLanguagesFetchInProgress());
      List<Language> languages = await _settingsRepository.getLanguages();

      emit(SettingsAndLanguagesFetchSuccess(
          currentAppLanguage: _settingsRepository.getCurrentAppLanguage(),
          showOnBoardingScreen: _settingsRepository.getOnBoardingScreen(),
          languages: languages,
          settings: await _settingsRepository.getSettings(),
          currentLanguageTranslatedValues:
              (_settingsRepository.getCurrentAppLanguage().code != null &&
                      _settingsRepository.getCurrentAppLanguage().code !=
                          englishLangCode)
                  ? await _settingsRepository.getLanguageLables(
                      _settingsRepository.getCurrentAppLanguage().code!)
                  : defaultLanguageTranslatedValues));
      if (_settingsRepository.getCurrentAppLanguage().code == null) {
        changeLanguage(languages
            .firstWhere((element) => element.code == defaultLanguageCode));
      }
    } catch (e) {
      emit(SettingsAndLanguagesFetchFailure(e.toString()));
    }
  }

  bool appUnderMaintenance() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      return (state as SettingsAndLanguagesFetchSuccess)
              .settings
              .systemSettings!
              .sellerAppMaintenanceStatus ==
          1;
    }
    return false;
  }

  Future<void> changeLanguage(Language currentAppLanguage) async {
    _settingsRepository.setCurrentAppLanguage(currentAppLanguage);
    emit((state as SettingsAndLanguagesFetchSuccess).copyWith(
        currentAppLanguage: currentAppLanguage,
        currentLanguageTranslatedValues:
            (_settingsRepository.getCurrentAppLanguage().code != null &&
                    _settingsRepository.getCurrentAppLanguage().code != 'en')
                ? await _settingsRepository.getLanguageLables(
                    _settingsRepository.getCurrentAppLanguage().code!)
                : defaultLanguageTranslatedValues));
  }

  void changeShowOnBoardingScreen(bool showOnBoardingScreen) {
    _settingsRepository.setOnBoardingScreen(showOnBoardingScreen);
    emit((state as SettingsAndLanguagesFetchSuccess)
        .copyWith(showOnBoardingScreen: showOnBoardingScreen));
  }

  String getTranslatedValue({required String labelKey}) {
    if (state is SettingsAndLanguagesFetchSuccess) {
      return ((state as SettingsAndLanguagesFetchSuccess)
              .currentLanguageTranslatedValues[labelKey]) ??
          (defaultLanguageTranslatedValues[labelKey] ?? labelKey);
    }

    return (defaultLanguageTranslatedValues[labelKey] ?? labelKey);
  }

  Language getCurrentAppLanguage() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      return (state as SettingsAndLanguagesFetchSuccess).currentAppLanguage;
    }
    return Language.fromJson({});
  }

  bool getshowOnBoardingScreen() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      return (state as SettingsAndLanguagesFetchSuccess).showOnBoardingScreen;
    }
    return true;
  }

  Settings getSettings() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      return (state as SettingsAndLanguagesFetchSuccess).settings;
    }
    return Settings.fromJson({});
  }

  String getPusherAppKey() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      return (state as SettingsAndLanguagesFetchSuccess)
              .settings
              .pusherSettings!
              .pusherAppKey ??
          '';
    }
    return '';
  }

  String getPusherCluster() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      return (state as SettingsAndLanguagesFetchSuccess)
              .settings
              .pusherSettings!
              .pusherAppCluster ??
          '';
    }
    return '';
  }

  String getPusherChannerName() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      String channelName = (state as SettingsAndLanguagesFetchSuccess)
              .settings
              .pusherSettings!
              .pusherChannelName ??
          '';
      return '$channelName.${AuthRepository.getUserId()}';
    }
    return '';
  }

  bool isUpdateRequired() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      if ((state as SettingsAndLanguagesFetchSuccess)
              .settings
              .systemSettings!
              .versionSystemStatus ==
          1) {
        if (defaultTargetPlatform == TargetPlatform.android &&
                needsUpdate((state as SettingsAndLanguagesFetchSuccess)
                    .settings
                    .systemSettings!
                    .currentVersionOfAndroidAppForSeller!) ||
            defaultTargetPlatform == TargetPlatform.iOS &&
                needsUpdate((state as SettingsAndLanguagesFetchSuccess)
                    .settings
                    .systemSettings!
                    .currentVersionOfIosAppForSeller!)) {
          return true;
        }
      } else {
        return false;
      }
    }
    return false;
  }

  bool needsUpdate(String enforceVersion) {
    final List<int> currentVersion = packageInfo.version
        .split('.')
        .map((String number) => int.parse(number))
        .toList();
    final List<int> enforcedVersion = enforceVersion
        .split('.')
        .map((String number) => int.parse(number))
        .toList();

    for (int i = 0; i < 3; i++) {
      if (enforcedVersion[i] > currentVersion[i]) {
        return true;
      } else if (currentVersion[i] > enforcedVersion[i]) {
        return false;
      }
    }
    return false;
  }

  List<Language> getLanguages() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      return (state as SettingsAndLanguagesFetchSuccess).languages;
    }
    return [];
  }

  fetchSettings(String userId) async {
    emit((state as SettingsAndLanguagesFetchSuccess).copyWith(
        settings: await _settingsRepository.getSettings(userId: userId)));
  }
}
