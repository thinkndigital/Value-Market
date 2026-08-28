import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/core/configs/appConfig.dart';
import 'package:eshop_plus/ui/profile/address/models/address.dart';
import 'package:eshop_plus/ui/profile/address/models/city.dart';
import 'package:eshop_plus/ui/profile/address/models/zipcode.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';

class AddressRepository {
  Future<List<Address>> getAddress() async {
    try {
      final result = await Api.get(
          url: ApiURL.getAddress, useAuthToken: true, queryParameters: {});

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((address) => Address.fromJson(Map.from(address ?? {})))
          .toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<({Address address, String successMessage})> addAddress(
      {required Map<String, dynamic> params}) async {
    try {
      Map<String, dynamic> result;
      if (params.containsKey(ApiURL.idApiKey)) {
        result = await Api.put(
            queryParameters: params,
            url: ApiURL.updateAddress,
            useAuthToken: true);
      } else {
        result = await Api.post(
            body: params, url: ApiURL.addAddress, useAuthToken: true);
      }

      return (
        successMessage: result['message'].toString(),
        address: Address.fromJson(Map.from(result[ApiURL.dataKey][0] ?? {}))
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<
      ({
        List<City> citylist,
        int total,
      })> getCities({int? offset, String? search}) async {
    try {
      final result = await Api.get(
          url: ApiURL.getCities,
          useAuthToken: true,
          queryParameters: {
            ApiURL.limitApiKey: limit,
            ApiURL.offsetApiKey: offset ?? 0,
            ApiURL.searchApiKey: search ?? '',
          });

      return (
        citylist: ((result[ApiURL.dataKey] ?? []) as List)
            .map((city) => City.fromJson(Map.from(city ?? {})))
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<
      ({
        List<Zipcode> zipcodes,
        int total,
      })> getZipcodes({int? cityId, int? offset, String? search}) async {
    try {
      Map<String, dynamic>? queryParameters = {
        ApiURL.limitApiKey: limit,
        ApiURL.offsetApiKey: offset ?? 0,
        ApiURL.searchApiKey: search ?? '',
      };
      if (cityId != null) {
        queryParameters[ApiURL.cityIdApiKey] = cityId;
      }
      final result = await Api.get(
          url: cityId == null ? ApiURL.getZipcodes : ApiURL.getZipcodeByCityId,
          useAuthToken: true,
          queryParameters: queryParameters);

      return (
        zipcodes: ((result[ApiURL.dataKey] ?? []) as List)
            .map((zipcode) => Zipcode.fromJson(Map.from(zipcode ?? {})))
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<String> deleteAddress({
    required int addressId,
  }) async {
    try {
      final result = await Api.post(
        body: {ApiURL.idApiKey: addressId},
        url: ApiURL.deleteAddress,
        useAuthToken: true,
      );
      return result['message'].toString();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}
