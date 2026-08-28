import 'package:eshopplus_seller/commons/models/language.dart';
import 'package:eshopplus_seller/commons/models/settings.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/core/constants/hiveConstants.dart';
import 'package:eshopplus_seller/features/auth/repositories/authRepository.dart';
import 'package:eshopplus_seller/utils/utils.dart';

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
    Hive.box(settingsBoxKey).put(showOnBoardingScreenKey, value);
  }

  bool getOnBoardingScreen() {
    return Hive.box(settingsBoxKey).get(showOnBoardingScreenKey) ?? true;
  }

  Future<Settings> getSettings({String? userId}) async {
    try {
      final result = await Api.get(
          url: ApiURL.getSettings,
          useAuthToken: true,
          queryParameters: {
            if (AuthRepository.getUserId() != 0)
              ApiURL.userIdApiKey: AuthRepository.getUserId().toString()
          });
      return Settings.fromJson(Map.from(result[ApiURL.dataKey] ?? {}));
    } catch (e) {
      return Utils.throwApiException(e);
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
      return Utils.throwApiException(e);
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
      return Utils.throwApiException(e);
    }
  }
}
