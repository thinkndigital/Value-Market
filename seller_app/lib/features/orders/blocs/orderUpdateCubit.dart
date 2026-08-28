import 'package:value_market_seller/core/api/apiService.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';


//type = 1-orderupdate, 2-tracking update
abstract class OrderUpdateState {}

class OrderUpdateInitial extends OrderUpdateState {}

class OrderUpdateProgress extends OrderUpdateState {
  int type;
  OrderUpdateProgress(this.type);
}

class OrderUpdateSuccess extends OrderUpdateState {

  int type;
  String status;
  String? orderItemId;
  String successMsg;
  OrderUpdateSuccess(this.type, this.successMsg, this.status, this.orderItemId);

}

class OrderUpdateFailure extends OrderUpdateState {
  final String errorMessage;
  int type;
  OrderUpdateFailure(this.errorMessage, this.type);
}

class OrderUpdateCubit extends Cubit<OrderUpdateState> {
  OrderUpdateCubit() : super(OrderUpdateInitial());

  updateOrder(
    BuildContext context,
    Map<String, String?> parameter,
    int type,
    String apiurl,
  ) {
    emit(OrderUpdateProgress(type));
    updateOrderProcess(context, parameter, apiurl).then((value) {
      emit(OrderUpdateSuccess(type, value["message"], parameter["status"] ?? '',
          parameter["order_item_id"]));
    }).catchError((e) {
      emit(OrderUpdateFailure(e.toString(), type));
    });
  }

  Future updateOrderProcess(BuildContext context,
      Map<String, String?> parameter, String apiUrl) async {
    try {
      final result = await Api.put(
          url: apiUrl, useAuthToken: true, queryParameters: parameter);

      return result;
  
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
