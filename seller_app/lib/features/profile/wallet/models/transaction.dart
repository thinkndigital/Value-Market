import 'package:eshopplus_seller/core/localization/labelKeys.dart';

class Transaction {
  late int id;
  String? transactionType;
  int? userId;
  String? orderId;
  String? orderItemId;
  String? type;
  String? txnId;
  String? payuTxnId;
  double? amount;
  String? status;
  String? currencyCode;
  String? payerEmail;
  String? message;
  String? transactionDate;
  int? isRefund;
  String? createdAt;
  String? updatedAt;

  Transaction(
      {required this.id,
      this.transactionType,
      this.userId,
      this.orderId,
      this.orderItemId,
      this.type,
      this.txnId,
      this.payuTxnId,
      this.amount,
      this.status,
      this.currencyCode,
      this.payerEmail,
      this.message,
      this.transactionDate,
      this.isRefund,
      this.createdAt,
      this.updatedAt});

  Transaction.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    transactionType = json['transaction_type'];
    userId = json['user_id'];
    orderId = json['order_id'];
    orderItemId = (json['order_item_id'] ?? 0).toString();
    type = json['type'];
    txnId = json['txn_id'];
    payuTxnId = json['payu_txn_id'];
    amount = json['amount'].toDouble();
    status = json['status'];
    currencyCode = json['currency_code'];
    payerEmail = json['payer_email'];
    message = json['message'];
    transactionDate = json['transaction_date'];
    isRefund = json['is_refund'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }
  Transaction.fromWithdrawJson(Map<String, dynamic> json) {
    id = json['id'];
    transactionType = json['payment_type'];
    userId = json['user_id'];
    type = withdrawKey;
    txnId = json['txn_id'];
    payuTxnId = json['payu_txn_id'];
    amount = double.tryParse((json['amount_requested'] ?? 0).toString().isEmpty
        ? "0"
        : (json['amount_requested'] ?? 0).toString());
    status = json['status_code'] == 0
        ? pendingKey
        : json['status_code'] == 1
            ? approvedKey
            : rejectedKey;
    message = json['remarks'];
    transactionDate = json['date_created'];
    createdAt = json['date_created'];
    updatedAt = json['updated_at'];
  }
}
