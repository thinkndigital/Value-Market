import 'package:value_market_delivery_boy/commons/models/language.dart';
import 'package:value_market_delivery_boy/commons/models/settings.dart';
import 'package:value_market_delivery_boy/core/api/apiService.dart';
import 'package:value_market_delivery_boy/core/constants/hiveConstants.dart';
import 'package:value_market_delivery_boy/core/localization/labelKeys.dart';
import 'package:value_market_delivery_boy/core/api/apiEndPoints.dart';
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

  Future<Settings> getSettings() async {
    try {
      final result = await Api.get(url: ApiURL.getSettings, useAuthToken: true);
      return Settings.fromJson(result);
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

      return ((result['data'] ?? []) as List)
          .map((language) => Language.fromJson(language))
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

      return Map.from(result['data'] ?? {});
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}
