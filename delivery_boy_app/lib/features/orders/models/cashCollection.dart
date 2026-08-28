import 'package:eshoppro_deliveryboy/features/orders/models/order.dart';

class CashCollection {
int? id;
String? name;
String? mobile;
String? orderId;
String? cashReceived;
String? type;
String? amount;
String? message;
String? transactionDate;
String? date;
Order? orderDetails;

CashCollection({
this.id,
this.name,
this.mobile,
this.orderId,
this.cashReceived,
this.type,
this.amount,
this.message,
this.transactionDate,
this.date,
this.orderDetails,
});

CashCollection.fromJson(Map<String, dynamic> json) {
id = json['id'];
name = json['name'];
mobile = json['mobile'];
orderId = json['order_id'];
cashReceived = json['cash_received'];
type = json['type'];
amount = json['amount'];
message = json['message'];
transactionDate = json['transaction_date'];
date = json['date'];
orderDetails = json['order_details'] != null && json['order_details'] != ""
? Order.fromJson(json['order_details'])
: null;
}
}
