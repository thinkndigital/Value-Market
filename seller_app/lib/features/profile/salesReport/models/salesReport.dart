class SalesReport {
  String? id;
  String? customerName;
  String? orderDate;
  double? total;
  double? deliveryCharge;
  double? discountedPrice;
  double? finalTotal;
  String? paymentMethod;
  String? sellerName;
  double? taxAmount;
  String? storeName;

  SalesReport({
    required this.id,
    required this.storeName,
    required this.taxAmount,
    required this.sellerName,
    required this.customerName,
    required this.orderDate,
    required this.total,
    required this.discountedPrice,
    required this.deliveryCharge,
    required this.finalTotal,
    required this.paymentMethod,
  });
  SalesReport.fromJson(Map<String, dynamic> json) {
    id = json["id"].toString();
    customerName = json["name"];
    total = double.tryParse((json['total'] ?? 0).toString().isEmpty
        ? "0"
        : (json['total'] ?? 0).toString());
    deliveryCharge = double.tryParse(
        (json['delivery_charge'] ?? 0).toString().isEmpty
            ? "0"
            : (json['delivery_charge'] ?? 0).toString());
    taxAmount = double.tryParse((json['tax_amount'] ?? 0).toString().isEmpty
        ? "0"
        : (json['tax_amount'] ?? 0).toString());
    discountedPrice = double.tryParse(
        (json['discounted_price'] ?? 0).toString().isEmpty
            ? "0"
            : (json['discounted_price'] ?? 0).toString());
    finalTotal = double.tryParse((json['final_total'] ?? 0).toString().isEmpty
        ? "0"
        : (json['final_total'] ?? 0).toString());
    paymentMethod = json["payment_method"];
    storeName = json["store_name"] ?? "";
    sellerName = json["seller_name"] ?? "";
    orderDate = json["date_added"] ?? "";
  }
}
