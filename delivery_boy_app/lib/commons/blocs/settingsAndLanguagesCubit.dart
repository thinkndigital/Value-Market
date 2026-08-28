
import 'package:eshoppro_deliveryboy/core/localization/defaultLanguageTranslatedValues.dart';
import 'package:eshoppro_deliveryboy/commons/models/language.dart';
import 'package:eshoppro_deliveryboy/commons/models/settings.dart';
import 'package:eshoppro_deliveryboy/commons/repositories/settingsRepository.dart';
import 'package:eshoppro_deliveryboy/main.dart';
import 'package:eshoppro_deliveryboy/core/configs/appConfig.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SettingsAndLanguagesState {}

class SettingsAndLanguagesInitial extends SettingsAndLanguagesState {}

class SettingsAndLanguagesFetchInProgress extends SettingsAndLanguagesState {}

class SettingsAndLanguagesFetchSuccess extends SettingsAndLanguagesState {
  final Language currentAppLanguage;
  final List<Language> languages;
  final Settings settings;
  final Map<String, String> currentLanguageTranslatedValues;

  SettingsAndLanguagesFetchSuccess(
      {required this.currentAppLanguage,
      required this.languages,
      required this.settings,
      required this.currentLanguageTranslatedValues});

  SettingsAndLanguagesFetchSuccess copyWith(
      {Language? currentAppLanguage,
      List<Language>? languages,
      Settings? settings,
      Map<String, String>? currentLanguageTranslatedValues}) {
    return SettingsAndLanguagesFetchSuccess(
      currentLanguageTranslatedValues: currentLanguageTranslatedValues ??
          this.currentLanguageTranslatedValues,
      currentAppLanguage: currentAppLanguage ?? this.currentAppLanguage,
      languages: languages ?? this.languages,
      settings: settings ?? this.settings,
    );
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
      Language currentAppLanguage = _settingsRepository.getCurrentAppLanguage();
      if (languages.indexWhere((e) =>
              e.code == _settingsRepository.getCurrentAppLanguage().code) !=
          -1) {
        currentAppLanguage = languages.firstWhere(
            (e) => e.code == _settingsRepository.getCurrentAppLanguage().code);
      } else {
        currentAppLanguage = languages.first;
      }
      emit(SettingsAndLanguagesFetchSuccess(
          currentAppLanguage: currentAppLanguage,
          languages: languages,
          settings: await _settingsRepository.getSettings(),
          currentLanguageTranslatedValues: (currentAppLanguage.code != null &&
                  currentAppLanguage.code != 'en')
              ? await _settingsRepository
                  .getLanguageLables(currentAppLanguage.code!)
              : defaultLanguageTranslatedValues));
      if (getCurrentAppLanguage().code == null) {
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
              .deliveryBoyAppMaintenanceStatus! ==
          1;
    }
    return false;
  }

  Future<void> changeLanguage(Language currentAppLanguage) async {
    _settingsRepository.setCurrentAppLanguage(currentAppLanguage);
    SettingsAndLanguagesFetchSuccess settingsAndLanguagesFetchSuccess =
        (state as SettingsAndLanguagesFetchSuccess);

    emit((settingsAndLanguagesFetchSuccess).copyWith(
        languages: settingsAndLanguagesFetchSuccess.languages,
        settings: settingsAndLanguagesFetchSuccess.settings,
        currentAppLanguage: currentAppLanguage,
        currentLanguageTranslatedValues:
            (_settingsRepository.getCurrentAppLanguage().code != null &&
                    _settingsRepository.getCurrentAppLanguage().code != 'en')
                ? await _settingsRepository.getLanguageLables(
                    _settingsRepository.getCurrentAppLanguage().code!)
                : defaultLanguageTranslatedValues));
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

  Settings getSettings() {
    if (state is SettingsAndLanguagesFetchSuccess) {
      return (state as SettingsAndLanguagesFetchSuccess).settings;
    }
    return Settings.fromJson({});
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
                    .currentVersionOfAndroidAppForDeliveryBoy!) ||
            defaultTargetPlatform == TargetPlatform.iOS &&
                needsUpdate((state as SettingsAndLanguagesFetchSuccess)
                    .settings
                    .systemSettings!
                    .currentVersionOfIosAppForDeliveryBoy!)) {
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
}
