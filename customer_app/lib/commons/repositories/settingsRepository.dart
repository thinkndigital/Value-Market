import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:eshop_plus/commons/models/language.dart';
import 'package:eshop_plus/commons/models/settings.dart';

import 'package:eshop_plus/core/localization/labelKeys.dart';

import 'package:hive_flutter/hive_flutter.dart';

class SettingsRepository {
  Future<void> setCurrentAppLanguage(Language value) async {
    try {
      await Hive.box(settingsBoxKey).put(currentAppLanguageKey, value.toJson());
    } catch (e) {}
  }

  Language getCurrentAppLanguage() {
    try {
      final languageValue = Hive.box(settingsBoxKey).get(currentAppLanguageKey);

      return Language.fromJson(Map.from(languageValue ?? {}));
    } catch (e) {
      return Language.fromJson({});
    }
  }

  Future<void> setOnBoardingScreen(bool value) async {
    Hive.box(authBoxKey).put(showOnBoardingScreenKey, value);
  }

  bool getOnBoardingScreen() {
    return Hive.box(authBoxKey).get(showOnBoardingScreenKey) ?? true;
  }

  Future<void> setFirstTimeUser(bool value) async {
    Hive.box(authBoxKey).put(isFirstTimeUserKey, value);
  }

  bool getFirstTimeUser() {
    return Hive.box(authBoxKey).get(isFirstTimeUserKey) ?? true;
  }

  Future<Settings> getSettings() async {
    try {
      final result =
          await Api.get(url: ApiURL.getSettings, useAuthToken: false);

      return Settings.fromJson(Map.from(result[ApiURL.dataKey] ?? {}));
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<List<Language>> getLanguages() async {
    try {
      final result =
          await Api.get(url: ApiURL.getLanguages, useAuthToken: false);

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((language) => Language.fromJson(Map.from(language ?? {})))
          .toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<Map<String, String>> getLanguageLables(String languageCode) async {
    try {
      final result = await Api.get(
          url: ApiURL.getLanguageLabels,
          queryParameters: {ApiURL.languageCodeApiKey: languageCode},
          useAuthToken: false);

      return Map.from(result[ApiURL.dataKey] ?? {});
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}
