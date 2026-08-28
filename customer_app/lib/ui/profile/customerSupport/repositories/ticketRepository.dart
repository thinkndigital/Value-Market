import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import 'package:value_market_customer/ui/profile/customerSupport/models/ticket.dart';
import 'package:value_market_customer/ui/profile/customerSupport/models/ticketType.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';

class TicketRepository {
  Future<({List<Ticket> tickets, int total})> getTickets({
    int? offset,
  }) async {
    try {
      final result = await Api.get(
          url: ApiURL.getTickets,
          useAuthToken: true,
          queryParameters: {
            ApiURL.limitApiKey: limit,
            ApiURL.offsetApiKey: offset ?? 0,
          });

      return (
        tickets: ((result[ApiURL.dataKey] ?? []) as List)
            .map((ticket) => Ticket.fromJson(Map.from(ticket ?? {})))
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

  Future<List<TicketType>> getTicketTypes() async {
    try {
      final result = await Api.get(
        url: ApiURL.getTicketTypes,
        useAuthToken: true,
      );
      return (result[ApiURL.dataKey] as List)
          .map((ticketType) => TicketType.fromJson(Map.from(ticketType ?? {})))
          .toList();
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
            Ticket ticket,
            String successMessage,
          })>
      addOrEditTicket(
          {required Map<String, dynamic> params, required bool isEdit}) async {
    try {
      var result;
      if (isEdit) {
        result = await Api.put(
            url: ApiURL.editTicket,
            queryParameters: params,
            useAuthToken: true);
      } else {
        result = await Api.post(
            url: ApiURL.addTicket, body: params, useAuthToken: true);
      }
      return (
        ticket: Ticket.fromJson(Map.from(result[ApiURL.dataKey] ?? {})),
        successMessage: result['message'].toString(),
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}
