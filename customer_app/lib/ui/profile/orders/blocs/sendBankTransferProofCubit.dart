import 'package:dio/dio.dart';
import 'package:eshop_plus/ui/profile/orders/repositories/orderRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SendBankTransferProofState {}

class SendBankTransferProofInitial extends SendBankTransferProofState {}

class SendBankTransferProofInProgress extends SendBankTransferProofState {}

class SendBankTransferProofSuccess extends SendBankTransferProofState {
  final String message;

  final List<String> attachmentUrls;
  final int orderId;

  SendBankTransferProofSuccess({
    required this.message,
    required this.attachmentUrls,
    required this.orderId,
  });
}

class SendBankTransferProofFailure extends SendBankTransferProofState {
  final String errorMessage;
  SendBankTransferProofFailure(this.errorMessage);
}

class SendBankTransferProofCubit extends Cubit<SendBankTransferProofState> {
  SendBankTransferProofCubit() : super(SendBankTransferProofInitial());

  Future<void> sendBankTransferProof(
      {required int orderId, required List<MultipartFile> attachments}) async {
    emit(SendBankTransferProofInProgress());
    try {
      final result = await OrderRepository()
          .sendBankTransferProof(orderId: orderId, attachments: attachments);

      // Extract attachment URLs from the response
      List<String> attachmentUrls = [];
      if (result['data'] != null && result['data']['attachments'] != null) {
        for (var attachment in result['data']['attachments']) {
          if (attachment['image_path'] != null) {
            attachmentUrls.add(attachment['image_path'].toString());
          }
        }
      }

      // Parse order_id as integer
      int responseOrderId = orderId;
      if (result['data'] != null && result['data']['order_id'] != null) {
        responseOrderId =
            int.tryParse(result['data']['order_id'].toString()) ?? orderId;
      }

      emit(SendBankTransferProofSuccess(
        message: result['message']?.toString() ?? '',
        attachmentUrls: attachmentUrls,
        orderId: responseOrderId,
      ));
    } catch (e) {
      emit(SendBankTransferProofFailure(e.toString()));
    }
  }
}
